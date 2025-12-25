<?php

namespace Drupal\domain_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;

/**
 * Settings for the module.
 *
 * @package Drupal\domain_access\Form
 */
class DomainAccessSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['node_advanced_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Move Domain Access fields to advanced node settings.'),
      '#config_target' => 'domain_access.settings:node_advanced_tab',
      '#description' => $this->t('When checked the Domain Access fields will be shown as a tab in the advanced settings on node edit form. However, if you have placed the fields in a field group already, they will not be moved.'),
    ];
    $form['node_advanced_tab_open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open the Domain Access details.'),
      '#description' => $this->t('Set the details tab to be open by default.'),
      '#config_target' => 'domain_access.settings:node_advanced_tab_open',
      '#states' => [
        'visible' => [
          ':input[name="node_advanced_tab"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

}
