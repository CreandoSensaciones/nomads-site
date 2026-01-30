<?php

namespace Drupal\field_group_toggler\Plugin\field_group\FieldGroupFormatter;

use Drupal\field_group\Plugin\field_group\FieldGroupFormatter\Details;

/**
 * Plugin implementation of the 'toggle' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "toggle",
 *   label = @Translation("Field Group Toggler"),
 *   description = @Translation("Add a details element with a checkbox toggle"),
 *   supported_contexts = {
 *     "form",
 *     "view"
 *   }
 * )
 */
class FieldGroupToggler extends Details {

  /**
   * {@inheritdoc}
   */
  public function process(&$element, $processed_object) {
    parent::process($element, $processed_object);
    $element['#attributes']['class'][] = 'field-group-toggle';
    $element['#attached']['library'][] = 'field_group_toggler/field_group_toggler';
  }

}
