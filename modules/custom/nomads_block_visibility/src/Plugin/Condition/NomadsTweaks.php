<?php

declare(strict_types=1);

namespace Drupal\nomads_block_visibility\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a Nomads-specific block visibility condition.
 */
#[Condition(
  id: "nomads_tweaks",
  label: new TranslatableMarkup("Nomads Tweaks"),
)]
final class NomadsTweaks extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'enabled_rules' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['negate']);

    $definitions = nomads_block_visibility_get_rule_definitions();
    if ($definitions === []) {
      $form['empty'] = [
        '#type' => 'item',
        '#markup' => $this->t('No Nomads visibility rules are available yet.'),
      ];
      return $form;
    }

    $form['rules'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $enabled_rules = nomads_block_visibility_get_enabled_rules($this->configuration);

    foreach ($definitions as $rule_id => $definition) {
      $is_enabled = in_array($rule_id, $enabled_rules, TRUE) || (!in_array($rule_id, $enabled_rules, TRUE) && !empty($definition['default']));
      $form['rules'][$rule_id] = [
        '#type' => 'checkbox',
        '#title' => $definition['label'],
        '#description' => $definition['description'],
        '#default_value' => $is_enabled,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $submitted = $form_state->getValue('rules', []);
    $submitted = is_array($submitted) ? $submitted : [];

    $enabled_rules = [];
    foreach (nomads_block_visibility_get_rule_definitions() as $rule_id => $definition) {
      if (!empty($submitted[$rule_id])) {
        $enabled_rules[] = $rule_id;
      }
    }

    $this->configuration['enabled_rules'] = $enabled_rules;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    return (string) nomads_block_visibility_build_summary($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $enabled_rules = nomads_block_visibility_get_enabled_rules($this->configuration);
    if ($enabled_rules === []) {
      return TRUE;
    }

    foreach ($enabled_rules as $rule_id) {
      if (!nomads_block_visibility_evaluate_rule($rule_id, $this->configuration, $this)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_values(array_unique(array_merge(
      parent::getCacheContexts(),
      nomads_block_visibility_get_cache_contexts($this->configuration),
    )));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_values(array_unique(array_merge(
      parent::getCacheTags(),
      nomads_block_visibility_get_cache_tags($this->configuration),
    )));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return \Drupal\Core\Cache\Cache::mergeMaxAges(
      parent::getCacheMaxAge(),
      nomads_block_visibility_get_cache_max_age($this->configuration),
    );
  }

}
