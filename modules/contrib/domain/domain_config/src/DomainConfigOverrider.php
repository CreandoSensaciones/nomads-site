<?php

namespace Drupal\domain_config;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\domain\DomainInterface;

/**
 * Domain-specific config overrides.
 *
 * @see \Drupal\language\Config\LanguageConfigFactoryOverride for ways
 * this might be improved.
 */
class DomainConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * The cache suffix for when no domain or no configurations are available.
   */
  protected const DOMAIN_NONE_CACHE_SUFFIX = 'domain-none';

  /**
   * The domain config override name prefix.
   */
  protected const DOMAIN_CONFIG_PREFIX = 'domain.config.';

  /**
   * Cache of previously looked up configuration overrides.
   *
   * Stores configuration override results keyed by a hash of config names
   * and language ID to prevent repeating expensive lookup operations.
   * The array format is:
   * - key: MD5 hash of imploded config names concatenated with language ID
   * - value: Array of configuration overrides for those names.
   *
   * @var array
   */
  protected static $overridesCache = [];

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * A storage controller instance for reading and writing configuration data.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The domain context of the request.
   *
   * @var \Drupal\domain\DomainInterface|bool|null
   */
  protected $domain = NULL;

  /**
   * The language context of the request.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * Drupal language manager.
   *
   * Using dependency injection for this service causes a circular dependency.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Indicates that the request context is set.
   *
   * @var bool|null
   */
  protected $contextSet = NULL;

  /**
   * List of domain-specific configuration names.
   *
   * @var array
   */
  protected $domainConfigs;

  /**
   * Indicates that some configuration overrides are available.
   *
   * @var bool
   */
  protected $hasOverrides;

  /**
   * The cache suffix for this overrider.
   *
   * @var string|null
   */
  protected $cacheSuffix = NULL;

  /**
   * Constructs a DomainConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The configuration storage engine.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(StorageInterface $storage, ModuleHandlerInterface $module_handler) {
    $this->storage = $storage;
    $this->moduleHandler = $module_handler;
    $this->initialize();
  }

  /**
   * Initializes the domain configuration overrider state.
   */
  protected function initialize() {
    // Check if domain configs are available and if there are any overrides
    // in settings.php. If not, we can skip the overrides.
    // See https://www.drupal.org/project/domain/issues/3126532.
    $this->domainConfigs = $this->storage->listAll(static::DOMAIN_CONFIG_PREFIX);
    $this->hasOverrides = !empty($this->domainConfigs);
    if (!$this->hasOverrides) {
      // Check if we have some domain config overrides in settings.php.
      foreach ($GLOBALS['config'] as $config_key => $_) {
        if (str_starts_with($config_key, static::DOMAIN_CONFIG_PREFIX)) {
          // A domain config override has been found in settings.php.
          $this->hasOverrides = TRUE;
          break;
        }
      }
    }
    // If no overrides are available, disable overriding.
    if (!$this->hasOverrides) {
      $this->domain = FALSE;
      $this->contextSet = FALSE;
      $this->cacheSuffix = self::DOMAIN_NONE_CACHE_SUFFIX;
    }
  }

  /**
   * Reset the override cache and context.
   *
   * Used mainly for testing.
   */
  public function reset() {
    // Reset the cache.
    static::$overridesCache = [];
    // Check for configuration overrides updates.
    $this->initialize();
    if ($this->hasOverrides) {
      // Reset the context.
      $this->domain = NULL;
      $this->contextSet = NULL;
      $this->cacheSuffix = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    if (!$this->hasOverrides) {
      // No configuration overrides are available.
      return [];
    }
    elseif (!$this->initiateContext()) {
      // Context not initialized, we cannot load overrides yet.
      return [];
    }
    else {
      // Try to prevent repeating lookups.
      // Key should be a known length, so hash.
      // We add the language as a suffix as it can change after negotiation.
      $key = md5(implode(':', $names) . ':' . $this->language->getId());
      if (isset(static::$overridesCache[$key])) {
        return static::$overridesCache[$key];
      }

      // Prepare our overrides.
      $overrides = [];
      // loadOverrides() runs on config entities, which means that if we try
      // to run this routine on our own data, we end up in an infinite loop.
      // So ensure that we are _not_ looking up a domain.record.*.
      // We also skip overriding the domain.settings config.
      if ($this->isInternalName(current($names))) {
        static::$overridesCache[$key] = $overrides;
        return $overrides;
      }

      if ($this->isDomainAvailable()) {
        foreach ($names as $name) {
          $config_names = $this->getDomainConfigNames($name);
          // Check to see if the config storage has an appropriately named file
          // containing override data.
          if (in_array($config_names['langcode'], $this->domainConfigs, TRUE)
            && ($override = $this->storage->read($config_names['langcode']))
          ) {
            $overrides[$name] = $override;
          }
          // Check to see if we have a file without a specific language.
          elseif (in_array($config_names['domain'], $this->domainConfigs, TRUE)
            && ($override = $this->storage->read($config_names['domain']))
          ) {
            $overrides[$name] = $override;
          }
          // Apply any existing settings.php language overrides.
          if (isset($GLOBALS['config'][$config_names['langcode']])) {
            if (isset($overrides[$name])) {
              $overrides[$name] = NestedArray::mergeDeepArray(
                [
                  $overrides[$name],
                  $GLOBALS['config'][$config_names['langcode']],
                ], TRUE
              );
            }
            else {
              $overrides[$name] = $GLOBALS['config'][$config_names['langcode']];
            }
          }
          // Apply any existing settings.php language agnostic overrides.
          elseif (isset($GLOBALS['config'][$config_names['domain']])) {
            if (isset($overrides[$name])) {
              $overrides[$name] = NestedArray::mergeDeepArray(
                [
                  $overrides[$name],
                  $GLOBALS['config'][$config_names['domain']],
                ], TRUE
              );
            }
            else {
              $overrides[$name] = $GLOBALS['config'][$config_names['domain']];
            }
          }
        }
        static::$overridesCache[$key] = $overrides;
      }
      else {
        if ($this->domain === FALSE) {
          // No domain exists, so we can safely cache the empty overrides.
          static::$overridesCache[$key] = $overrides;
        }
      }

      return $overrides;
    }
  }

  /**
   * Get configuration names for current domain.
   *
   * @param string $name
   *   The name of the config object.
   *
   * @return array
   *   The domain-language, and domain-specific config names.
   */
  protected function getDomainConfigNames($name) {
    return static::getConfigNamesByDomainAndLanguage($name, $this->domain->id(), $this->language->getId());
  }

  /**
   * Get configuration names for a specific domain and language.
   *
   * @param string $name
   *   The name of the config object.
   * @param string $domain_id
   *   The id of the domain for which to get the config name.
   * @param string $langcode
   *   The language for which to get the config name.
   *
   * @return array
   *   The domain-language, and domain-specific config names.
   */
  public static function getConfigNamesByDomainAndLanguage($name, string $domain_id, string $langcode) {
    return [
      'langcode' => static::getConfigNameByDomainAndLanguage($name, $domain_id, $langcode),
      'domain' => static::getConfigNameByDomain($name, $domain_id),
    ];
  }

  /**
   * Get the configuration name for a specific domain.
   *
   * @param string $name
   *   The name of the config object.
   * @param string $domain_id
   *   The id of the domain for which to get the config name.
   *
   * @return string
   *   The domain-specific config name.
   */
  public static function getConfigNameByDomain(string $name, string $domain_id): string {
    return static::DOMAIN_CONFIG_PREFIX . $domain_id . '.' . $name;
  }

  /**
   * Get the configuration name for a specific domain and language.
   *
   * @param string $name
   *   The name of the config object.
   * @param string $domain_id
   *   The id of the domain for which to get the config name.
   * @param string $langcode
   *   The language for which to get the config name.
   *
   * @return string
   *   The domain-language config name.
   */
  public static function getConfigNameByDomainAndLanguage(string $name, string $domain_id, string $langcode): string {
    return static::DOMAIN_CONFIG_PREFIX . $domain_id . '.' . $langcode . '.' . $name;
  }

  /**
   * Get configuration name for this hostname.
   *
   * It will be the same name with a prefix depending on domain and language:
   *
   * `domain.config.DOMAIN_ID.LANGCODE`
   *
   * @param string $name
   *   The name of the config object.
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain for which to get the config name.
   *
   * @return array
   *   The domain-language, and domain-specific config names.
   *
   * @deprecated in domain:2.0.0 and is removed from domain:2.1.0. Unused.
   *
   * @see https://www.drupal.org/project/domain/issues/3543535
   */
  protected function getDomainConfigName($name, DomainInterface $domain) {
    return static::getConfigNamesByDomainAndLanguage($name, $domain->id(), $this->language->getId());
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\language\Config\LanguageConfigFactoryOverride::setLanguage()
   * @see \Drupal\language\EventSubscriber\LanguageRequestSubscriber::setLanguageOverrides()
   */
  public function getCacheSuffix() {
    if (isset($this->cacheSuffix)) {
      return $this->cacheSuffix;
    }
    elseif ($this->hasOverrides && $this->isDomainAvailable()) {
      $this->cacheSuffix = $this->domain->id();
      // We still need to add the language suffix here as the
      // LanguageConfigFactoryOverride service, which adds the current language
      // to the cache suffix, is taking some time to get updated after language
      // negotiation via an EventSubscriber (check phpdoc above).
      if ($this->language instanceof LanguageInterface) {
        $this->cacheSuffix .= '+' . $this->language->getId();
      }
      return $this->cacheSuffix;
    }
    return self::DOMAIN_NONE_CACHE_SUFFIX;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    $metadata = new CacheableMetadata();
    if ($this->hasOverrides && $this->isDomainAvailable()) {
      $config_names = $this->getDomainConfigNames($name);
      if (in_array($config_names['domain'], $this->domainConfigs, TRUE)) {
        $metadata->addCacheContexts(['url.site']);
        $metadata->addCacheTags(['config:' . $config_names['domain']]);
      }
      if (in_array($config_names['langcode'], $this->domainConfigs, TRUE)) {
        $metadata->addCacheContexts(['url.site', 'languages:language_interface']);
        $metadata->addCacheTags(['config:' . $config_names['langcode']]);
      }
    }
    return $metadata;
  }

  /**
   * Explicitly sets the domain.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to use for overrides.
   *
   * @see https://www.drupal.org/project/domain/issues/3385946
   */
  public function setDomain(DomainInterface $domain) {
    $this->domain = $domain;
  }

  /**
   * Set the language after negotiation.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The negotiated language.
   *
   * @see \Drupal\domain_config\EventSubscriber\DomainConfigSubscriber::onKernelRequest()
   */
  public function setLanguage(LanguageInterface $language) {
    $this->language = $language;
    // We need to reset the cache suffix as it is language dependent.
    $this->cacheSuffix = NULL;
    $this->getCacheSuffix();
  }

  /**
   * Initialize domain and language contexts for the request.
   *
   * We wait to do this in order to avoid circular dependencies
   * with the locale module.
   */
  protected function initiateContext() {
    // Initialize the context only once per request.
    if ($this->contextSet === NULL) {
      // Initialize the context value to avoid reentrancy.
      $this->contextSet = FALSE;

      // We must ensure that modules have loaded, which they may not have.
      // See https://www.drupal.org/project/domain/issues/3025541.
      $this->moduleHandler->loadAll();

      // Get the language context. Note that injecting the language manager
      // into the service created a circular dependency error, so we load from
      // the core service manager.
      // @phpstan-ignore-next-line
      $this->languageManager = \Drupal::languageManager();
      // Get the current language. At this step, the language negotiation might
      // not have been done yet, so it will return the site default language.
      // An EventSubscriber will later change this to the negotiated language.
      // @see \Drupal\domain_config\EventSubscriber\LanguageNegotiationSubscriber::onKernelRequest()
      $this->language = $this->languageManager->getCurrentLanguage();

      // The same issue is true for the domainNegotiator.
      // @phpstan-ignore-next-line
      $this->domainNegotiator = \Drupal::service('domain.negotiator');
      // Start the domain negotiation process.
      $this->domain = $this->domainNegotiator->getActiveDomain();

      $this->contextSet = TRUE;
    }
    return $this->contextSet;
  }

  /**
   * Determines if an active domain is available for this request.
   */
  protected function isDomainAvailable() {
    if ($this->domain instanceof DomainInterface) {
      // If we already have a domain, we can skip the negotiation.
      return TRUE;
    }
    if ($this->domain === FALSE) {
      // If we have already determined that no domain is available, we can skip
      // the negotiation.
      return FALSE;
    }
    if ($this->initiateContext() && $this->domainNegotiator->isNegotiated()) {
      // Get the negotiated domain for this request. Negotiation was started in
      // the initiateContext() method.  $domain can still be NULL if no domain
      // is available or exists.  We set it to FALSE in that case.
      $domain = $this->domainNegotiator->getActiveDomain();
      $this->domain = ($domain instanceof DomainInterface) ? $domain : FALSE;
      return $this->domain !== FALSE;
    }
    return FALSE;
  }

  /**
   * Checks if the given config name is an internal domain config.
   *
   * Internal configs are `domain.record.*` or `domain.settings`.
   * These configs are not meant to be overridden.
   *
   * @param string $name
   *   The config name to check.
   *
   * @return bool
   *   TRUE if the config is internal, FALSE otherwise.
   */
  protected function isInternalName(string $name) {
    $parts = explode('.', $name);
    return (isset($parts[0], $parts[1]) && $parts[0] === 'domain' && in_array($parts[1], ['record', 'settings'], TRUE));
  }

}
