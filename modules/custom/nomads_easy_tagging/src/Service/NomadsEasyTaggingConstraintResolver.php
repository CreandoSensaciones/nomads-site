<?php

namespace Drupal\nomads_easy_tagging\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Resolves easy tagging constraints and term data.
 */
class NomadsEasyTaggingConstraintResolver {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * The entity field manager.
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs the resolver.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    CacheBackendInterface $cache,
    LanguageManagerInterface $languageManager,
    FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->cache = $cache;
    $this->languageManager = $languageManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Get children for a parent term.
   */
  public function getChildren(int $parent_tid): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $cid = 'nomads_easy_tagging:children:' . $parent_tid . ':' . $langcode;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $parent = $term_storage->load($parent_tid);
    if (!$parent instanceof TermInterface) {
      return [
        'parent_children_limit' => NULL,
        'children' => [],
      ];
    }

    $vid = $parent->bundle();
    $children = $term_storage->loadTree($vid, $parent_tid, 1, TRUE);
    $children_data = [];
    $cache_tags = Cache::mergeTags(['taxonomy_term_list'], $parent->getCacheTags());

    foreach ($children as $child) {
      if (!$child instanceof TermInterface) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $child->getCacheTags());

      $children_data[] = $this->buildTermCardData($child);
    }

    $parent_limit = $this->getChildrenLimit($parent);

    $data = [
      'parent_children_limit' => $parent_limit,
      'children' => $children_data,
    ];

    $this->cache->set($cid, $data, Cache::PERMANENT, $cache_tags);
    return $data;
  }

  /**
   * Get descendants for a term.
   */
  public function getDescendants(int $tid): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $cid = 'nomads_easy_tagging:descendants:' . $tid . ':' . $langcode;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term = $term_storage->load($tid);
    if (!$term instanceof TermInterface) {
      return [];
    }

    $vid = $term->bundle();
    $tree = $term_storage->loadTree($vid, $tid, NULL, FALSE);
    $descendants = [];
    foreach ($tree as $item) {
      if (!empty($item->tid)) {
        $descendants[] = (int) $item->tid;
      }
    }

    $cache_tags = Cache::mergeTags(['taxonomy_term_list'], $term->getCacheTags());
    $this->cache->set($cid, $descendants, Cache::PERMANENT, $cache_tags);

    return $descendants;
  }

  /**
   * Compute blocked terms for unified and types selections.
   */
  public function computeBlocked(array $selected_unified_tids, array $selected_type_tids): array {
    $selected_unified_tids = array_values(array_unique(array_filter($selected_unified_tids)));
    $selected_type_tids = array_values(array_unique(array_filter($selected_type_tids)));
    $selected_all = array_values(array_unique(array_merge($selected_unified_tids, $selected_type_tids)));

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $selected_terms = $selected_all ? $term_storage->loadMultiple($selected_all) : [];

    $blocked = [];
    $selection_limits = [];

    foreach ($selected_terms as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $this->collectSelectionLimit($term, $selection_limits);

      $blocked_targets = $this->getNoCombineTargets($term);
      foreach ($blocked_targets as $target_tid) {
        $this->addBlocked($blocked, $target_tid, $term);
        foreach ($this->getDescendants($target_tid) as $descendant_tid) {
          $this->addBlocked($blocked, $descendant_tid, $term);
        }
      }
    }

    if ($selected_all && $this->hasNoCombineField()) {
      $query = $term_storage->getQuery();
      $query->accessCheck(TRUE);
      $query->condition('field_no_combine.target_id', $selected_all, 'IN');
      $referencing_ids = $query->execute();

      if ($referencing_ids) {
        $referencing_terms = $term_storage->loadMultiple($referencing_ids);
        foreach ($referencing_terms as $referencing_term) {
          if (!$referencing_term instanceof TermInterface) {
            continue;
          }
          $referenced_ids = $this->getNoCombineTargets($referencing_term);
          foreach ($referenced_ids as $referenced_id) {
            if (!isset($selected_terms[$referenced_id])) {
              continue;
            }
            $blocker = $selected_terms[$referenced_id];
            $this->addBlocked($blocked, (int) $referencing_term->id(), $blocker);
            foreach ($this->getDescendants((int) $referencing_term->id()) as $descendant_tid) {
              $this->addBlocked($blocked, $descendant_tid, $blocker);
            }
          }
        }
      }
    }

    return [
      'blocked_unified' => $blocked,
      'blocked_types' => $blocked,
      'selection_limits' => $selection_limits,
    ];
  }

  /**
   * Build a term card data payload.
   */
  public function buildTermCardData(TermInterface $term): array {
    $explainer = '';
    if ($term->hasField('field_ui_explainer')) {
      $explainer = trim((string) $term->get('field_ui_explainer')->value);
    }
    $explainer_html = $explainer !== '' ? Xss::filter($explainer, ['p', 'br', 'strong', 'em', 'a']) : '';

    return [
      'tid' => (int) $term->id(),
      'label' => (string) $term->label(),
      'ui_explainer' => $explainer,
      'ui_explainer_html' => $explainer_html,
      'has_children' => $this->termHasChildren($term),
      'icon_url' => $this->getTermIconUrl($term),
      'children_limit' => $this->getChildrenLimit($term),
    ];
  }

  /**
   * Determine if a term has children.
   */
  protected function termHasChildren(TermInterface $term): bool {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tree = $term_storage->loadTree($term->bundle(), (int) $term->id(), 1, FALSE);
    return !empty($tree);
  }

  /**
   * Read the children limit from a term.
   */
  protected function getChildrenLimit(TermInterface $term): ?int {
    if (!$term->hasField('field_children_limit')) {
      return NULL;
    }

    $value = $term->get('field_children_limit')->value;
    if ($value === NULL || $value === '') {
      return NULL;
    }

    $limit = (int) $value;
    return $limit > 0 ? $limit : NULL;
  }

  /**
   * Add a selection limit for a parent term.
   */
  protected function collectSelectionLimit(TermInterface $term, array &$selection_limits): void {
    foreach ($term->get('parent')->getValue() as $parent) {
      $parent_tid = (int) $parent['target_id'];
      if (!$parent_tid) {
        continue;
      }
      if (isset($selection_limits[$parent_tid])) {
        continue;
      }
      $parent_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($parent_tid);
      if ($parent_term instanceof TermInterface) {
        $limit = $this->getChildrenLimit($parent_term);
        if ($limit !== NULL) {
          $selection_limits[$parent_tid] = $limit;
        }
      }
    }
  }

  /**
   * Get no combine target tids from a term.
   */
  protected function getNoCombineTargets(TermInterface $term): array {
    if (!$term->hasField('field_no_combine') || $term->get('field_no_combine')->isEmpty()) {
      return [];
    }

    $targets = [];
    foreach ($term->get('field_no_combine')->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $targets[] = (int) $item['target_id'];
      }
    }

    return array_values(array_unique($targets));
  }

  /**
   * Add a blocked term with a blocker term.
   */
  protected function addBlocked(array &$blocked, int $blocked_tid, TermInterface $blocker): void {
    if (!isset($blocked[$blocked_tid])) {
      $blocked[$blocked_tid] = [
        'blocked_by' => [],
      ];
    }

    foreach ($blocked[$blocked_tid]['blocked_by'] as $existing) {
      if ((int) $existing['tid'] === (int) $blocker->id()) {
        return;
      }
    }

    $blocked[$blocked_tid]['blocked_by'][] = [
      'tid' => (int) $blocker->id(),
      'label' => (string) $blocker->label(),
    ];
  }

  /**
   * Get a term icon URL with variant selection.
   */
  protected function getTermIconUrl(TermInterface $term): ?string {
    if (!$term->hasField('field_icons') || $term->get('field_icons')->isEmpty()) {
      return NULL;
    }

    $icon_field = $term->get('field_icons');
    $media_candidates = [];

    foreach ($icon_field as $item) {
      if (empty($item->entity)) {
        continue;
      }
      $entity = $item->entity;
      if ($entity instanceof MediaInterface) {
        $media_candidates[] = $entity;
      }
    }

    if (!$media_candidates) {
      return NULL;
    }

    $selected = $this->selectMediaVariant($media_candidates);
    if (!$selected) {
      return NULL;
    }

    if ($selected instanceof MediaInterface) {
      $source = $selected->getSource();
      $media_type = $this->entityTypeManager->getStorage('media_type')->load($selected->bundle());
      if (!$media_type) {
        return NULL;
      }
      $definition = $source->getSourceFieldDefinition($media_type);
      if (!$definition) {
        return NULL;
      }
      $field_name = $definition->getName();
      if (!$selected->hasField($field_name) || $selected->get($field_name)->isEmpty()) {
        return NULL;
      }
      $file = $selected->get($field_name)->entity;
      if ($file instanceof FileInterface) {
        return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }

    return NULL;
  }

  /**
   * Pick a media variant by field value, prefer onboarding then default.
   */
  protected function selectMediaVariant(array $candidates): ?MediaInterface {
    $preferred = NULL;
    $fallback = NULL;

    foreach ($candidates as $candidate) {
      if ($preferred === NULL) {
        $preferred = $candidate;
      }

      $variant = $this->getMediaVariantValue($candidate);
      if ($variant === 'onboarding') {
        return $candidate;
      }
      if ($variant === 'default') {
        $fallback = $candidate;
      }
    }

    return $fallback ?? $preferred;
  }

  /**
   * Check if field_no_combine exists on taxonomy terms.
   */
  protected function hasNoCombineField(): bool {
    $storages = $this->entityFieldManager->getFieldStorageDefinitions('taxonomy_term');
    return isset($storages['field_no_combine']);
  }

  /**
   * Read a variant string from a media entity.
   */
  protected function getMediaVariantValue(MediaInterface $media): string {
    $fields = ['field_variant', 'field_icon_variant', 'field_display_variant'];
    foreach ($fields as $field_name) {
      if ($media->hasField($field_name) && !$media->get($field_name)->isEmpty()) {
        return (string) $media->get($field_name)->value;
      }
    }

    return '';
  }

}
