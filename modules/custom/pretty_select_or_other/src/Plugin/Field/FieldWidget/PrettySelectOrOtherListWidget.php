<?php

namespace Drupal\pretty_select_or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\select_or_other\Plugin\Field\FieldWidget\ListWidget;

/**
 * Plugin implementation of the 'pretty_select_or_other_list' widget.
 *
 * @FieldWidget(
 *   id = "pretty_select_or_other_list",
 *   label = @Translation("Pretty select or other"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class PrettySelectOrOtherListWidget extends ListWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'select_element_type' => 'select_or_other_buttons',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#pretty_select_or_other'] = TRUE;
    return $element;
  }

}
