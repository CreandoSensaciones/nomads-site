<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_config\DomainConfigOverrider;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Resolves domain roots for landing pages configured as domain front pages.
 */
final class FrontpageDomainResolver {

  /**
   * Per-request cache of resolved root URLs by node ID.
   *
   * @var array<int, string|null>
   */
  private array $rootUrlByNodeId = [];

  /**
   * Cache tags touched while resolving domain front-page config.
   *
   * @var string[]
   */
  private array $cacheTags = ['config:domain_list'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StorageInterface $configStorage,
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * Returns the first matching domain root URL for the node, if any.
   */
  public function resolveRootUrl(NodeInterface $node): ?string {
    $node_id = (int) $node->id();
    if (array_key_exists($node_id, $this->rootUrlByNodeId)) {
      return $this->rootUrlByNodeId[$node_id];
    }

    $target_path = '/node/' . $node_id;

    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = $this->entityTypeManager->getStorage('domain');
    foreach ($domain_storage->loadMultipleSorted() as $domain) {
      if (!$domain instanceof DomainInterface) {
        continue;
      }

      foreach ($this->getSystemSiteConfigNames($domain) as $config_name) {
        $this->cacheTags[] = 'config:' . $config_name;
        $config = $this->configStorage->read($config_name);
        if (!is_array($config)) {
          continue;
        }

        $front_path = $config['page']['front'] ?? NULL;
        if ($this->frontPathMatchesNode($front_path, $target_path)) {
          return $this->rootUrlByNodeId[$node_id] = rtrim($domain->getPath(), '/') . '/';
        }
      }
    }

    return $this->rootUrlByNodeId[$node_id] = NULL;
  }

  /**
   * Returns cache tags for config inspected during this request.
   *
   * @return string[]
   *   Cache tags.
   */
  public function getCacheTags(): array {
    return array_values(array_unique($this->cacheTags));
  }

  /**
   * Returns domain-specific system.site config names in lookup order.
   *
   * @return string[]
   *   Config names.
   */
  private function getSystemSiteConfigNames(DomainInterface $domain): array {
    $domain_id = (string) $domain->id();
    $names = [
      DomainConfigOverrider::getConfigNameByDomain('system.site', $domain_id),
    ];

    $prefix = 'domain.config.' . $domain_id . '.';
    foreach ($this->configStorage->listAll($prefix) as $config_name) {
      if (str_ends_with($config_name, '.system.site')) {
        $names[] = $config_name;
      }
    }

    return array_values(array_unique($names));
  }

  /**
   * Checks whether a configured front path resolves to the node path.
   */
  private function frontPathMatchesNode(mixed $front_path, string $target_path): bool {
    if (!is_string($front_path) || $front_path === '') {
      return FALSE;
    }

    $normalized = $this->normalizePath($front_path);
    if ($normalized === $target_path) {
      return TRUE;
    }

    return $this->normalizePath($this->aliasManager->getPathByAlias($normalized)) === $target_path;
  }

  /**
   * Normalizes a configured Drupal path for comparison.
   */
  private function normalizePath(string $path): string {
    $path = trim($path);
    $path = strtok($path, '?#') ?: $path;
    $path = '/' . ltrim($path, '/');

    return $path === '/' ? $path : rtrim($path, '/');
  }

}
