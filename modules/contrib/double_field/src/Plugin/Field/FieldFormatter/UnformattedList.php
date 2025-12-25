<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField;

/**
 * Plugin implementations for 'double_field' formatter.
 */
#[FieldFormatter(
  id: self::ID,
  label: new TM('Unformatted List'),
  field_types: [DoubleField::ID],
)]
final class UnformattedList extends ListBase {

  public const string ID = 'double_field_unformatted_list';

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    $element['#attributes']['class'][] = 'double-field-unformatted-list';

    $settings = $this->getSettings();
    $field_name = $items->getName();
    foreach ($items as $delta => $item) {
      if ($settings['inline']) {
        if (!isset($item->_attributes)) {
          $item->_attributes = [];
        }
        $item->_attributes += ['class' => ['container-inline']];
      }
      $element[$delta] = [
        '#theme' => 'double_field_item',
        '#settings' => $settings,
        '#field_settings' => $this->getFieldSettings(),
        '#field_name' => $field_name,
        '#item' => $item,
      ];
    }

    return $element;
  }

}
