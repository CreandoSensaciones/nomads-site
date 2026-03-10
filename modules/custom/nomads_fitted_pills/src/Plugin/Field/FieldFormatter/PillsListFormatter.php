<?php

namespace Drupal\nomads_fitted_pills\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Simple pills formatter for list (text) fields.
 */
#[FieldFormatter(
  id: 'nomads_pills_list',
  label: new TranslatableMarkup('Pills'),
  field_types: [
    'list_string',
  ],
)]
class PillsListFormatter extends FittedPillsFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'max_items' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [
      'show_tooltip' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show tooltip'),
        '#default_value' => $this->getSetting('show_tooltip'),
      ],
      'max_items' => [
        '#type' => 'number',
        '#title' => $this->t('Max items'),
        '#default_value' => (int) $this->getSetting('max_items'),
        '#min' => 0,
        '#description' => $this->t('Maximum pills to render. Use 0 for unlimited.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Show tooltip: @value', ['@value' => $this->getSetting('show_tooltip') ? $this->t('Yes') : $this->t('No')]),
      $this->t('Max items: @value', [
        '@value' => ((int) $this->getSetting('max_items') <= 0) ? $this->t('Unlimited') : (int) $this->getSetting('max_items'),
      ]),
    ];
  }

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

    if (function_exists('options_allowed_values')) {
      $allowed_values = options_allowed_values($this->fieldDefinition->getFieldStorageDefinition(), $items->getEntity()) ?: [];
    }

    foreach ($items as $item) {
      $value = (string) $item->value;
      $label = $allowed_values[$value] ?? $value;
      $tooltip = '';

      $parts = explode(' -- ', $label, 2);
      if (count($parts) === 2) {
        $label = $parts[0];
        if ($show_tooltip) {
          $tooltip = $parts[1];
        }
      }

      $pill_items[] = [
        'label' => $this->stripPrioritySuffix($label),
        'tooltip' => $tooltip,
      ];
    }

    if ($pill_items === []) {
      return [];
    }

    $max_items = max(0, (int) $this->getSetting('max_items'));
    if ($max_items > 0) {
      $pill_items = array_slice($pill_items, 0, $max_items);
    }

    $build = [
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
      $content = ['#plain_text' => (string) $pill_item['label']];
      if (!empty($pill_item['tooltip'])) {
        $content = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => (string) $pill_item['label'],
          '#attributes' => [
            'title' => (string) $pill_item['tooltip'],
          ],
        ];
      }

      $build['pill_' . $pill_index] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-fitted-pills__pill', 'nomads-pill'],
        ],
        0 => $content,
      ];
    }

    return [0 => $build];
  }

}
