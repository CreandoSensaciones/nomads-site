<?php

namespace Drupal\domain_config_ui;

/**
 * Domain Config UI manager.
 */
interface DomainConfigUIManagerInterface {

  /**
   * Get selected config name.
   *
   * @param string $name
   *   The config name.
   * @param bool $omit_language
   *   A flag to indicate if the language-sensitive config should be loaded.
   *
   * @return string
   *   A config object name.
   */
  public function getSelectedConfigName($name, $omit_language = FALSE);

  /**
   * Get the selected domain ID.
   *
   * @return string|null
   *   A domain machine name.
   */
  public function getSelectedDomainId();

  /**
   * Get the selected language ID.
   *
   * @return string|null
   *   A language code.
   */
  public function getSelectedLanguageId();

  /**
   * Checks if the provided path is registered for domain configuration.
   *
   * @return bool
   *   TRUE if domain switch should be added. Otherwise, FALSE.
   */
  public function isPathRegistered();

  /**
   * Checks if route is admin.
   *
   * @return bool
   *   TRUE if route is admin. Otherwise, FALSE.
   */
  public function isAdminRoute();

  /**
   * Checks if the current route and path are domain-configurable.
   *
   * @param string|array $name
   *   The config name.
   *
   * @return bool
   *   TRUE if domain-configurable, false otherwise.
   */
  public function isPerDomainEditable($name);

  /**
   * Check that a specific config can be edited per domain.
   *
   * @param string|array $names
   *   The config name.
   *
   * @return bool
   *   TRUE if it can be edited by domain, FALSE otherwise.
   */
  public function isAllowedConfiguration($names):bool;

}
