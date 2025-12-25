<?php

namespace Drupal\domain_menu_access\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Domain Access settings for this site.
 *
 * @internal
 */
class DomainMenuAccessSettingsForm extends ConfigFormBase {

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuStorage;

  /**
   * Constructs a DomainMenuAccessSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed configuration manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, TypedConfigManagerInterface $typed_config_manager) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->menuStorage = $entity_type_manager->getStorage('menu');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_menu_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['domain_menu_access.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_menu_access.settings')->get('menu_enabled');

    $menu = $this->menuStorage->loadMultiple();
    if (count($menu) === 0) {
      $form['markup'] = [
        '#markup' => $this->t('Your menu list is empty. Please, try add the menu and return here.'),
      ];
    }
    else {
      $form['description'] = [
        '#markup' => $this->t('Please, select menu for enable control by domain records.'),
      ];
      /** @var \Drupal\system\Entity\Menu $item */
      foreach ($menu as $key => $item) {
        $form[$key] = [
          '#type' => 'checkbox',
          '#title' => $item->label(),
          '#default_value' => is_array($config) ? (in_array($key, $config, TRUE) ? '1' : '') : '',
          '#description' => $item->getDescription(),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('domain_menu_access.settings');
    $menu = array_keys($this->menuStorage->loadMultiple());
    if (count($menu) !== 0 && count($form_state->getValues()) !== 0) {
      $menu_enabled = [];
      $values = $form_state->getValues();
      foreach ($values as $key => $value) {
        if ($value && in_array($key, $menu, TRUE)) {
          $menu_enabled[] = $key;
        }
      }
      $config->set('menu_enabled', $menu_enabled);
      $config->save();
    }
    parent::submitForm($form, $form_state);
  }

}
