<?php

namespace Drupal\nomads_moderation;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Action;

/**
 * Shared helpers for Nomads listing moderation states.
 */
final class ListingState {

  public const ADVANCED_FIELD = 'advanced_listing';

  /**
   * Builds the Advanced listing node base-field definition.
   */
  public static function advancedListingBaseFieldDefinition(): BaseFieldDefinition {
    return BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Advanced listing'))
      ->setDescription(new TranslatableMarkup('Whether this listing should use the advanced full-page presentation.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
        'weight' => 17,
      ])
      ->setDisplayConfigurable('form', TRUE);
  }

  /**
   * Determines whether a listing is marked as advanced.
   */
  public static function isAdvanced(NodeInterface $node): bool {
    if ($node->bundle() !== 'listing' || !$node->hasField(self::ADVANCED_FIELD)) {
      return FALSE;
    }

    return (string) $node->get(self::ADVANCED_FIELD)->value === '1';
  }

  /**
   * Ensures the configured node actions exist for Views bulk forms.
   */
  public static function ensureActionConfig(): void {
    $actions = [
      'nomads_moderation_mark_listing_advanced_action' => [
        'label' => 'Mark selected listings as advanced',
        'plugin' => 'nomads_moderation_mark_listing_advanced',
      ],
      'nomads_moderation_mark_listing_not_advanced_action' => [
        'label' => 'Mark selected listings as not advanced',
        'plugin' => 'nomads_moderation_mark_listing_not_advanced',
      ],
    ];

    foreach ($actions as $id => $values) {
      $action = Action::load($id);
      if (!$action) {
        Action::create([
          'id' => $id,
          'label' => $values['label'],
          'type' => 'node',
          'plugin' => $values['plugin'],
          'configuration' => [],
        ])->save();
        continue;
      }

      $changed = FALSE;
      foreach (['label', 'plugin'] as $key) {
        if ($action->get($key) !== $values[$key]) {
          $action->set($key, $values[$key]);
          $changed = TRUE;
        }
      }
      if ($action->get('type') !== 'node') {
        $action->set('type', 'node');
        $changed = TRUE;
      }
      if ($changed) {
        $action->save();
      }
    }
  }

  /**
   * Removes obsolete field-based display tweak actions if they exist.
   */
  public static function removeLegacyActionConfig(): void {
    foreach ([
      'nomads_display_tweaks_mark_listing_advanced_action',
      'nomads_display_tweaks_mark_listing_simple_action',
    ] as $id) {
      if ($action = Action::load($id)) {
        $action->delete();
      }
    }
  }

}
