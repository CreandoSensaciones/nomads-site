<?php

namespace Drupal\nomads_fitted_pills\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Simple pills formatter for taxonomy term reference fields.
 */
#[FieldFormatter(
  id: 'nomads_pills_term_ref',
  label: new TranslatableMarkup('Pills'),
  field_types: [
    'entity_reference',
  ],
)]
class PillsTermRefFormatter extends FittedPillsFormatterBase {

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
  public static function defaultSettings(): array {
    return [
      'link' => TRUE,
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
      'link' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Link'),
        '#default_value' => $this->getSetting('link'),
        '#description' => $this->t('Link pills to the taxonomy term page.'),
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
      $this->t('Link: @value', ['@value' => $this->getSetting('link') ? $this->t('Yes') : $this->t('No')]),
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
    $show_tooltip = (bool) $this->getSetting('show_tooltip');
    $link = (bool) $this->getSetting('link');

    foreach ($items as $item) {
      if (!$item->entity) {
        continue;
      }

      $term = $item->entity;
      $label = $this->stripPrioritySuffix($term->label());

      $tooltip = '';
      if ($show_tooltip && $term->hasField('field_tooltip') && !$term->get('field_tooltip')->isEmpty()) {
        $tooltip = (string) $term->get('field_tooltip')->value;
      }

      $pill_items[] = [
        'label' => $label,
        'entity' => $term,
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
      $tooltip = (string) ($pill_item['tooltip'] ?? '');

      if ($link && isset($pill_item['entity'])) {
        $content = [
          '#type' => 'link',
          '#title' => (string) $pill_item['label'],
          '#url' => $pill_item['entity']->toUrl(),
          '#attributes' => [
            'class' => ['nomads-pill__link'],
          ],
        ];
        if ($tooltip !== '') {
          $content['#attributes']['title'] = $tooltip;
        }
      }
      elseif ($tooltip !== '') {
        $content = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => (string) $pill_item['label'],
          '#attributes' => [
            'title' => $tooltip,
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
