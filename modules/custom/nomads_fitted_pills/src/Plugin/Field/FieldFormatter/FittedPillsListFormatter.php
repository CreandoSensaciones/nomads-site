<?php

namespace Drupal\nomads_fitted_pills\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Field formatter for list (text) fields.
 */
#[FieldFormatter(
  id: 'nomads_fitted_pills_list',
  label: new TranslatableMarkup('Fitted pills'),
  field_types: [
    'list_string',
  ],
)]
class FittedPillsListFormatter extends FittedPillsFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }
    $pill_items = [];
    $allowed_values = [];
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

    if (function_exists('options_allowed_values')) {
      $allowed_values = options_allowed_values($this->fieldDefinition->getFieldStorageDefinition(), $items->getEntity()) ?: [];
    }

    foreach ($items as $item) {
      $value = (string) $item->value;
      $label = $allowed_values[$value] ?? $value;
      $tooltip = '';
      $priority = $this->getPriorityFromLabel($label);

      $parts = explode(' -- ', $label, 2);
      if (count($parts) === 2) {
        $label = $parts[0];
        $priority = $this->getPriorityFromLabel($label);
        if ($show_tooltip) {
          $tooltip = $parts[1];
        }
      }
      $label = $this->stripPrioritySuffix($label);
      $total_label_chars += strlen($label);

      $pill_items[] = [
        'label' => $label,
        'value' => $value,
        'priority' => $priority,
        'tooltip' => $tooltip,
      ];
    }
    if ($pill_items === []) {
      return [];
    }

    $big_pills = FALSE;
    $packer_settings = $this->getPackerSettings();
    if ($auto_big_pills && $total_label_chars < $auto_big_pills_threshold) {
      $big_pills = TRUE;
      $fit_pills = TRUE;
      $packer_settings = $this->getPackerSettingsWithOverrides([
        'max_chars_per_row' => $big_pills_max_chars_per_row,
      ]);
    }
    $max_chars_per_row = (int) ($packer_settings['max_chars_per_row'] ?? $this->getSetting('max_chars_per_row'));

    $pill_builder = static function (array $pill_item): array {
      $label = (string) ($pill_item['label'] ?? '');
      $tooltip = (string) ($pill_item['tooltip'] ?? '');

      if ($tooltip === '') {
        return ['#plain_text' => $label];
      }

      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
        '#attributes' => [
          'title' => $tooltip,
        ],
      ];
    };

    if (!$fit_pills) {
      $inline_build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-fitted-pills', 'nomads-pills', 'nomads-pills--fitted-pills'],
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
            'class' => ['nomads-fitted-pills__pill', 'nomads-pill'],
          ],
          0 => $pill_builder($pill_item),
        ];
      }

      return $inline_build === [] ? [] : [0 => $inline_build];
    }

    $active_items = $pill_items;
    if ($max_rows > 0) {
      $priority_two = [];
      $priority_three = [];
      $base_items = [];
      $removed_priority_two = FALSE;
      $removed_priority_three = FALSE;

      foreach ($pill_items as $pill_item) {
        $priority = (int) ($pill_item['priority'] ?? 0);
        if ($priority === 3) {
          $priority_three[] = $pill_item;
        }
        elseif ($priority === 2) {
          $priority_two[] = $pill_item;
        }
        else {
          $base_items[] = $pill_item;
        }
      }

      $threshold = $max_rows * $max_chars_per_row * 0.7;
      $total_chars = 0;
      foreach ($active_items as $pill_item) {
        $total_chars += strlen((string) ($pill_item['label'] ?? ''));
      }

      if ($total_chars > $threshold) {
        $active_items = array_merge($base_items, $priority_two);
        $removed_priority_three = TRUE;
        $total_chars = 0;
        foreach ($active_items as $pill_item) {
          $total_chars += strlen((string) ($pill_item['label'] ?? ''));
        }

        if ($total_chars > $threshold) {
          $active_items = $base_items;
          $removed_priority_two = TRUE;
        }
      }

      $active_items = $this->trimItemsToMaxRows($active_items, $max_rows, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
      $rows = $this->buildRowsForItems($active_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);

      $groups_to_add = [];
      if ($removed_priority_two) {
        $groups_to_add[] = $priority_two;
      }
      if ($removed_priority_three) {
        $groups_to_add[] = $priority_three;
      }

      foreach ($groups_to_add as $group) {
        foreach ($group as $pill_item) {
          $candidate_items = $active_items;
          $candidate_items[] = $pill_item;
          $candidate_rows = $this->buildRowsForItems($candidate_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
          if (count($candidate_rows) > $max_rows) {
            break 2;
          }
          $active_items = $candidate_items;
          $rows = $candidate_rows;
        }
      }
    }

    if ($max_rows <= 0) {
      $rows = $this->buildRowsForItems($pill_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
    }

    $build = $this->buildRenderArray($rows, $pill_builder, $big_pills ? ['big-pills'] : []);
    return $build === [] ? [] : [0 => $build];
  }

}
