<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField;

/**
 * Plugin implementations for 'html_list' formatter.
 */
#[FieldFormatter(
  id: self::ID,
  label: new TM('HTML List'),
  field_types: [DoubleField::ID],
)]
final class HtmlList extends ListBase {

  public const string ID = 'double_field_html_list';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['list_type' => 'ul'] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['list_type'] = [
      '#type' => 'radios',
      '#title' => new TM('List type'),
      '#options' => [
        'ul' => new TM('Unordered list'),
        'ol' => new TM('Ordered list'),
        'dl' => new TM('Definition list'),
      ],
      '#default_value' => $this->getSetting('list_type'),
    ];

    $element += parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $element['inline']['#states']['invisible'] = [":input[name='fields[$field_name][settings_edit_form][settings][list_type]']" => ['value' => 'dl']];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $parent_summary = parent::settingsSummary();

    // Definition list does not support 'inline' option.
    $list_type = $this->getSetting('list_type');
    if ($list_type === 'dl') {
      if (($key = \array_search(new TM('Display as inline element'), $parent_summary)) !== FALSE) {
        unset($parent_summary[$key]);
      }
    }

    $summary[] = new TM('List type: @list_type', ['@list_type' => $this->getSetting('list_type')]);
    return \array_merge($summary, $parent_summary);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();
    $field_name = $items->getName();

    if ($settings['list_type'] === 'dl') {
      $element[0] = [
        '#theme' => 'double_field_definition_list',
        '#items' => $items,
        '#settings' => $settings,
        '#field_settings' => $field_settings,
        '#field_name' => $field_name,
      ];
    }
    else {
      $list_items = [];
      foreach ($items as $delta => $item) {
        $list_items[$delta] = [
          '#theme' => 'double_field_item',
          '#item' => $item,
          '#settings' => $settings,
          '#field_settings' => $field_settings,
          '#field_name' => $field_name,
        ];
        if ($settings['inline']) {
          $list_items[$delta]['#wrapper_attributes'] = [];
          if (!isset($item->_attributes)) {
            $item->_attributes = [];
          }
          $list_items[$delta]['#wrapper_attributes'] += $item->_attributes;
          $list_items[$delta]['#wrapper_attributes']['class'][] = 'container-inline';
        }
      }
      $element[0] = [
        '#theme' => 'item_list',
        '#list_type' => $settings['list_type'],
        '#items' => $list_items,
        '#context' => ['double_field' => ['field_name' => $field_name]],
      ];
    }

    $element[0]['#attributes']['class'][] = 'double-field-list';

    return $element;
  }

}
