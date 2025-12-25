<?php

/**
 * @file
 * Callbacks and hooks for the domain_config_ui module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of disallowed routes for the domain_config_ui.
 *
 * @param string[] $disallowed
 *   An array of disallowed route names.
 */
function hook_domain_config_ui_disallowed_routes_alter(&$disallowed) {
  // Add the 'my_custom_route_to_disallow' route to the disallowed list.
  $disallowed[] = 'my_custom_route_to_disallow';
}

/**
 * @} End of "addtogroup hooks".
 */
