<?php

namespace Drupal\domain_config_ui\Config;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactory as CoreConfigFactory;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\domain_config_ui\DomainConfigUIManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Extends core ConfigFactory class to save domain specific configuration.
 */
class ConfigFactory extends CoreConfigFactory {

  /**
   * The container service.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * The manager service name.
   *
   * @var string
   */
  protected string $managerServiceName;

  /**
   * The Domain config UI manager.
   *
   * @var \Drupal\domain_config_ui\DomainConfigUIManagerInterface
   */
  protected $domainConfigUIManager;

  /**
   * Constructs the Config factory.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The configuration storage engine.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   An event dispatcher instance to use for configuration events.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed configuration manager.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container service.
   * @param string $manager_service_name
   *   The manager service name.
   */
  public function __construct(
    StorageInterface $storage,
    EventDispatcherInterface $event_dispatcher,
    TypedConfigManagerInterface $typed_config,
    ContainerInterface $container,
    string $manager_service_name,
  ) {
    parent::__construct($storage, $event_dispatcher, $typed_config);
    $this->container = $container;
    $this->managerServiceName = $manager_service_name;
  }

  /**
   * {@inheritDoc}
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    parent::onConfigSave($event);
    $config = $event->getConfig();
    if ($config instanceof Config) {
      // We inject the newly saved values in moduleOverrides
      // to make them immediately available after save.
      // An extra config get would be required otherwise.
      $config->setModuleOverride($config->getRawData());
    }
  }

  /**
   * Create a domain editable configuration object.
   *
   * @param string $name
   *   The name of the configuration object to create.
   *
   * @return \Drupal\domain_config_ui\Config\Config
   *   A new configuration object that is editable per domain.
   */
  protected function createDomainEditableConfigObject($name) {
    $config = new Config($name, $this->storage, $this->eventDispatcher, $this->typedConfigManager);
    // Pass the UI manager to the Config object.
    $config->setDomainConfigUiManager($this->getDomainConfigUiManager());
    return $config;
  }

  /**
   * Lazy load the Domain Config UI Manager service.
   *
   * Deferred loading is required to avoid a circular dependency error.
   */
  private function getDomainConfigUiManager() {
    if (!isset($this->domainConfigUIManager)) {
      $domain_config_ui_manager = $this->container->get($this->managerServiceName);
      if ($domain_config_ui_manager instanceof DomainConfigUIManagerInterface) {
        $this->setDomainConfigUiManager($domain_config_ui_manager);
      }
    }
    return $this->domainConfigUIManager;
  }

  /**
   * Set the Domain config UI manager.
   *
   * @param \Drupal\domain_config_ui\DomainConfigUIManagerInterface $domain_config_ui_manager
   *   The Domain config UI manager.
   */
  public function setDomainConfigUiManager(DomainConfigUIManagerInterface $domain_config_ui_manager) {
    $this->domainConfigUIManager = $domain_config_ui_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $names, $immutable = TRUE) {
    // Do not override if config is immutable or not editable per domain.
    // @todo This will need to change if we allow saving for 'all allowed domains'
    if ($immutable || !$this->getDomainConfigUIManager()->isPerDomainEditable($names)) {
      return parent::doLoadMultiple($names, $immutable);
    }

    $list = [];

    foreach ($names as $key => $name) {
      $cache_key = $this->getDomainEditableConfigCacheKey($name);
      if (isset($this->cache[$cache_key])) {
        $list[$name] = $this->cache[$cache_key];
        unset($names[$key]);
      }
    }

    // Pre-load remaining configuration files.
    if ($names !== []) {
      // Initialize override information.
      $module_overrides = [];
      $storage_data = $this->storage->readMultiple($names);

      // Load module overrides so that domain config is loaded in admin forms.
      if ($storage_data !== []) {
        // Only get domain overrides if we have configuration to override.
        $module_overrides = $this->loadDomainOverrides($names);
      }

      foreach ($storage_data as $name => $data) {
        $cache_key = $this->getDomainEditableConfigCacheKey($name);
        $this->cache[$cache_key] = $this->createDomainEditableConfigObject($name);
        $this->cache[$cache_key]->initWithData($data);
        if (isset($module_overrides[$name])) {
          $this->cache[$cache_key]->setModuleOverride($module_overrides[$name]);
        }
        $list[$name] = $this->cache[$cache_key];
      }
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGet($name, $immutable = TRUE) {
    // If config for 'all' domains or immutable then don't override config.
    if ($immutable || !$this->getDomainConfigUIManager()->isPerDomainEditable($name)) {
      return parent::doGet($name, $immutable);
    }
    $config = $this->doLoadMultiple([$name], $immutable);
    if (isset($config[$name])) {
      return $config[$name];
    }
    else {
      // If the configuration object does not exist in the configuration
      // storage, create a new object.
      $config = $this->createDomainEditableConfigObject($name);

      // Load domain overrides so domain config is loaded in admin forms.
      $overrides = $this->loadDomainOverrides([$name]);
      if (isset($overrides[$name])) {
        $config->setModuleOverride($overrides[$name]);
      }

      return $config;
    }
  }

  /**
   * Get the cache key for a domain editable configuration object.
   *
   * @param string $name
   *   The name of the configuration object.
   *
   * @return string
   *   The cache key for the configuration object, including domain and language
   */
  protected function getDomainEditableConfigCacheKey($name) {
    // We want to be able to cache all editable domain-specific configuration
    // objects, so we need to include the domain cache keys in the cache key.
    // Default implementation only add cache keys to immutable config.
    $suffix = ':' . $this->getDomainConfigUiManager()->getSelectedDomainId();
    $langcode = $this->getDomainConfigUiManager()->getSelectedLanguageId();
    if (!empty($langcode)) {
      $suffix .= '+' . $langcode;
    }
    // To avoid potential conflicts with the default config cache key.
    $suffix .= ':editable';
    return $name . $suffix;
  }

  /**
   * Get Domain module overrides for the named configuration objects.
   *
   * @param array $names
   *   The names of the configuration objects to get overrides for.
   *
   * @return array
   *   An array of overrides keyed by the configuration object name.
   */
  protected function loadDomainOverrides(array $names) {
    $overrides = [];
    foreach ($names as $name) {
      // Try to load the language-specific domain override.
      $config_name = $this->getDomainConfigUIManager()->getSelectedConfigName($name);
      $langcode = $this->getDomainConfigUIManager()->getSelectedLanguageId();
      $override = $this->storage->read($config_name);
      if ($override !== FALSE) {
        $overrides[$name] = $override;
      }
      // If we tried to load a language-sensitive file and failed, load the
      // domain-specific override.
      elseif (!is_null($langcode)) {
        $omit_language = TRUE;
        $config_name = $this->getDomainConfigUIManager()->getSelectedConfigName($name, $omit_language);
        if ($override = $this->storage->read($config_name)) {
          $overrides[$name] = $override;
        }
      }
    }
    return $overrides;
  }

}
