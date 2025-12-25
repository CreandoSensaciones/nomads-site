<?php

namespace Drupal\domain_source\HttpKernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the outbound path using route match lookups.
 */
class DomainSourcePathProcessor implements OutboundPathProcessorInterface {

  use LoggerChannelTrait;

  /**
   * The Domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $negotiator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An array of content entity types.
   *
   * @var array
   */
  protected $entityTypes;

  /**
   * An array of routes exclusion settings, keyed by route.
   *
   * @var array
   */
  protected $excludedRoutes;

  /**
   * The active domain request.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $activeDomain;

  /**
   * The domain storage.
   *
   * @var \Drupal\domain\DomainStorageInterface|null
   */
  protected $domainStorage;

  /**
   * Constructs a DomainSourcePathProcessor object.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $negotiator
   *   The domain negotiator.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel factory.
   */
  public function __construct(DomainNegotiatorInterface $negotiator, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_channel_factory) {
    $this->negotiator = $negotiator;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->setLoggerFactory($logger_channel_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // Load the active domain if not set.
    $options['active_domain'] = $options['active_domain'] ?? $this->getActiveDomain();

    // Process only non-empty internal paths with an available active domain.
    if (!$options['active_domain'] instanceof DomainInterface || empty($path) || !empty($options['external'])) {
      return $path;
    }

    // Check if the path is allowed, skip processing otherwise.
    if (!$this->allowedPath($path)) {
      return $path;
    }

    // Extract the route name and parameters from the path using an
    // in-house route matcher until the following core issue is fixed:
    // https://www.drupal.org/project/drupal/issues/3202329
    if (!isset($options['route_name'])) {
      if ($route_info = DomainSourceRouteMatcher::routeMatch($path)) {
        if (isset($route_info['_route'])) {
          $options['route_name'] = $route_info['_route'];
          $options['route_parameters'] = $route_info['_raw_variables'] ?? [];
        }
      }
    }

    // Check the route, if available. Entities can be configured to
    // only rewrite specific routes.
    if (isset($options['route_name']) && !$this->allowedRoute($options['route_name'])) {
      return $path;
    }

    if (isset($options['entity'])) {
      $entity = $options['entity'];
    }
    elseif (isset($options['route_name'])) {
      $entity = $this->getEntity($options['route_parameters']);
    }
    else {
      $entity = NULL;
    }

    $source = NULL;
    // One hook for entities.
    if ($entity instanceof EntityInterface) {
      // Get the current language.
      if (isset($options['language']) && $options['language'] instanceof LanguageInterface) {
        $langcode = $options['language']->getId();
        // Ensure we send the right translation.
        if (
          $entity->getEntityType()->isTranslatable()
          && $entity instanceof TranslatableInterface
          && $entity->hasTranslation($langcode)
          && $translation = $entity->getTranslation($langcode)
        ) {
          $entity = $translation;
        }
      }
      if (isset($options['domain_target_id'])) {
        $target_id = $options['domain_target_id'];
      }
      else {
        $target_id = domain_source_get($entity);
      }
      if (!is_null($target_id)) {
        $source = $this->domainStorage()->load($target_id);
      }
      $options['entity'] = $entity;
      $options['entity_type'] = $entity->getEntityTypeId();
      $this->moduleHandler->alter('domain_source', $source, $path, $options);
    }
    // One for other, because the latter is resource-intensive.
    else {
      if (isset($options['domain_target_id'])) {
        $target_id = $options['domain_target_id'];
        $source = $this->domainStorage()->load($target_id);
      }
      $this->moduleHandler->alter('domain_source_path', $source, $path, $options);
    }

    // If a source domain is specified and does not match the active domain,
    // rewrite the link.
    if (
      $source instanceof DomainInterface
      && $source->getDomainId() !== $options['active_domain']->getDomainId()
    ) {
      // Note that url rewrites add a leading /, which getPath() also adds.
      $options['base_url'] = rtrim($source->getPath(), '/');
      $options['absolute'] = TRUE;
    }

    return $path;
  }

  /**
   * Derive entity data from a given route's parameters.
   *
   * @param array $parameters
   *   An array of route parameters.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns the entity when available, otherwise NULL.
   */
  public function getEntity(array $parameters) {
    // Loop protection.
    static $depth = 0;
    $entity = NULL;
    // The max depth of 1 could be increased if needed.
    if ($depth < 1) {
      $depth++;
      try {
        $entity_types = $this->getEntityTypes();
        foreach ($parameters as $entity_type => $value) {
          if (isset($entity_types[$entity_type])) {
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($value);
            break;
          }
        }
        $depth--;
      }
      catch (\Exception $e) {
        $depth--;
        throw $e;
      }
    }
    return $entity;
  }

  /**
   * Checks that a path is allowed.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path is allowed, FALSE otherwise.
   *
   * @see https://www.drupal.org/project/domain/issues/3544347
   */
  protected function allowedPath($path) {
    return TRUE;
  }

  /**
   * Checks that a route's common name is not disallowed.
   *
   * Looks at the name (e.g. canonical) of the route without regard for
   * the entity type.
   *
   * @parameter $name
   *   The route name being checked.
   *
   * @return bool
   *   Returns TRUE when allowed, otherwise FALSE.
   */
  public function allowedRoute($name) {
    $excluded = $this->getExcludedRoutes();
    $parts = explode('.', $name);
    $route_name = end($parts);
    // Config is stored as an array. Empty items are not excluded.
    return !isset($excluded[$route_name]);
  }

  /**
   * Gets an array of content entity types, keyed by type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of content entity types, keyed by type.
   */
  public function getEntityTypes() {
    if (!isset($this->entityTypes)) {
      foreach ($this->entityTypeManager->getDefinitions() as $type => $definition) {
        if ($definition->getGroup() === 'content') {
          $this->entityTypes[$type] = $type;
        }
      }
    }
    return $this->entityTypes;
  }

  /**
   * Gets the settings for domain source path rewrites.
   *
   * @return array
   *   The settings for domain source path rewrites.
   */
  public function getExcludedRoutes() {
    if (!isset($this->excludedRoutes)) {
      $config = $this->configFactory->get('domain_source.settings');
      $routes = $config->get('exclude_routes');
      if (is_array($routes)) {
        $this->excludedRoutes = array_flip($routes);
      }
      else {
        $this->excludedRoutes = [];
      }
    }
    return $this->excludedRoutes;
  }

  /**
   * Gets the active domain.
   *
   * @return \Drupal\domain\DomainInterface
   *   The active domain.
   */
  public function getActiveDomain() {
    if (!isset($this->activeDomain)) {
      $this->activeDomain = $this->negotiator->getActiveDomain();
    }
    return $this->activeDomain;
  }

  /**
   * Retrieves the domain storage handler.
   *
   * @return \Drupal\domain\DomainStorageInterface
   *   The domain storage handler.
   */
  protected function domainStorage() {
    if (is_null($this->domainStorage)) {
      $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    }

    return $this->domainStorage;
  }

  /**
   * Gets the domain_source logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for the domain_source channel.
   */
  protected function logger() {
    return $this->getLogger('domain_source');
  }

}
