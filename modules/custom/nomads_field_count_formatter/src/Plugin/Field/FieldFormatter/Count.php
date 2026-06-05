<?php

/**
 * @file
 * Contains \Drupal\nomads_field_count_formatter\Plugin\Field\FieldFormatter\Count.
 */

namespace Drupal\nomads_field_count_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'CountFormatter' formatter.
 */
#[FieldFormatter(
  id: 'count',
  label: new TranslatableMarkup('Field count'),
  field_types: [],
)]
class Count extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Displays the number of items/count.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Needs to be nested (element 0) so we preserve the default field title rendering.
    return [
      [
        '#markup' => $items->count(),
      ],
    ];
  }
}
