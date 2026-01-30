<?php

namespace Drupal\magical_links\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'magical_links' widget.
 *
 * @FieldWidget(
 *   id = "magical_links",
 *   label = @Translation("Magical Links"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class MagicalLinksWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#prefix'] = '<div class="magical-links-widget">';
    $element['#suffix'] = '</div>';
    $element['#attached']['library'][] = 'magical_links/widget';

    $element['uri']['#attributes']['data-magical-links-uri'] = '1';
    $element['title']['#attributes']['data-magical-links-title'] = '1';

    $repo = \Drupal::service('magical_links.definition_repository');
    $definitions_data = $repo->getDefinitions('links', 'field_icons', 'field_prefill', TRUE, FALSE);
    $definitions = $definitions_data['definitions'];

    $element['magical_links'] = [
      '#type' => 'markup',
      '#weight' => -10,
      '#markup' => Markup::create($this->buildIconRow($definitions)),
    ];

    $definitions_data['cacheable_metadata']->applyTo($element);

    return $element;
  }

  /**
   * Build the icon row markup.
   */
  protected function buildIconRow(array $definitions): string {
    $groups = $this->getIconGroups($definitions);
    $markup = '<div class="magical-links-widget__icons">';

    foreach ($groups as $group) {
      $group_label = (string) ($group['label'] ?? '');
      $markup .= '<div class="magical-links-widget__group">';
      if ($group_label !== '') {
        $markup .= '<div class="magical-links-widget__group-title">' . Html::escape($group_label) . '</div>';
      }
      $markup .= '<div class="magical-links-widget__group-items">';
      foreach (($group['items'] ?? []) as $icon) {
        $label = (string) ($icon['label'] ?? '');
        $prefix = (string) ($icon['prefix'] ?? $icon['prefill'] ?? '');
        $link_text = (string) ($icon['link_text'] ?? '');
        $markup .= '<button type="button" class="magical-links-widget__icon" data-prefix="' . Html::escape($prefix) . '" data-link-text="' . Html::escape($link_text) . '" data-tooltip="' . Html::escape($label) . '" aria-label="' . Html::escape($label) . '">';
        $markup .= $this->buildIconMarkup($icon, $label, $link_text);
        $markup .= '</button>';
      }
      $markup .= '</div>';
      $markup .= '</div>';
    }

    $markup .= '</div>';
    return $markup;
  }

  /**
   * Group icons by parent term in tree order.
   */
  protected function getIconGroups(array $definitions): array {
    $groups = [];
    foreach ($definitions as $definition) {
      $group_key = $definition['group_key'] ?? 'root';
      if (!isset($groups[$group_key])) {
        $groups[$group_key] = [
          'label' => (string) ($definition['group_label'] ?? ''),
          'items' => [],
        ];
      }
      $groups[$group_key]['items'][] = $definition;
    }

    return array_values($groups);
  }

  /**
   * Build icon markup (term icon or SVG fallback).
   */
  protected function buildIconMarkup(array $icon, string $label, string $link_text): string {
    $icon_url = (string) ($icon['icon_url'] ?? '');
    if ($icon_url !== '') {
      $alt = $link_text !== '' ? $link_text : $label;
      return '<img src="' . Html::escape($icon_url) . '" alt="' . Html::escape($alt) . '" />';
    }

    $label_lower = strtolower($label);
    if ($label_lower === 'facebook') {
      return $this->svgFacebook();
    }
    if ($label_lower === 'instagram') {
      return $this->svgInstagram();
    }
    if ($label_lower === 'x') {
      return $this->svgX();
    }
    if ($label_lower === 'website') {
      return $this->svgGlobe();
    }

    return $this->svgGlobe();
  }

  protected function svgGlobe(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a16 16 0 0 0 0 18M12 3a16 16 0 0 1 0 18" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
  }

  protected function svgFacebook(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 8h3V5h-3c-2.8 0-4.5 1.8-4.5 4.6V12H7v3h2.5v4.5h3V15H15l.5-3h-3v-2c0-1 .4-2 1.5-2z" fill="currentColor"/></svg>';
  }

  protected function svgInstagram(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17" cy="7" r="1.5" fill="currentColor"/></svg>';
  }

  protected function svgX(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 5h3.5l3.2 4.3L17 5h3l-5.4 6.3L20 19h-3.6l-3.5-4.7L9 19H6l5.7-6.7L6 5z" fill="currentColor"/></svg>';
  }

  protected function svgMore(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="6" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="18" cy="12" r="2" fill="currentColor"/></svg>';
  }

}
