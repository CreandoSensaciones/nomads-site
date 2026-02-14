<?php

namespace Drupal\nomads_easy_tagging\Plugin\conditional_fields\handler;

use Drupal\conditional_fields\ConditionalFieldsHandlerBase;
use Drupal\conditional_fields\ConditionalFieldsInterface;

/**
 * Provides states handler for the easy tagging widget.
 *
 * @ConditionalFieldsHandler(
 *   id = "states_handler_nomads_easy_tagging_master",
 * )
 */
class NomadsEasyTagging extends ConditionalFieldsHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function statesHandler($field, $field_info, $options) {
    $state = [];
    $selector = $options['selector'] ?? '';
    $name_prefix = '';
    $condition = $options['condition'] ?? '';

    if (!empty($field['#attributes']['data-name-prefix'])) {
      $name_prefix = $field['#attributes']['data-name-prefix'];
    }
    elseif (!empty($field['#field_name'])) {
      $name_prefix = $field['#field_name'];
    }
    elseif (!empty($field['#parents'][0])) {
      $name_prefix = $field['#parents'][0];
    }

    if ($name_prefix !== '') {
      $selector = 'input[name="' . $name_prefix . '[_net_values]"]';
    }

    if (empty($selector)) {
      return $state;
    }

    if ($condition === 'checked' || $condition === '!checked') {
      $mapped = $condition === 'checked' ? '!empty' : 'empty';
      $state[$options['state']][$selector] = [
        $mapped => TRUE,
      ];
      return $state;
    }

    if ($condition === 'empty' || $condition === '!empty') {
      $state[$options['state']][$selector] = [
        $condition => TRUE,
      ];
      return $state;
    }

    $values_set = $options['values_set'];
    $values = $this->getConditionValues($options);

    switch ($values_set) {
      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_WIDGET:
        $values = $this->getWidgetValue($options['value_form']);
        if (empty($values)) {
          return $state;
        }
        $state[$options['state']][$selector] = [
          'value' => $options['field_cardinality'] == 1 ? $values[0] : $values,
        ];
        break;

      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_REGEX:
        $state[$options['state']][$selector] = [
          'value' => ['regex' => $options['regex']],
        ];
        break;

      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_XOR:
        $state[$options['state']][$selector] = [
          'value' => ['xor' => $values],
        ];
        break;

      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_AND:
        $state[$options['state']][$selector] = [
          'value' => $values,
        ];
        break;

      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_NOT:
        $options['state'] = '!' . $options['state'];
      case ConditionalFieldsInterface::CONDITIONAL_FIELDS_DEPENDENCY_VALUES_OR:
        if (!empty($values)) {
          $escaped = array_map('preg_quote', $values);
          $pattern = '(^|\\n)(' . implode('|', $escaped) . ')(\\n|$)';
          $state[$options['state']][$selector] = [
            'value' => ['regex' => $pattern],
          ];
        }
        break;
    }

    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetValue(array $value_form) {
    if (empty($value_form)) {
      return [];
    }

    if (isset($value_form['_net_values']) && is_string($value_form['_net_values'])) {
      $values = array_filter(array_map('trim', explode("\n", $value_form['_net_values'])));
      return array_values($values);
    }

    if (isset($value_form['target_id']) && is_array($value_form['target_id'])) {
      return array_values(array_filter(array_map(static function ($item) {
        return isset($item['target_id']) && $item['target_id'] !== '' ? (string) $item['target_id'] : NULL;
      }, $value_form['target_id'])));
    }

    $values = [];
    foreach ($value_form as $value) {
      if (isset($value['target_id']) && $value['target_id'] !== '') {
        $values[] = (string) $value['target_id'];
      }
    }

    return $values;
  }

}
