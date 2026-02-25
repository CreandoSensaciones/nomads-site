<?php

namespace Drupal\nomads_term_dependees;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds and caches taxonomy constraint maps for selected vocabularies.
 */
class ConstraintMapBuilder {

  /**
   * Cache id for the constraint map.
   */
  private const CACHE_ID = 'nomads_term_dependees.constraint_map';

  /**
   * Vocabularies that hold constraint fields.
   */
  private const VOCABULARIES = ['t', 'type'];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Persistent cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * Constructs the map builder.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cache = $cache;
  }

  /**
   * Get dependee and no-combine maps.
   */
  public function getConstraintMap(): array {
    $cached = $this->cache->get(self::CACHE_ID);
    if ($cached && is_array($cached->data)) {
      return $cached->data;
    }

    $map = [
      'dependee' => $this->buildDependeeMap(),
      'no_combine' => $this->buildNoCombineMap(),
    ];

    $this->cache->set(
      self::CACHE_ID,
      $map,
      Cache::PERMANENT,
      [
        'taxonomy_term_list:t',
        'taxonomy_term_list:type',
      ],
    );

    return $map;
  }

  /**
   * Build dependee map keyed by controller term id.
   */
  protected function buildDependeeMap(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', self::VOCABULARIES, 'IN')
      ->condition('status', 1)
      ->exists('field_dependee.target_id');

    $controller_ids = $query->execute();
    if (empty($controller_ids)) {
      return [];
    }

    $controllers = $term_storage->loadMultiple($controller_ids);
    $map = [];

    $candidate_target_ids = [];
    foreach ($controllers as $controller) {
      if (!$controller instanceof TermInterface) {
        continue;
      }
      if (!$controller->hasField('field_dependee') || $controller->get('field_dependee')->isEmpty()) {
        continue;
      }

      $targets = [];
      foreach ($controller->get('field_dependee')->getValue() as $item) {
        $target_tid = (int) ($item['target_id'] ?? 0);
        if ($target_tid > 0) {
          $targets[] = $target_tid;
          $candidate_target_ids[] = $target_tid;
        }
      }

      if ($targets) {
        $controller_tid = (int) $controller->id();
        $map[$controller_tid] = array_values(array_unique(array_map('intval', $targets)));
      }
    }

    if (!$map) {
      return [];
    }

    $published_targets = array_flip($this->filterPublishedTermIds($candidate_target_ids));
    foreach ($map as $controller_tid => $targets) {
      $map[$controller_tid] = array_values(array_filter($targets, static fn (int $tid): bool => isset($published_targets[$tid])));
      if (!$map[$controller_tid]) {
        unset($map[$controller_tid]);
      }
    }

    return $map;
  }

  /**
   * Build symmetric no-combine map with descendant expansion.
   */
  protected function buildNoCombineMap(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', self::VOCABULARIES, 'IN')
      ->condition('status', 1)
      ->exists('field_no_combine.target_id');

    $source_ids = $query->execute();
    if (empty($source_ids)) {
      return [];
    }

    $source_terms = $term_storage->loadMultiple($source_ids);
    $direct_map = [];

    $candidate_ids = [];
    foreach ($source_terms as $source_term) {
      if (!$source_term instanceof TermInterface) {
        continue;
      }
      if (!$source_term->hasField('field_no_combine') || $source_term->get('field_no_combine')->isEmpty()) {
        continue;
      }

      $source_tid = (int) $source_term->id();
      $candidate_ids[] = $source_tid;
      if (!isset($direct_map[$source_tid])) {
        $direct_map[$source_tid] = [];
      }

      foreach ($source_term->get('field_no_combine')->getValue() as $item) {
        $target_tid = (int) ($item['target_id'] ?? 0);
        if ($target_tid > 0) {
          $direct_map[$source_tid][] = $target_tid;
          $candidate_ids[] = $target_tid;
        }
      }

      $direct_map[$source_tid] = array_values(array_unique($direct_map[$source_tid]));
    }

    $published_lookup = array_flip($this->filterPublishedTermIds($candidate_ids));
    foreach ($direct_map as $source_tid => $targets) {
      if (!isset($published_lookup[(int) $source_tid])) {
        unset($direct_map[$source_tid]);
        continue;
      }
      $direct_map[$source_tid] = array_values(array_filter($targets, static fn (int $tid): bool => isset($published_lookup[$tid])));
      if (!$direct_map[$source_tid]) {
        unset($direct_map[$source_tid]);
      }
    }

    if (!$direct_map) {
      return [];
    }

    $descendants_by_tid = $this->buildDescendantsIndex();
    $symmetric_direct = $direct_map;

    foreach ($direct_map as $source_tid => $targets) {
      foreach ($targets as $target_tid) {
        if (!isset($symmetric_direct[$target_tid])) {
          $symmetric_direct[$target_tid] = [];
        }
        $symmetric_direct[$target_tid][] = (int) $source_tid;
      }
    }

    $no_combine_map = [];
    foreach ($symmetric_direct as $source_tid => $targets) {
      $forbidden = [];
      foreach (array_values(array_unique($targets)) as $target_tid) {
        $forbidden[$target_tid] = TRUE;
        foreach ($descendants_by_tid[$target_tid] ?? [] as $descendant_tid) {
          $forbidden[(int) $descendant_tid] = TRUE;
        }
      }

      unset($forbidden[(int) $source_tid]);
      if ($forbidden) {
        $items = array_map('intval', array_keys($forbidden));
        sort($items, SORT_NUMERIC);
        $no_combine_map[(int) $source_tid] = $items;
      }
    }

    return $no_combine_map;
  }

  /**
   * Build descendants for each term in the target vocabularies.
   */
  protected function buildDescendantsIndex(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $children_by_parent = [];
    $known_tids = [];

    foreach (self::VOCABULARIES as $vid) {
      $tree = $term_storage->loadTree($vid, 0, NULL, FALSE);
      $tree_ids = [];
      foreach ($tree as $item) {
        $tid = (int) ($item->tid ?? 0);
        if ($tid > 0) {
          $tree_ids[] = $tid;
        }
      }
      $published_lookup = array_flip($this->filterPublishedTermIds($tree_ids));
      $reachable_lookup = [];

      foreach ($tree as $item) {
        $tid = (int) ($item->tid ?? 0);
        if ($tid <= 0 || !isset($published_lookup[$tid])) {
          continue;
        }

        $parents = isset($item->parents) && is_array($item->parents) ? $item->parents : [0];
        $reachable = FALSE;
        foreach ($parents as $parent_tid) {
          $parent_tid = (int) $parent_tid;
          if ($parent_tid === 0 || isset($reachable_lookup[$parent_tid])) {
            $reachable = TRUE;
            break;
          }
        }
        if (!$reachable) {
          continue;
        }

        $reachable_lookup[$tid] = TRUE;
        $known_tids[$tid] = TRUE;

        foreach ($parents as $parent_tid) {
          $parent_tid = (int) $parent_tid;
          if ($parent_tid !== 0 && !isset($reachable_lookup[$parent_tid])) {
            continue;
          }
          if (!isset($children_by_parent[$parent_tid])) {
            $children_by_parent[$parent_tid] = [];
          }
          $children_by_parent[$parent_tid][] = $tid;
        }
      }
    }

    foreach ($children_by_parent as $parent_tid => $child_ids) {
      $children_by_parent[$parent_tid] = array_values(array_unique(array_map('intval', $child_ids)));
    }

    $descendants_by_tid = [];

    $collect = function (int $tid) use (&$collect, &$descendants_by_tid, $children_by_parent): array {
      if (isset($descendants_by_tid[$tid])) {
        return $descendants_by_tid[$tid];
      }

      $descendants = [];
      foreach ($children_by_parent[$tid] ?? [] as $child_tid) {
        $descendants[$child_tid] = TRUE;
        foreach ($collect($child_tid) as $nested_tid) {
          $descendants[$nested_tid] = TRUE;
        }
      }

      $descendants_by_tid[$tid] = array_map('intval', array_keys($descendants));
      return $descendants_by_tid[$tid];
    };

    foreach (array_keys($known_tids) as $tid) {
      $collect((int) $tid);
    }

    return $descendants_by_tid;
  }

  /**
   * Filter term IDs to published taxonomy terms only.
   */
  protected function filterPublishedTermIds(array $term_ids): array {
    $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
    $term_ids = array_values(array_filter($term_ids));
    if (!$term_ids) {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('tid', $term_ids, 'IN')
      ->condition('status', 1);
    $result = $query->execute();
    $published = array_values(array_map('intval', array_keys($result)));
    if (!$published) {
      return [];
    }

    $published_lookup = array_flip($published);
    return array_values(array_filter($term_ids, static fn (int $tid): bool => isset($published_lookup[$tid])));
  }

}
