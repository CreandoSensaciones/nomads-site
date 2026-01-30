<?php

namespace Drupal\nomads_fitted_pills\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Field formatter for taxonomy term reference fields.
 */
#[FieldFormatter(
  id: 'nomads_fitted_pills_term_ref',
  label: new TranslatableMarkup('Pills'),
  field_types: [
    'entity_reference',
  ],
)]
class FittedPillsTermRefFormatter extends FittedPillsFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $pill_items = [];
    $show_tooltip = (bool) $this->getSetting('show_tooltip');
    $fit_pills = (bool) $this->getSetting('fit_pills');
    $max_rows = max(0, (int) $this->getSetting('max_rows'));
    $auto_big_pills = (bool) $this->getSetting('auto_big_pills');
    $auto_big_pills_threshold = max(0, (int) $this->getSetting('auto_big_pills_threshold'));
    $big_pills_max_chars_per_row = max(1, (int) $this->getSetting('big_pills_max_chars_per_row'));
    $radical_fitting = (bool) $this->getSetting('radical_fitting');
    $radical_threshold = max(0, (int) $this->getSetting('radical_fitting_threshold'));
    $overhead = (int) $this->getSetting('pill_overhead');
    $total_label_chars = 0;

    foreach ($items as $item) {
      if (!$item->entity) {
        continue;
      }
      $term = $item->entity;
      $tooltip = '';
      if ($show_tooltip && $term->hasField('field_tooltip') && !$term->get('field_tooltip')->isEmpty()) {
        $tooltip = (string) $term->get('field_tooltip')->value;
      }

      $label = $this->stripPrioritySuffix($term->label());
      $total_label_chars += strlen($label);

      $pill_items[] = [
        'label' => $label,
        'entity' => $term,
        'tooltip' => $tooltip,
      ];
    }

    $pill_builder = static function (array $pill_item): array {
      if (!isset($pill_item['entity'])) {
        return ['#plain_text' => (string) ($pill_item['label'] ?? '')];
      }

      $term = $pill_item['entity'];
      $tooltip = (string) ($pill_item['tooltip'] ?? '');

      $link = [
        '#type' => 'link',
        '#title' => $pill_item['label'],
        '#url' => $term->toUrl(),
      ];

      if ($tooltip !== '') {
        $link['#attributes']['title'] = $tooltip;
      }

      return $link;
    };

    $big_pills = FALSE;
    $packer_settings = $this->getPackerSettings();
    if ($auto_big_pills && $total_label_chars < $auto_big_pills_threshold) {
      $big_pills = TRUE;
      $fit_pills = TRUE;
      $packer_settings = $this->getPackerSettingsWithOverrides([
        'max_chars_per_row' => $big_pills_max_chars_per_row,
      ]);
    }

    if (!$fit_pills) {
      $inline_build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-fitted-pills'],
        ],
        '#attached' => [
          'library' => [
            'nomads_fitted_pills/fitted-pills',
          ],
        ],
      ];

      foreach ($pill_items as $pill_index => $pill_item) {
        $inline_build['pill_' . $pill_index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-fitted-pills__pill'],
          ],
          0 => $pill_builder($pill_item),
        ];
      }

      return $inline_build === [] ? [] : [0 => $inline_build];
    }

    $active_items = $pill_items;
    if ($max_rows > 0) {
      $active_items = $this->trimItemsToMaxRows($active_items, $max_rows, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
    }

    $rows = $this->buildRowsForItems($active_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);

    $build = $this->buildRenderArray($rows, $pill_builder, $big_pills ? ['big-pills'] : []);
    return $build === [] ? [] : [0 => $build];
  }

}
