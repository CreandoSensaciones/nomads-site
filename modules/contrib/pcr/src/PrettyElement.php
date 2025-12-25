<?php

namespace Drupal\pcr;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides methods for Drupal render pretty elements.
 */
class PrettyElement {

  /**
   * Processes checkboxes and radios form element.
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    // If the element to be processed has the #pretty_option widget.
    if (isset($element['#pretty_option'])) {
      // Check if the current element is Boolean or List text type.
      switch ($element['#type']) {
        case 'checkbox':
        case 'radio':
          // Modify the current element to change it to pretty element.
          $element = self::setValues($element);
          break;

        case 'checkboxes':
        case 'radios':
          if (isset($element['#options']) && count($element['#options']) > 0) {
            foreach ($element['#options'] as $key => $choice) {
              // Modify the current element to change it to pretty element.
              $element[$key] = self::setValues($element[$key]);
            }
          }
          break;
      }
    }
    return $element;
  }

  /**
   * Modify an element to change it to pretty element.
   *
   * @param array $element
   *   The element to be modified.
   *
   * @return array
   *   The element modified.
   */
  private static function setValues(array $element): array {
    $element['#theme'] = 'elements__pretty_options';
    $element['#title_display'] = 'hidden';
    $element['#attached']['library'][] = 'pcr/pretty_elements';
    return $element;
  }

}
