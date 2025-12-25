<?php

namespace Drupal\currency\Plugin\Currency\AmountFormatter;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines an amount formatter plugin manager.
 */
interface AmountFormatterManagerInterface extends PluginManagerInterface {

  /**
   * Gets the default plugin ID.
   *
   * @return string
   *   The default plugin ID.
   */
  public function getDefaultPluginId();

  /**
   * Sets the default plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID to be set as default.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setDefaultPluginId($plugin_id);

  /**
   * Gets the default formatter.
   *
   * @return \Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterInterface
   *   The default amount formatter plugin instance.
   */
  public function getDefaultPlugin();

  /**
   * Creates an amount formatter.
   *
   * @param string $plugin_id
   *   The id of the plugin being instantiated.
   * @param mixed[] $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterInterface
   *   The instantiated amount formatter plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []);

}
