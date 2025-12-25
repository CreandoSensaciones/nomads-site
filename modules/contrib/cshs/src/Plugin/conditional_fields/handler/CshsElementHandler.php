<?php

namespace Drupal\cshs\Plugin\conditional_fields\handler;

use Drupal\conditional_fields\Plugin\conditional_fields\handler\DefaultStateHandler;

/**
 * Conditional fields handler states_handler_cshs.
 *
 * @ConditionalFieldsHandler(
 *   id = "states_handler_cshs",
 * )
 */
class CshsElementHandler extends DefaultStateHandler {

  /**
   * {@inheritdoc}
   */
  public function statesHandler($field, $field_info, $options) {
    $options['value_form'] = array_map(function ($option) {
      return $option['target_id'];
    }, $options['value_form']);
    return parent::statesHandler($field, $field_info, $options);
  }

}
