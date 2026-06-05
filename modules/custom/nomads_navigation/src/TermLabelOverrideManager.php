<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation;

use Drupal\Core\Config\StorageInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain_config\DomainConfigOverrider;
use Drupal\taxonomy\TermInterface;

/**
 * Provides domain-specific term label overrides for navigation UI.
 */
final class TermLabelOverrideManager {

  /**
   * Static cache of overrides by config name.
   *
   * @var array<string, array<int, string>>
   */
  private array $overridesByConfigName = [];

  /**
   * Constructs a TermLabelOverrideManager object.
   */
  public function __construct(
    private readonly StorageInterface $configStorage,
    private readonly DomainNegotiatorInterface $domainNegotiator,
  ) {}

  /**
   * Gets the navigation label for a term.
   */
  public function getLabel(TermInterface $term): string {
    $term_id = (int) $term->id();
    $overrides = $this->getOverrides();

    return $overrides[$term_id] ?? $term->label();
  }

  /**
   * Gets config cache tags used by the active override lookup.
   *
   * @return string[]
   *   Cache tags.
   */
  public function getCacheTags(): array {
    return array_map(
      static fn (string $config_name): string => 'config:' . $config_name,
      $this->getConfigNames(),
    );
  }

  /**
   * Gets cache contexts required for domain-specific overrides.
   *
   * @return string[]
   *   Cache contexts.
   */
  public function getCacheContexts(): array {
    return ['url.site', 'domain'];
  }

  /**
   * Gets the active domain-specific override map.
   *
   * @return array<int, string>
   *   Labels keyed by term ID.
   */
  private function getOverrides(): array {
    foreach ($this->getConfigNames() as $config_name) {
      if (array_key_exists($config_name, $this->overridesByConfigName)) {
        return $this->overridesByConfigName[$config_name];
      }

      $data = $this->configStorage->read($config_name);
      if (!is_array($data) || !array_key_exists('term_label_overrides', $data)) {
        continue;
      }

      $this->overridesByConfigName[$config_name] = $this->normalizeOverrides($data['term_label_overrides']);
      return $this->overridesByConfigName[$config_name];
    }

    return [];
  }

  /**
   * Gets config names in domain override order.
   *
   * @return string[]
   *   Config object names.
   */
  private function getConfigNames(): array {
    $config_names = [];
    $domain = $this->domainNegotiator->getActiveDomain();
    if ($domain && method_exists($domain, 'id')) {
      $domain_id = (string) $domain->id();
      $config_names[] = DomainConfigOverrider::getConfigNameByDomain('nomads_navigation.settings', $domain_id);
    }

    $config_names[] = 'nomads_navigation.settings';

    return array_values(array_unique($config_names));
  }

  /**
   * Normalizes stored override config.
   *
   * @return array<int, string>
   *   Labels keyed by term ID.
   */
  private function normalizeOverrides(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $overrides = [];
    foreach ($value as $term_id => $label) {
      $term_id = (int) $term_id;
      $label = trim((string) $label);
      if ($term_id > 0 && $label !== '') {
        $overrides[$term_id] = $label;
      }
    }

    return $overrides;
  }

}
