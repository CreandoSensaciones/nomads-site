<?php

namespace Drupal\domain\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Domain module.
 *
 * @package Drupal\domain\Form
 */
class DomainSettingsForm extends ConfigFormBase {

  use RedundantEditableConfigNamesTrait;

  /**
   * The domain token handler.
   *
   * @var \Drupal\domain\DomainToken
   */
  protected $domainTokens;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->domainTokens = $container->get('domain.token');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['allow_non_ascii'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow non-ASCII characters in domains and aliases'),
      '#config_target' => 'domain.settings:allow_non_ascii',
      '#description' => $this->t('Domains may be registered with international character sets. Note that not all DNS server respect non-ascii characters.'),
    ];
    $form['www_prefix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore www prefix when negotiating domains'),
      '#config_target' => 'domain.settings:www_prefix',
      '#description' => $this->t('Domain negotiation will ignore any www prefixes for all requests.'),
    ];
    // Get the usable tokens for this field.
    $patterns = [];
    foreach ($this->domainTokens->getCallbacks() as $key => $callback) {
      $patterns[] = "[domain:$key]";
    }
    $form['css_classes'] = [
      '#type' => 'textfield',
      '#size' => 80,
      '#title' => $this->t('Custom CSS classes'),
      '#config_target' => 'domain.settings:css_classes',
      '#description' => $this->t('Enter any CSS classes that should be added to the &lt;body&gt; tag. Available replacement patterns are: @patterns', [
        '@patterns' => implode(', ', $patterns),
      ]),
    ];
    $form['login_paths'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#columns' => 40,
      '#title' => $this->t('Paths that should be accessible for inactive domains'),
      '#config_target' => 'domain.settings:login_paths',
      '#description' => $this->t('Inactive domains are only accessible to users with permission.
        Enter any paths that should be accessible, one per line. Normally, only the
        login path will be allowed.'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
