<?php

namespace Drupal\nomads_fitted_pills\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nomads_fitted_pills\Packer\FittedPillsPacker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base formatter for fitted pills.
 */
abstract class FittedPillsFormatterBase extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The packer service.
   */
  protected FittedPillsPacker $packer;

  /**
   * Constructs a fitted pills formatter.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    FittedPillsPacker $packer,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->packer = $packer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('nomads_fitted_pills.packer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'max_chars_per_row' => FittedPillsPacker::MAX_CHARS_PER_ROW,
      'pill_overhead' => FittedPillsPacker::PILL_OVERHEAD,
      'trigger_gap' => FittedPillsPacker::TRIGGER_GAP,
      'min_improvement' => FittedPillsPacker::MIN_IMPROVEMENT,
      'show_tooltip' => FALSE,
      'fit_pills' => FALSE,
      'max_rows' => 0,
      'auto_big_pills' => FALSE,
      'auto_big_pills_threshold' => 0,
      'big_pills_max_chars_per_row' => FittedPillsPacker::MAX_CHARS_PER_ROW,
      'radical_fitting' => FALSE,
      'radical_fitting_threshold' => 150,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['max_chars_per_row'] = [
      '#type' => 'number',
      '#title' => $this->t('Max chars per row'),
      '#default_value' => $this->getSetting('max_chars_per_row'),
      '#min' => 1,
    ];

    $element['pill_overhead'] = [
      '#type' => 'number',
      '#title' => $this->t('Pill overhead'),
      '#default_value' => $this->getSetting('pill_overhead'),
      '#min' => 0,
    ];

    $element['trigger_gap'] = [
      '#type' => 'number',
      '#title' => $this->t('Trigger gap'),
      '#default_value' => $this->getSetting('trigger_gap'),
      '#min' => 0,
    ];

    $element['min_improvement'] = [
      '#type' => 'number',
      '#title' => $this->t('Min improvement'),
      '#default_value' => $this->getSetting('min_improvement'),
      '#min' => 0,
    ];

    $element['show_tooltip'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show tooltip'),
      '#default_value' => $this->getSetting('show_tooltip'),
    ];

    $element['fit_pills'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fit pills into box'),
      '#default_value' => $this->getSetting('fit_pills'),
    ];

    $element['max_rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Max. Rows'),
      '#default_value' => $this->getSetting('max_rows'),
      '#min' => 0,
      '#description' => $this->t('Set to 0 for no limit. Applies only when fitting pills into a box.'),
    ];

    $element['auto_big_pills'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto big pills'),
      '#default_value' => $this->getSetting('auto_big_pills'),
    ];

    $element['auto_big_pills_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Auto big pills threshold'),
      '#default_value' => $this->getSetting('auto_big_pills_threshold'),
      '#min' => 0,
    ];

    $element['big_pills_max_chars_per_row'] = [
      '#type' => 'number',
      '#title' => $this->t('Big pills max chars per row'),
      '#default_value' => $this->getSetting('big_pills_max_chars_per_row'),
      '#min' => 1,
    ];

    $element['radical_fitting'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Radical fitting'),
      '#default_value' => $this->getSetting('radical_fitting'),
    ];

    $element['radical_fitting_threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Radical fitting threshold'),
      '#default_value' => $this->getSetting('radical_fitting_threshold'),
      '#min' => 0,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Max chars per row: @value', ['@value' => $this->getSetting('max_chars_per_row')]),
      $this->t('Pill overhead: @value', ['@value' => $this->getSetting('pill_overhead')]),
      $this->t('Trigger gap: @value', ['@value' => $this->getSetting('trigger_gap')]),
      $this->t('Min improvement: @value', ['@value' => $this->getSetting('min_improvement')]),
      $this->t('Show tooltip: @value', ['@value' => $this->getSetting('show_tooltip') ? $this->t('Yes') : $this->t('No')]),
      $this->t('Fit pills into box: @value', ['@value' => $this->getSetting('fit_pills') ? $this->t('Yes') : $this->t('No')]),
      $this->t('Max. Rows: @value', [
        '@value' => ((int) $this->getSetting('max_rows') <= 0) ? $this->t('Unlimited') : $this->getSetting('max_rows'),
      ]),
      $this->t('Auto big pills: @value', ['@value' => $this->getSetting('auto_big_pills') ? $this->t('Yes') : $this->t('No')]),
      $this->t('Auto big pills threshold: @value', ['@value' => $this->getSetting('auto_big_pills_threshold')]),
      $this->t('Big pills max chars per row: @value', ['@value' => $this->getSetting('big_pills_max_chars_per_row')]),
      $this->t('Radical fitting: @value', ['@value' => $this->getSetting('radical_fitting') ? $this->t('Yes') : $this->t('No')]),
      $this->t('Radical fitting threshold: @value', ['@value' => $this->getSetting('radical_fitting_threshold')]),
    ];
  }

  /**
   * Builds a render array for rows of pills.
   *
   * @param array $rows
   *   Rows of items.
   * @param callable $pill_builder
   *   Builder for pill content.
   *
   * @return array
   *   Render array.
   */
  protected function buildRenderArray(array $rows, callable $pill_builder, array $extra_classes = []): array {
    if ($rows === []) {
      return [];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => array_merge(['nomads-fitted-pills'], $extra_classes),
      ],
      '#attached' => [
        'library' => [
          'nomads_fitted_pills/fitted-pills',
        ],
      ],
    ];

    foreach ($rows as $row_index => $row_items) {
      $row_build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-fitted-pills__row'],
        ],
      ];

      foreach ($row_items as $pill_index => $pill_item) {
        $row_build['pill_' . $pill_index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-fitted-pills__pill'],
          ],
          0 => $pill_builder($pill_item),
        ];
      }

      $build['row_' . $row_index] = $row_build;
    }

    return $build;
  }

  /**
   * Gets packer settings for this formatter.
   */
  protected function getPackerSettings(): array {
    return [
      'max_chars_per_row' => (int) $this->getSetting('max_chars_per_row'),
      'pill_overhead' => (int) $this->getSetting('pill_overhead'),
      'trigger_gap' => (int) $this->getSetting('trigger_gap'),
      'min_improvement' => (int) $this->getSetting('min_improvement'),
    ];
  }

  /**
   * Gets packer settings with overrides.
   */
  protected function getPackerSettingsWithOverrides(array $overrides): array {
    return $overrides + $this->getPackerSettings();
  }

  /**
   * Builds rows for the given pill items with current settings.
   */
  protected function buildRowsForItems(array $pill_items, int $overhead, bool $radical_fitting, int $radical_threshold, array $packer_settings): array {
    $total_chars = 0;
    foreach ($pill_items as $pill_item) {
      $total_chars += strlen((string) ($pill_item['label'] ?? '')) + $overhead;
    }

    if ($radical_fitting && $total_chars > $radical_threshold) {
      $short_items = [];
      $long_items = [];

      foreach ($pill_items as $pill_item) {
        $label_length = strlen((string) ($pill_item['label'] ?? ''));
        if ($label_length <= FittedPillsPacker::SHORT_POOL_MAX_LABEL_LEN) {
          $short_items[] = $pill_item;
        }
        else {
          $long_items[] = $pill_item;
        }
      }

      if ($long_items === []) {
        return $this->packer->pack($short_items, $packer_settings);
      }

      return $this->packer->packWithShortPool($long_items, $short_items, $packer_settings);
    }

    return $this->packer->pack($pill_items, $packer_settings);
  }

  /**
   * Trims items from the end until they fit within the max rows.
   */
  protected function trimItemsToMaxRows(array $pill_items, int $max_rows, int $overhead, bool $radical_fitting, int $radical_threshold, array $packer_settings): array {
    if ($max_rows <= 0) {
      return $pill_items;
    }

    $rows = $this->buildRowsForItems($pill_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
    while ($pill_items !== [] && count($rows) > $max_rows) {
      array_pop($pill_items);
      $rows = $this->buildRowsForItems($pill_items, $overhead, $radical_fitting, $radical_threshold, $packer_settings);
    }

    return $pill_items;
  }

  /**
   * Determines a priority suffix from a label.
   */
  protected function getPriorityFromLabel(string $label): int {
    if (preg_match('/\\s\\*([23])$/', $label, $matches) !== 1) {
      return 0;
    }

    return (int) $matches[1];
  }

  /**
   * Strips trailing priority suffixes from labels.
   */
  protected function stripPrioritySuffix(string $label): string {
    return preg_replace('/\\s\\*[23]$/', '', $label);
  }

}
