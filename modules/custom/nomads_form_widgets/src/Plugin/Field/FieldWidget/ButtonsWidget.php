<?php

namespace Drupal\nomads_form_widgets\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\options\Plugin\Field\FieldWidget\OptionsButtonsWidget;

/**
 * Plugin implementation of the 'nomads_buttons' widget.
 *
 * @FieldWidget(
 *   id = "nomads_buttons",
 *   label = @Translation("Buttons"),
 *   field_types = {
 *     "boolean",
 *     "list_string",
 *     "list_integer"
 *   }
 * )
 */
class ButtonsWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'nomads_form_widgets/buttons';
    $element['#attributes']['class'][] = 'nomads-buttons';

    return $element;
  }

}
