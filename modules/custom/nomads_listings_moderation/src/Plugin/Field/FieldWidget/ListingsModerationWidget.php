<?php

namespace Drupal\nomads_listings_moderation\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;

/**
 * Plugin implementation of the 'nomads_listings_moderation' widget.
 */
#[FieldWidget(
  id: 'nomads_listings_moderation',
  label: new TranslatableMarkup('Listings moderation buttons'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class ListingsModerationWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings() + [
      'enabled_branches' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $tree = $this->buildTermTree($items);
    if (!empty($tree) && isset($element['#options']) && is_array($element['#options'])) {
      $allowed = array_flip($this->flattenTreeTerms($tree));
      $element['#options'] = array_intersect_key($element['#options'], $allowed);
    }

    $element['#attached']['library'][] = 'nomads_listings_moderation/listings_moderation_widget';
    $element['#attributes']['class'][] = 'nomads-listings-moderation-field';
    $element['#attributes']['data-nomads-moderation-field'] = $this->fieldDefinition->getName();
    $element['#wrapper_attributes']['class'][] = 'nomads-listings-moderation-field';
    $element['#wrapper_attributes']['data-nomads-moderation-field'] = $this->fieldDefinition->getName();
    $element['#attached']['drupalSettings']['nomadsListingsModeration']['tree'][$this->fieldDefinition->getName()] = $tree;

    return $element;
  }

  /**
   * Builds a taxonomy tree grouping for the widget UI.
   */
  protected function buildTermTree(FieldItemListInterface $items): array {
    if ($this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type') !== 'taxonomy_term') {
      return [];
    }
    $settings = $this->fieldDefinition->getSetting('handler_settings') ?? [];
    $vocabularies = $settings['target_bundles'] ?? [];
    if (empty($vocabularies)) {
      return [];
    }

    $allowed = array_flip(array_keys(OptGroup::flattenOptions($this->getOptions($items->getEntity()))));
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $groups = [];
    $enabled_setting = $this->getSetting('enabled_branches');
    $limit_branches = $enabled_setting !== [];
    $enabled = array_map('strval', array_keys(array_filter($enabled_setting ?? [])));

    foreach ($vocabularies as $vid) {
      $tree = $storage->loadTree($vid);
      $has_children = [];

      foreach ($tree as $term) {
        foreach ($term->parents as $parent_id) {
          if (!$parent_id || $parent_id === '0' || $parent_id === 0) {
            continue;
          }
          $has_children[(string) $parent_id] = TRUE;
        }
      }

      $current = NULL;

      foreach ($tree as $term) {
        if ((int) $term->depth === 0) {
          if ($current && !empty($current['terms'])) {
            $groups[] = $current;
          }
          $term_id = (string) $term->tid;
          if ($limit_branches && !in_array($term_id, $enabled, TRUE)) {
            $current = NULL;
            continue;
          }
          $current = [
            'label' => $term->name,
            'terms' => [],
          ];
          continue;
        }

        $term_id = (string) $term->tid;
        if (!isset($has_children[$term_id]) && isset($allowed[$term_id]) && $current) {
          $current['terms'][] = $term_id;
        }
      }

      if ($current && !empty($current['terms'])) {
        $groups[] = $current;
      }
    }

    return $groups;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $branches = $this->getBranchOptions();
    if (empty($branches)) {
      return $form;
    }

    $default = $this->getSetting('enabled_branches');
    if ($default === []) {
      $default = array_keys($branches);
    }

    $form['enabled_branches'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Visible tag branches'),
      '#options' => $branches,
      '#default_value' => $default,
      '#description' => $this->t('Uncheck a branch to hide its tags from the moderation widget.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $branches = $this->getBranchOptions();
    if (empty($branches)) {
      return $summary;
    }

    $enabled = $this->getSetting('enabled_branches');
    if ($enabled === []) {
      $summary[] = $this->t('Visible branches: All');
      return $summary;
    }

    $enabled_ids = array_keys(array_filter($enabled));
    $all_ids = array_keys($branches);
    $hidden = array_diff($all_ids, $enabled_ids);
    $summary[] = $this->t('Visible branches: @count of @total', [
      '@count' => count($enabled_ids),
      '@total' => count($all_ids),
    ]);
    if (!empty($hidden)) {
      $summary[] = $this->t('Hidden branches: @count', ['@count' => count($hidden)]);
    }

    return $summary;
  }

  /**
   * Builds checkbox options for top-level taxonomy branches.
   */
  protected function getBranchOptions(): array {
    if ($this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type') !== 'taxonomy_term') {
      return [];
    }
    $settings = $this->fieldDefinition->getSetting('handler_settings') ?? [];
    $vocabularies = $settings['target_bundles'] ?? [];
    if (empty($vocabularies)) {
      return [];
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $vocab_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    $options = [];

    foreach ($vocabularies as $vid) {
      $tree = $term_storage->loadTree($vid, 0, 1);
      if (empty($tree)) {
        continue;
      }
      $vocab = $vocab_storage->load($vid);
      $label = $vocab ? $vocab->label() : $vid;

      foreach ($tree as $term) {
        $term_id = (string) $term->tid;
        $options[$term_id] = $label . ' - ' . $term->name;
      }
    }

    return $options;
  }

  /**
   * Flattens grouped term IDs into a single ordered list.
   */
  protected function flattenTreeTerms(array $tree): array {
    $term_ids = [];
    foreach ($tree as $group) {
      foreach ($group['terms'] ?? [] as $term_id) {
        $term_ids[] = $term_id;
      }
    }
    return $term_ids;
  }

}
