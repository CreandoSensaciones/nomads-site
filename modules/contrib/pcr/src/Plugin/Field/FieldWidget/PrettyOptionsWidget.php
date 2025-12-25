<?php

namespace Drupal\pcr\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'options_pretty' widget.
 *
 * @FieldWidget(
 *   id = "options_pretty",
 *   label = @Translation("Pretty Check boxes/radio buttons"),
 *   field_types = {
 *     "list_integer",
 *     "list_string",
 *     "list_float",
 *     "boolean",
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class PrettyOptionsWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Set the #pretty_option for "Pretty Check boxes/radio buttons" Widget.
    $element['#pretty_option'] = TRUE;
    return $element;
  }

}
