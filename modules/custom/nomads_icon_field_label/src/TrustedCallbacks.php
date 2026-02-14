<?php

namespace Drupal\nomads_icon_field_label;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Trusted render callbacks for the Nomads Icon Field Label module.
 */
final class TrustedCallbacks implements TrustedCallbackInterface {

  /**
   * Pre-render callback to inject the icon before field content.
   */
  public static function preRenderField(array $element): array {
    if (($element['#label_display'] ?? NULL) !== 'icon') {
      return $element;
    }

    $entity_type = $element['#entity_type'] ?? '';
    $bundle = $element['#bundle'] ?? '';
    $field_name = $element['#field_name'] ?? '';
    $icon_markup = nomads_icon_field_label_get_icon_markup($entity_type, $bundle, $field_name, (string) ($element['#title'] ?? ''));

    $element['#label_display'] = 'hidden';
    $element['#title'] = '';
    $element['#attached']['library'][] = 'nomads_icon_field_label/icon-label';

    $children = Element::children($element);
    $first_child = $children[0] ?? NULL;
    if ($first_child !== NULL) {
      $item_content = $element[$first_child];
      $element[$first_child] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['nomads-icon-field-label__wrapper']],
        'icon' => ['#markup' => $icon_markup],
        'content' => is_array($item_content) ? $item_content : ['#markup' => $item_content],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderField'];
  }

}
