<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nomads_navigation\TermLabelOverrideManager;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides navbar taxonomy term search results.
 */
final class NavbarTermSearchController implements ContainerInjectionInterface {

  private const VOCABULARIES = ['cit_countries_information', 't'];
  private const MIN_QUERY_LENGTH = 4;
  private const MAX_QUERY_LENGTH = 64;

  /**
   * Constructs a NavbarTermSearchController object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $termEntityTypeManager,
    private readonly TermLabelOverrideManager $labelOverrideManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('nomads_navigation.term_label_override_manager'),
    );
  }

  /**
   * Returns matching taxonomy terms for the navbar search.
   */
  public function search(Request $request): JsonResponse {
    $keys = trim((string) $request->query->get('q', ''));
    $length = mb_strlen($keys);
    if ($length < self::MIN_QUERY_LENGTH || $length > self::MAX_QUERY_LENGTH) {
      return new JsonResponse([]);
    }

    $storage = $this->termEntityTypeManager->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', self::VOCABULARIES, 'IN')
      ->condition('name', $keys, 'CONTAINS')
      ->condition('status', 1)
      ->sort('name')
      ->range(0, 12);

    $terms = $storage->loadMultiple($query->execute());
    $results = [];

    foreach ($terms as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $parent_ids = $this->getParentIds((int) $term->id(), $storage);
      if ($term->bundle() === 't' && !$this->isDirectChildOfTopLevelTerm($parent_ids, $storage)) {
        continue;
      }

      $sibling_ids = [];
      foreach ($parent_ids as $parent_id) {
        foreach ($this->loadChildren($parent_id, $term->bundle(), $storage) as $sibling) {
          if ($sibling instanceof TermInterface && $sibling->isPublished() && (int) $sibling->id() !== (int) $term->id()) {
            $sibling_ids[] = (int) $sibling->id();
          }
        }
      }

      $results[] = [
        'id' => (int) $term->id(),
        'label' => $this->labelOverrideManager->getLabel($term),
        'vocabulary' => $term->bundle(),
        'parent_ids' => $parent_ids,
        'sibling_ids' => array_values(array_unique($sibling_ids)),
      ];
    }

    return new JsonResponse($results);
  }

  /**
   * Checks whether a term is exactly one level below a top-level term.
   *
   * @param int[] $parent_ids
   *   Parent term IDs.
   */
  private function isDirectChildOfTopLevelTerm(array $parent_ids, TermStorageInterface $storage): bool {
    foreach ($parent_ids as $parent_id) {
      $parent = $storage->load($parent_id);
      if ($parent instanceof TermInterface && $parent->isPublished() && $this->getParentIds($parent_id, $storage) === []) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Gets parent IDs with a per-request cache.
   *
   * @return int[]
   *   Parent term IDs.
   */
  private function getParentIds(int $term_id, TermStorageInterface $storage): array {
    static $parents = [];
    if (!array_key_exists($term_id, $parents)) {
      $parents[$term_id] = array_map('intval', array_keys($storage->loadParents($term_id)));
    }

    return $parents[$term_id];
  }

  /**
   * Loads children with a per-request cache.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Child terms.
   */
  private function loadChildren(int $parent_id, string $bundle, TermStorageInterface $storage): array {
    static $children = [];
    $key = $bundle . ':' . $parent_id;
    if (!array_key_exists($key, $children)) {
      $children[$key] = $storage->loadChildren($parent_id, $bundle);
    }

    return $children[$key];
  }

}
