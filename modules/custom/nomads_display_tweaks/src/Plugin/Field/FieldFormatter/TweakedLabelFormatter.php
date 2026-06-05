<?php

namespace Drupal\nomads_display_tweaks\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formats list text labels with Nomads inline label markers.
 */
#[FieldFormatter(
  id: 'nomads_display_tweaks_tweaked_label',
  label: new TranslatableMarkup('Tweaked label'),
  field_types: [
    'list_integer',
    'list_string',
  ],
)]
class TweakedLabelFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $elements = [];
    $allowed_values = [];
    if (function_exists('options_allowed_values')) {
      $allowed_values = options_allowed_values($this->fieldDefinition->getFieldStorageDefinition(), $items->getEntity()) ?: [];
      $allowed_values = $this->flattenAllowedValues($allowed_values);
    }

    foreach ($items as $delta => $item) {
      $value = (string) $item->value;
      $label = (string) ($allowed_values[$value] ?? $value);

      $elements[$delta] = [
        '#markup' => Markup::create($this->buildTweakedLabelMarkup($label)),
      ];
    }

    return $elements;
  }

  /**
   * Flattens allowed values that may be grouped by optgroup label.
   */
  protected function flattenAllowedValues(array $allowed_values): array {
    $flattened = [];
    foreach ($allowed_values as $key => $value) {
      if (is_array($value)) {
        $flattened += $this->flattenAllowedValues($value);
        continue;
      }
      $flattened[(string) $key] = $value;
    }
    return $flattened;
  }

  /**
   * Builds escaped markup for one configured option label.
   */
  protected function buildTweakedLabelMarkup(string $label): string {
    $tooltip = '';
    $parts = explode(' -- ', $label, 2);
    if (count($parts) === 2) {
      $label = $parts[0];
      $tooltip = $parts[1];
    }
    $label = $this->stripTrailingNumberMarker($label);

    $markup = $this->replaceSmallTextMarkers($label);
    if ($tooltip === '') {
      return $markup;
    }

    $tag = str_contains($markup, '<div class="small">') ? 'div' : 'span';
    return '<' . $tag . ' title="' . Html::escape($tooltip) . '">' . $markup . '</' . $tag . '>';
  }

  /**
   * Replaces [text] and [[text]] label markers with escaped small markup.
   */
  protected function replaceSmallTextMarkers(string $label): string {
    $markup = '';
    $offset = 0;
    $matches = [];
    preg_match_all('/\[\[([^\]]+)\]\]|\[([^\[\]]+)\]/', $label, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as $index => $match) {
      [$matched_text, $position] = $match;
      $markup .= Html::escape(substr($label, $offset, $position - $offset));

      $block_text = $matches[1][$index][0] ?? '';
      if ($block_text !== '') {
        $markup .= '<div class="small">' . Html::escape($block_text) . '</div>';
      }
      else {
        $inline_text = $matches[2][$index][0] ?? '';
        $markup .= '<span class="small">' . Html::escape($inline_text) . '</span>';
      }

      $offset = $position + strlen($matched_text);
    }

    $markup .= Html::escape(substr($label, $offset));
    return $markup;
  }

  /**
   * Strips trailing markers like " *2" from labels before display.
   */
  protected function stripTrailingNumberMarker(string $label): string {
    return preg_replace('/\s+\*\d+$/', '', $label) ?? $label;
  }

}
