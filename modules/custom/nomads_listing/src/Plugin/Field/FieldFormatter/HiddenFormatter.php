<?php

namespace Drupal\nomads_listing\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FieldFormatter(
  id: 'nomads_hidden',
  label: new TranslatableMarkup('Hidden'),
  description: new TranslatableMarkup('Hide the field output without disabling it.'),
  field_types: [
    'string',
  ],
)]
class HiddenFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return [];
  }

}
