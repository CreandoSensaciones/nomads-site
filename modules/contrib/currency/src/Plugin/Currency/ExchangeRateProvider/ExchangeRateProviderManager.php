<?php

namespace Drupal\currency\Plugin\Currency\ExchangeRateProvider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\currency\Annotation\CurrencyExchangeRateProvider;
use Drupal\plugin\PluginDefinition\PluginOperationsProviderDefinitionInterface;

/**
 * Manages currency exchange rate provider plugins.
 *
 * @see \Drupal\block\BlockInterface
 */
class ExchangeRateProviderManager extends DefaultPluginManager implements ExchangeRateProviderManagerInterface {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'description' => NULL,
  ];

  /**
   * The class resolver service.
   */
  protected ClassResolverInterface $classResolver;

  /**
   * Constructs a new instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver) {
    parent::__construct('Plugin/Currency/ExchangeRateProvider', $namespaces, $module_handler, ExchangeRateProviderInterface::class, CurrencyExchangeRateProvider::class);
    $this->alterInfo('currency_exchange_rate_provider');
    $this->setCacheBackend($cache_backend, 'currency_exchange_rate_provider');
    $this->classResolver = $class_resolver;
  }

  /**
   * Returns the operations for a plugin definition.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return array|null
   *   The operations, or NULL if not available.
   */
  public function getOperations($plugin_id) {
    $definition = $this->getDefinition($plugin_id);

    if ($definition instanceof PluginOperationsProviderDefinitionInterface) {
      $provider_class = $definition->getOperationsProviderClass();
      $provider = $this->classResolver->getInstanceFromDefinition($provider_class);
      return $provider->getOperations($plugin_id);
    }

    return NULL;
  }

  /**
   * Returns the operations provider for the given plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return object|null
   *   The operations provider object or NULL if not available.
   */
  public function getOperationsProvider($plugin_id) {
    $definition = $this->getDefinition($plugin_id);

    if ($definition instanceof PluginOperationsProviderDefinitionInterface) {
      $provider_class = $definition->getOperationsProviderClass();
      return $this->classResolver->getInstanceFromDefinition($provider_class);
    }

    return NULL;
  }

}
