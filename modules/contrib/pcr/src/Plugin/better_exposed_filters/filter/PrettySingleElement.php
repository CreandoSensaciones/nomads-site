<?php

namespace Drupal\pcr\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\Single;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "pretty_single_bef",
 *   label = @Translation("Pretty Single On/Off Checkbox"),
 * )
 */
class PrettySingleElement extends Single {

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    // Form element is designated by the element ID which is user-
    // configurable.
    $field_id = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id])) {
      // Add support for pretty elements.
      $form[$field_id]['#pretty_option'] = TRUE;
    }
  }

}
