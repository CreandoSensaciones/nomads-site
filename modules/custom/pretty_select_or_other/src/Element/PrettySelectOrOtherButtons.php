<?php

namespace Drupal\pretty_select_or_other\Element;

use Drupal\Core\Form\FormStateInterface;

/**
 * Adds button styling to select_or_other_buttons when marked by this module.
 */
class PrettySelectOrOtherButtons {

  /**
   * Process callback for select_or_other_buttons.
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    if (empty($element['#pretty_select_or_other']) || empty($element['select']) || !is_array($element['select'])) {
      return $element;
    }

    // Match options_pretty wrapper semantics: use fieldset + legend.
    $element['#theme_wrappers'] = ['fieldset'];
    $element['#attributes']['class'][] = 'fieldgroup';
    $element['#attributes']['class'][] = 'form-composite';
    $element['#attributes']['class'][] = 'pretty-select-or-other-widget';
    $element['#attached']['library'][] = 'pretty_select_or_other/widget';

    if (!empty($element['other']) && is_array($element['other'])) {
      $element['other']['#wrapper_attributes']['class'][] = 'pretty-select-or-other-other-wrapper';
      $element['other']['#attributes']['class'][] = 'pretty-select-or-other-other-input';
    }

    $element['select']['#after_build'][] = [static::class, 'afterBuildSelect'];
    return $element;
  }

  /**
   * Adds classes after checkboxes/radios options are expanded.
   */
  public static function afterBuildSelect(array $element, FormStateInterface $form_state): array {
    if (!empty($element['#options']) && is_array($element['#options'])) {
      foreach ($element['#options'] as $key => $_label) {
        if (!isset($element[$key]) || !is_array($element[$key])) {
          continue;
        }
        // Match PCR's options_pretty output so existing theme styles apply.
        $element[$key]['#theme'] = 'elements__pretty_options';
        $element[$key]['#title_display'] = 'hidden';
        $element[$key]['#attached']['library'][] = 'pcr/pretty_elements';
      }
    }

    return $element;
  }

}
