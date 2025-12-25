<?php

namespace Drupal\domain_menu_access_menu_block\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\menu_block\Plugin\Block\MenuBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Domain Access Menu (Menu) block.
 *
 * @Block(
 *   id = "domain_access_menu_menu_block",
 *   admin_label = @Translation("Domain Menu Block"),
 *   category = @Translation("Domain Menu (Menu Block)"),
 *   deriver =
 *   "Drupal\domain_menu_access\Plugin\Derivative\DomainMenuAccessMenuBlock"
 * )
 */
class DomainMenuAccessMenuMenuBlock extends MenuBlock implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new DomainMenuAccessMenuMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $menu_tree, $menu_active_trail);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = $this->getDerivativeId();

    // Fallback on default menu if not restricted by domain.
    if (!$this->isDomainRestricted($menu_name)) {
      return parent::build();
    }
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    // Adjust the menu tree parameters based on the block's configuration.
    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    // For blocks placed in Layout Builder or similar, check for the deprecated
    // 'expand' config property in case the menu block's configuration has not
    // yet been updated.
    $expand_all_items = $this->configuration['expand'] ?? $this->configuration['expand_all_items'];
    $parent = $this->configuration['parent'];
    $follow = $this->configuration['follow'];
    $follow_parent = $this->configuration['follow_parent'];
    $following = FALSE;

    $parameters->setMinDepth($level);

    // If we're following the active trail and the active trail is deeper than
    // the initial starting level, we update the level to match the active menu
    // item's level in the menu.
    if ($follow && count($parameters->activeTrail) > $level) {
      $level = count($parameters->activeTrail);
      $following = TRUE;
    }

    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    // If we're currently following an active menu item, or for menu blocks with
    // start level greater than 1, only show menu items from the current active
    // trail. Adjust the root according to the current position in the menu in
    // order to determine if we can show the subtree. If we're not following an
    // active trail and using a fixed parent item, we'll skip this step.
    $fixed_parent_menu_link_id = str_replace($menu_name . ':', '', $parent);
    if ($following || ($level > 1 && !$fixed_parent_menu_link_id)) {
      if (count($parameters->activeTrail) >= $level) {
        // Active trail array is child-first. Reverse it, and pull the new menu
        // root based on the parent of the configured start level.
        $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
        $offset = ($following && $follow_parent === 'active') ? 2 : 1;
        $menu_root = $menu_trail_ids[$level - $offset];
        $parameters->setRoot($menu_root)->setMinDepth(1);
        if ($depth > 0) {
          $parameters->setMaxDepth(min($depth, $this->menuTree->maxDepth()));
        }
      }
      else {
        return [];
      }
    }

    // If expandedParents is empty, the whole menu tree is built.
    if ($expand_all_items) {
      $parameters->expandedParents = [];
    }

    // When a fixed parent item is set, root the menu tree at the given ID.
    if ($fixed_parent_menu_link_id) {
      // Clone the parameters so we can fall back to using them if we're
      // following the active menu item and the current page is part of the
      // active menu trail.
      $fixed_parameters = clone $parameters;
      $fixed_parameters->setRoot($fixed_parent_menu_link_id);
      $tree = $this->menuTree->load($menu_name, $fixed_parameters);

      // Check if the tree contains links.
      if (count($tree) === 0) {
        // If the starting level is 1, we always want the child links to appear,
        // but the requested tree may be empty if the tree does not contain the
        // active trail. We're accessing the configuration directly since the
        // $level variable may have changed by this point.
        if ($this->configuration['level'] === 1 || $this->configuration['level'] === '1') {
          // Change the request to expand all children and limit the depth to
          // the immediate children of the root.
          $fixed_parameters->expandedParents = [];
          $fixed_parameters->setMinDepth(1);
          $fixed_parameters->setMaxDepth(1);
          // Re-load the tree.
          $tree = $this->menuTree->load($menu_name, $fixed_parameters);
        }
      }
      elseif ($following) {
        // If we're following the active menu item, and the tree isn't empty
        // (which indicates we're currently in the active trail), we unset
        // the tree we made and just let the active menu parameters from before
        // do their thing.
        unset($tree);
      }
    }

    // Load the tree if we haven't already.
    if (!isset($tree)) {
      $tree = $this->menuTree->load($menu_name, $parameters);
    }
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'domain_menu_access.default_tree_manipulators:checkDomain'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    // There are no menu items to display, so don't render the menu.
    if (!array_key_exists('#items', $build) || count($build['#items']) === 0) {
      return [];
    }

    // Specific menu_block configuration.
    $label = $this->getBlockLabel() ?? $this->label();
    // Set the block's #title (label) to the dynamic value.
    $build['#title'] = [
      '#markup' => $label,
    ];

    if (isset($build['#theme'])) {
      // Add the configuration for use in menu_block_theme_suggestions_menu().
      $build['#menu_block_configuration'] = $this->configuration;
      // Set the generated label into the configuration array so it is
      // propagated to the theme preprocessor and template(s) as needed.
      $build['#menu_block_configuration']['label'] = $label;
      // Remove the menu name-based suggestion so we can control its precedence
      // better in menu_block_theme_suggestions_menu().
      $build['#theme'] = 'menu';
    }

    $build['#contextual_links']['menu'] = [
      'route_parameters' => ['menu' => $menu_name],
    ];

    $build['#cache']['contexts'][] = 'route.menu_active_trails:' . $menu_name;

    return $build;
  }

  /**
   * Check if domain access has been enabled on this menu.
   *
   * @param string $menu_name
   *   Menu name.
   *
   * @return bool
   *   Config enabled.
   */
  protected function isDomainRestricted($menu_name) {
    $config = $this->configFactory->get('domain_menu_access.settings')
      ->get('menu_enabled');

    return in_array($menu_name, $config, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.site']);
  }

}
