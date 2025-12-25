<?php

namespace Drupal\domain_config_ui;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\domain_config\DomainConfigOverrider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Domain Config UI manager.
 */
class DomainConfigUIManager implements DomainConfigUIManagerInterface {

  const DOMAIN_CONFIG_UI_DISALLOWED_ROUTES = [
    'domain_config_ui.settings',
    'domain.settings',
  ];

  const DOMAIN_CONFIG_UI_DISALLOWED_CONFIGURATIONS = [
    'domain_config_ui.settings',
    'domain.settings',
    'language.types',
  ];

  /**
   * A RequestStack instance.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Drupal\Core\Routing\AdminContext definition.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * Path current stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathCurrent;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * TRUE if the current page is an admin route.
   *
   * @var bool
   */
  protected $adminRoute;

  /**
   * TRUE if the current path is registered for domain configuration.
   *
   * @var bool
   */
  protected $pathRegistered;

  /**
   * The multi-line text containing the allowed paths.
   *
   * @var string
   */
  protected $pathPages;

  /**
   * List of route names that should not allow overrides.
   *
   * @var array|null
   */
  protected $disallowedRoutes = NULL;

  /**
   * List of configuration names that must not be overridden.
   *
   * @var array|null
   */
  protected $disallowedConfigurations = NULL;

  /**
   * Constructs DomainConfigUIManager object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match service.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The admin context.
   * @param \Drupal\Core\Path\CurrentPathStack $path_current
   *   The current path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    RequestStack $request_stack,
    CurrentRouteMatch $current_route_match,
    AdminContext $admin_context,
    CurrentPathStack $path_current,
    PathMatcherInterface $path_matcher,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $moduleHandler,
  ) {
    // We want the currentRequest, but it is not always available.
    // https://www.drupal.org/project/domain/issues/3004243#comment-13700917
    $this->requestStack = $request_stack;
    $this->currentRouteMatch = $current_route_match;
    $this->adminContext = $admin_context;
    $this->pathCurrent = $path_current;
    $this->pathMatcher = $path_matcher;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedConfigName($name, $omit_language = FALSE) {
    $domain_id = $this->getSelectedDomainId();

    if (!is_null($domain_id)) {
      if (!$omit_language && !empty($langcode = $this->getSelectedLanguageId())) {
        return DomainConfigOverrider::getConfigNameByDomainAndLanguage($name, $domain_id, $langcode);
      }
      return DomainConfigOverrider::getConfigNameByDomain($name, $domain_id);
    }

    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedDomainId() {
    $id = NULL;

    $request = $this->getRequest();
    if (!is_null($request)) {
      $id = $request->query->get('domain_config_ui_domain') ?? NULL;
    }
    // We check for nullity as an empty string means "All domains".
    if (is_null($id) && isset($_SESSION['domain_config_ui_domain'])) {
      $id = $_SESSION['domain_config_ui_domain'];
    }

    return $id;
  }

  /**
   * Check if a selected domain is available in request or session.
   */
  protected function hasSelectedDomainId() {
    return !empty($this->getSelectedDomainId());
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedLanguageId() {
    $id = NULL;

    $request = $this->getRequest();
    if (!is_null($request)) {
      $id = $request->query->get('domain_config_ui_language') ?? NULL;
    }
    // We check for nullity as an empty string means "Default".
    if (is_null($id) && isset($_SESSION['domain_config_ui_language'])) {
      $id = $_SESSION['domain_config_ui_language'];
    }

    return $id;
  }

  /**
   * Ensures that the currentRequest is loaded.
   *
   * @return \Symfony\Component\HttpFoundation\Request|null
   *   The current request object.
   */
  private function getRequest() {
    if (!isset($this->currentRequest)) {
      $this->currentRequest = $this->requestStack->getCurrentRequest();
    }

    return $this->currentRequest;
  }

  /**
   * Get the configured path pages.
   *
   * @return string
   *   The multi-line text containing the allowed paths.
   */
  public function getPathPages() {
    if (!isset($this->pathPages)) {
      $config = $this->configFactory->get('domain_config_ui.settings');
      $this->pathPages = $config->get('path_pages');
    }
    return $this->pathPages;
  }

  /**
   * {@inheritdoc}
   */
  public function isPathRegistered() {
    if (!isset($this->pathRegistered)) {
      $this->pathRegistered = $this->checkPathRegistered();
    }
    return $this->pathRegistered;
  }

  /**
   * Checks if the provided path is registered for domain configuration.
   *
   * @return bool
   *   TRUE if domain switch should be added. Otherwise, FALSE.
   */
  protected function checkPathRegistered() {
    $path_pages = $this->getPathPages();

    // Theme settings pass arguments, so check both path and route.
    $path = $this->pathCurrent->getPath();

    // Get the internal path without language prefix.
    $url = Url::fromUri('internal:' . $path);
    $internal_path = '/' . $url->getInternalPath();

    return $this->pathMatcher->matchPath($internal_path, $path_pages);
  }

  /**
   * {@inheritdoc}
   */
  public function isAdminRoute() {
    if (!isset($this->adminRoute)) {
      $admin_route = $this->checkAllowedRoute();
      if (is_bool($admin_route)) {
        $this->adminRoute = $admin_route;
      }
      else {
        // Route probably not yet available.
        return FALSE;
      }
    }
    return $this->adminRoute;
  }

  /**
   * Checks if the route is allowed and is an admin route.
   *
   * @return bool|null
   *   TRUE if the route is allowed, FALSE otherwise. NULL if undefined.
   */
  protected function checkAllowedRoute() {
    $route_name = $this->currentRouteMatch->getRouteName();
    if (is_null($route_name)) {
      return NULL;
    }
    if (!isset($this->disallowedRoutes)) {
      // Never allow this module's form to be added.
      $this->disallowedRoutes = self::DOMAIN_CONFIG_UI_DISALLOWED_ROUTES;
      // Allow modules to alter the list of disallowed routes.
      $this->moduleHandler->alter('domain_config_ui_disallowed_routes', $this->disallowedRoutes);
    }
    if (in_array($route_name, $this->disallowedRoutes, TRUE)) {
      return FALSE;
    }
    $route = $this->currentRouteMatch->getRouteObject();
    return $this->adminContext->isAdminRoute($route);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowedConfiguration($names):bool {
    if (!isset($this->disallowedConfigurations)) {
      // Never allow this module's settings to be added, for example.
      $this->disallowedConfigurations = static::DOMAIN_CONFIG_UI_DISALLOWED_CONFIGURATIONS;
      // Allow modules to alter the list of disallowed configurations.
      $this->moduleHandler->alter('domain_config_ui_disallowed_configurations', $this->disallowedConfigurations);
    }
    if (is_array($names)) {
      if (!empty(array_intersect($names, $this->disallowedConfigurations))) {
        return FALSE;
      }
    }
    else {
      if (in_array($names, $this->disallowedConfigurations, TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isPerDomainEditable($name) {
    return $this->hasSelectedDomainId()
      && $this->isAllowedConfiguration($name)
      && $this->isAdminRoute()
      && $this->isPathRegistered();
  }

}
