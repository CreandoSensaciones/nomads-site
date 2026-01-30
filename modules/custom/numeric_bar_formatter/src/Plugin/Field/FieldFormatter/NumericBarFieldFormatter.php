<?php

namespace Drupal\numeric_bar_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FieldFormatter(
  id: 'numeric_bar_formatter',
  label: new TranslatableMarkup('Numeric bar formatter'),
  field_types: [
    'integer',
    'list_integer',
  ],
)]
class NumericBarFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'numeric_bar_type' => 'bar',
      'numeric_bar_color' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['numeric_bar_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select progress bar type'),
      '#options' => [
        'bar' => $this->t('Rainbow Bar'),
        'colorbar' => $this->t('Color Bar'),
        'circle' => $this->t('Circular'),
        'circular' => $this->t('Color Circular'),
      ],
      '#default_value' => $this->getSetting('numeric_bar_type'),
    ];

    if ($this->fieldDefinition->getType() === 'integer') {
      $element['numeric_bar_type']['#description'] = '';
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('@setting: @value', [
        '@setting' => 'Progress bar type',
        '@value' => $this->getSetting('numeric_bar_type'),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $type = $this->getSetting('numeric_bar_type');

    if ($this->fieldDefinition->getType() === 'integer') {
      foreach ($items as $delta => $item) {
        $min = $this->fieldDefinition->getSetting('min') ?? 0;
        $max = $this->fieldDefinition->getSetting('max') ?? 100;
        $value = $max - $min > 0 ? round(($item->value / ($max - $min)) * 100) : 0;
        $elements[$delta] = [
          '#theme' => 'numeric_bar_format_' . $type,
          '#state' => [
            'state' => $value,
            'name' => $value . $this->fieldDefinition->getSetting('suffix'),
            'lowest_percent' => $value,
          ],
          '#attached' => [
            'library' => [
              'numeric_bar_formatter/progress-bar-' . $type,
            ],
          ],
        ];
      }

      return $elements;
    }

    if (!function_exists('options_allowed_values')) {
      return $elements;
    }

    $allowed_values = options_allowed_values($this->fieldDefinition->getFieldStorageDefinition(), $items->getEntity());
    if (!$allowed_values) {
      return $elements;
    }

    $list_count = count($allowed_values);
    return $this->getStateDetail($allowed_values, $list_count, $items);
  }

  /**
   * Generate the output appropriate for one field item.
   */
  protected function viewValue(FieldItemInterface $item): string {
    return nl2br(Html::escape($item->value));
  }

  /**
   * Helper function to get the color list.
   */
  protected function getColor(array $allowed_value, string $color_value, int $list_count): array {
    $color = $color_value !== '' ? explode(',', $color_value) : [''];
    $color_count = count($color);
    $color_data = [];

    if ($color_count < $list_count) {
      foreach ($allowed_value as $value) {
        $color_data[] = $color[0];
      }
    }
    else {
      $color_data = $color;
    }

    return $color_data;
  }

  /**
   * Helper function to get the state data.
   */
  protected function getStateData(array $allowed_value, int $list_count, int $search_value, array $color): array {
    $loop_count = 0;
    $state_data = [];
    $lowest_percent = (1 / $list_count) * 100;

    foreach ($allowed_value as $key => $value) {
      if ($loop_count < $search_value + 1) {
        $state = (($loop_count + 1) / $list_count) * 100;
        $state_data[] = [
          'state' => $state,
          'name' => $key,
          'color' => $color[$loop_count] ?? '',
          'lowest_percent' => $lowest_percent,
        ];
      }
      ++$loop_count;
    }

    return $state_data;
  }

  /**
   * Helper function to get the element data for state.
   */
  protected function getStateDetail(array $allowed_value, int $list_count, FieldItemListInterface $items): array {
    $elements = [];
    $color_value = (string) $this->getSetting('numeric_bar_color');
    $type_value = $this->getSetting('numeric_bar_type');
    $color = $this->getColor($allowed_value, $color_value, $list_count);

    foreach ($items as $delta => $item) {
      $search_value = array_search($this->viewValue($item), array_keys($allowed_value), TRUE);
      if ($search_value === FALSE) {
        continue;
      }
      $state = $this->getStateData($allowed_value, $list_count, $search_value, $color);
      $elements[$delta] = [
        '#theme' => 'numeric_bar_format_' . $type_value,
        '#state' => $state,
        '#attached' => [
          'library' => [
            'numeric_bar_formatter/progress-bar-' . $type_value,
          ],
        ],
      ];
    }

    return $elements;
  }

}
