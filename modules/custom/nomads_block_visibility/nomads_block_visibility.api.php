<?php

/**
 * @file
 * Hooks for the Nomads Block Visibility module.
 */

/**
 * Alter available Nomads-specific block visibility rules.
 *
 * These rules are rendered as checkboxes inside the "Nomads Tweaks" block
 * visibility condition plugin.
 *
 * Each rule definition should be keyed by a machine name and contain:
 * - label: Human-readable checkbox label.
 * - callback: Callable receiving ($rule_id, $definition, $condition) and
 *   returning a boolean outcome.
 *
 * Optional keys:
 * - description: Help text shown in block configuration.
 * - default: Whether the checkbox should be enabled by default.
 * - cache_contexts: Cache contexts required by the rule.
 * - cache_tags: Cache tags required by the rule.
 * - cache_max_age: Cache max-age required by the rule.
 *
 * @param array $definitions
 *   Rule definitions keyed by rule ID.
 */
function hook_nomads_block_visibility_rules_alter(array &$definitions): void {
  $definitions['hide_on_admin_routes'] = [
    'label' => t('Hide on admin pages'),
    'description' => t('Prevents the block from being shown on administrative routes.'),
    'callback' => 'mymodule_nomads_block_visibility_hide_on_admin_routes',
    'cache_contexts' => ['route'],
  ];
}

/**
 * Example callback for a custom visibility rule.
 */
function mymodule_nomads_block_visibility_hide_on_admin_routes(string $rule_id, array $definition, object $condition): bool {
  return !\Drupal::service('router.admin_context')->isAdminRoute();
}
