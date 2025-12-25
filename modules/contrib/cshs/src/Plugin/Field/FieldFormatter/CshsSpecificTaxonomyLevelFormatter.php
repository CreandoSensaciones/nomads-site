<?php

namespace Drupal\cshs\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the field formatter.
 *
 * @FieldFormatter(
 *   id = "cshs_specific_level_taxonomy",
 *   label = @Translation("Specific level taxonomy"),
 *   description = @Translation("Shows only specific level of taxonomy terms"),
 *   field_types = {
 *     "entity_reference",
 *   },
 * )
 */
class CshsSpecificTaxonomyLevelFormatter extends CshsFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['level'] = 1;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    unset($element['reverse']);

    $element['level'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('level'),
      '#description' => $this->t('The taxonomy level to show. If the term in the field has a different level it will be hidden. The root level is 1.'),
      '#default_value' => $this->getSetting('level'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];

    $summary[] = $this->t('Linked to term page: @linked', [
      '@linked' => $this->getSetting('linked') ? $this->t('Yes') : $this->t('No'),
    ]);

    $summary[] = $this->t('Level to show: @level', [
      '@level' => $this->getSetting('level') ?? $this->t('Root'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $level = (int) $this->getSetting('level');
    $linked = (bool) $this->getSetting('linked');
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $term) {
      $tree = $this->getTermParents($term);
      if (count($tree) !== $level) {
        continue;
      }

      $term_id = $term->id();
      if (!isset($elements[$term_id])) {
        $elements[$term_id] = [
          '#theme' => 'cshs_term_group',
          '#id' => $term_id,
          '#title' => $this->getTermsLabels([$term], $linked)[0] ?? $term->label(),
          '#terms' => [],
        ];
      }
    }

    return $elements;
  }

}
