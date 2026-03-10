<?php

namespace Drupal\pretty_select_or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\select_or_other\Plugin\Field\FieldWidget\ReferenceWidget;

/**
 * Plugin implementation of the 'pretty_select_or_other_reference' widget.
 *
 * @FieldWidget(
 *   id = "pretty_select_or_other_reference",
 *   label = @Translation("Pretty select or other"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class PrettySelectOrOtherReferenceWidget extends ReferenceWidget {

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
