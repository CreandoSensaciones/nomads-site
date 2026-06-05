<?php

namespace Drupal\nomads_display_tweaks\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formats list text labels as Nomads pills with inline label markers.
 */
#[FieldFormatter(
  id: 'nomads_display_tweaks_tweaked_pills',
  label: new TranslatableMarkup('Tweaked pills'),
  field_types: [
    'list_integer',
    'list_string',
  ],
)]
class TweakedPillsFormatter extends TweakedLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);

    if (
      !empty($elements['#theme'])
      && $elements['#theme'] === 'field'
      && ($elements['#label_display'] ?? NULL) === 'hidden'
      && empty($elements['#is_multiple'])
      && isset($elements[0])
    ) {
      $item_attributes = [];
      foreach ($items as $item) {
        $item_attributes = (array) ($item->_attributes ?? []);
        break;
      }
      $item_attributes['class'] = array_values(array_unique(array_merge(
        (array) ($item_attributes['class'] ?? []),
        ['field__item', 'nomads-pill']
      )));

      $build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'field',
            'field--name-' . Html::getClass($this->fieldDefinition->getName()),
            'field--type-' . Html::getClass($this->fieldDefinition->getType()),
            'field--label-hidden',
            'field__item-wrapper',
          ],
        ],
        0 => [
          '#type' => 'container',
          '#attributes' => $item_attributes,
          0 => $elements[0],
        ],
      ];

      foreach (['#cache', '#attached'] as $property) {
        if (isset($elements[$property])) {
          $build[$property] = $elements[$property];
        }
      }

      return $build;
    }

    if (!empty($elements['#theme']) && $elements['#theme'] === 'field') {
      $elements['#nomads_display_tweaks_single_item_wrapper'] = TRUE;
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $elements = [];
    $allowed_values = [];
    if (function_exists('options_allowed_values')) {
      $allowed_values = options_allowed_values($this->fieldDefinition->getFieldStorageDefinition(), $items->getEntity()) ?: [];
      $allowed_values = $this->flattenAllowedValues($allowed_values);
    }

    foreach ($items as $delta => $item) {
      $value = (string) $item->value;
      $label = (string) ($allowed_values[$value] ?? $value);
      $tooltip = '';
      $parts = explode(' -- ', $label, 2);
      if (count($parts) === 2) {
        $label = $parts[0];
        $tooltip = $parts[1];
      }
      $label = $this->stripTrailingNumberMarker($label);

      $item_attributes = (array) ($item->_attributes ?? []);
      $item_attributes['class'] = array_values(array_unique(array_merge(
        (array) ($item_attributes['class'] ?? []),
        ['nomads-pill']
      )));
      if ($tooltip !== '') {
        $item_attributes['title'] = $tooltip;
      }
      $item->_attributes = $item_attributes;

      $elements[$delta] = [
        '#markup' => Markup::create($this->replaceSmallTextMarkers($label)),
      ];
    }

    return $elements;
  }

}
