<?php

namespace Drupal\domain_config_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;

/**
 * Form for module settings.
 */
class SettingsForm extends ConfigFormBase {

  use DomainConfigUITrait;
  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_config_ui_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_config_ui.settings');
    $form['remember_domain'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remember domain selection'),
      '#config_target' => 'domain_config_ui.settings:remember_domain',
      '#description' => $this->t('Keeps last selected domain when loading new configuration forms.'),
    ];
    $form['pages'] = [
      '#title' => $this->t('Enabled configuration forms'),
      '#type' => 'details',
      '#open' => TRUE,
    ];
    $form['pages']['path_pages'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#columns' => 40,
      '#config_target' => 'domain_config_ui.settings:path_pages',
      '#description' => $this->t("Specify pages by using their paths. Enter one path per line. Paths must start with /admin. Wildcards (*) are not supported. An example path is /admin/appearance for the Appearance page."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $path_string = $form_state->getValue('path_pages');
    $path_array = $this->explodePathSettings($path_string);
    $exists = [];
    foreach ($path_array as $path) {
      if (in_array($path, $exists, TRUE)) {
        $form_state->setError($form['pages']['path_pages'], $this->t('Duplicate paths cannot be added'));
      }
      $exists[] = $path;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clean session values.
    unset($_SESSION['domain_config_ui_domain']);
    unset($_SESSION['domain_config_ui_language']);

    parent::submitForm($form, $form_state);
  }

}
