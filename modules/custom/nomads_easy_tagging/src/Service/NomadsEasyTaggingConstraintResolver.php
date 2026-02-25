<?php

namespace Drupal\nomads_easy_tagging\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Utility\Xss;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\nomads_term_dependees\ConstraintMapBuilder;
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
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constraint map builder from nomads_term_dependees.
   */
  protected ConstraintMapBuilder $constraintMapBuilder;

  /**
   * Constructs the resolver.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    CacheBackendInterface $cache,
    LanguageManagerInterface $languageManager,
    FileUrlGeneratorInterface $fileUrlGenerator,
    ConstraintMapBuilder $constraintMapBuilder,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cache = $cache;
    $this->languageManager = $languageManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->constraintMapBuilder = $constraintMapBuilder;
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
    if (!$parent instanceof TermInterface || !$parent->isPublished()) {
      return [
        'parent_children_limit' => NULL,
        'children' => [],
      ];
    }

    $vid = $parent->bundle();
    $children = $this->getPublishedTopLevelTerms($vid, (int) $parent->id(), 1);
    $children_data = [];
    $cache_tags = Cache::mergeTags(['taxonomy_term_list', 'taxonomy_term_list:' . $vid], $parent->getCacheTags());

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
    if (!$term instanceof TermInterface || !$term->isPublished()) {
      return [];
    }

    $vid = $term->bundle();
    $descendants = $this->getPublishedTreeTermIds($vid, $tid, NULL);

    $cache_tags = Cache::mergeTags(['taxonomy_term_list', 'taxonomy_term_list:' . $vid], $term->getCacheTags());
    $this->cache->set($cid, $descendants, Cache::PERMANENT, $cache_tags);

    return $descendants;
  }

  /**
   * Compute blocked terms for unified and types selections.
   */
  public function computeBlocked(array $selected_unified_tids, array $selected_type_tids): array {
    $selected_unified_tids = array_values(array_unique(array_filter($selected_unified_tids)));
    $selected_type_tids = array_values(array_unique(array_filter($selected_type_tids)));
    $selected_all = $this->filterPublishedTermIds(array_values(array_unique(array_merge($selected_unified_tids, $selected_type_tids))));

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $selected_terms = [];
    if ($selected_all) {
      foreach ($term_storage->loadMultiple($selected_all) as $loaded_tid => $term) {
        if ($term instanceof TermInterface && $term->isPublished()) {
          $selected_terms[(int) $loaded_tid] = $term;
        }
      }
    }
    $constraint_map = $this->constraintMapBuilder->getConstraintMap();
    $no_combine_map = $constraint_map['no_combine'] ?? [];

    $blocked = [];
    $selection_limits = [];

    foreach ($selected_terms as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $this->collectSelectionLimit($term, $selection_limits);
    }

    $blocked_candidate_lookup = [];
    foreach ($selected_all as $blocker_tid) {
      foreach (($no_combine_map[$blocker_tid] ?? []) as $blocked_tid) {
        $blocked_candidate_lookup[(int) $blocked_tid] = TRUE;
      }
    }
    $published_blocked_lookup = array_flip($this->filterPublishedTermIds(array_keys($blocked_candidate_lookup)));

    foreach ($selected_all as $blocker_tid) {
      $blocked_targets = $no_combine_map[$blocker_tid] ?? [];
      if (!$blocked_targets) {
        continue;
      }
      $blocker_label = '';
      if (isset($selected_terms[$blocker_tid]) && $selected_terms[$blocker_tid] instanceof TermInterface) {
        $blocker_label = (string) $selected_terms[$blocker_tid]->label();
      }
      foreach ($blocked_targets as $blocked_tid) {
        if (!isset($published_blocked_lookup[(int) $blocked_tid])) {
          continue;
        }
        $this->addBlocked($blocked, (int) $blocked_tid, (int) $blocker_tid, $blocker_label);
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
    $settings = $this->getTermSettings($term);

    return [
      'tid' => (int) $term->id(),
      'label' => (string) $term->label(),
      'ui_explainer' => $explainer,
      'ui_explainer_html' => $explainer_html,
      'has_children' => $this->termHasChildren($term),
      'icon_url' => $this->getTermIconUrl($term),
      'children_limit' => $this->getChildrenLimit($term),
      'dependee_tids' => $this->getDependeeTargets($term),
      'branch_mode' => $this->getBranchMode($settings),
      'is_category_label' => in_array('category_label', $settings, TRUE),
      'shows_initially' => in_array('shows_initially', $settings, TRUE),
      'settings' => $settings,
    ];
  }

  /**
   * Get dependee target tids from a term.
   */
  protected function getDependeeTargets(TermInterface $term): array {
    if (!$term->hasField('field_dependee') || $term->get('field_dependee')->isEmpty()) {
      return [];
    }

    $targets = [];
    foreach ($term->get('field_dependee')->getValue() as $item) {
      $target_tid = (int) ($item['target_id'] ?? 0);
      if ($target_tid > 0) {
        $targets[] = $target_tid;
      }
    }

    return $this->filterPublishedTermIds(array_values(array_unique($targets)));
  }

  /**
   * Determine if a term has children.
   */
  protected function termHasChildren(TermInterface $term): bool {
    return !empty($this->getPublishedTreeTermIds($term->bundle(), (int) $term->id(), 1));
  }

  /**
   * Read the children limit from a term.
   */
  protected function getChildrenLimit(TermInterface $term): ?int {
    $settings = $this->getTermSettings($term);
    if (in_array('limit_1_child', $settings, TRUE) || in_array('limit_1_children', $settings, TRUE)) {
      return 1;
    }
    if (in_array('limit_2_child', $settings, TRUE) || in_array('limit_2_children', $settings, TRUE)) {
      return 2;
    }
    if (in_array('limit_3_child', $settings, TRUE) || in_array('limit_3_children', $settings, TRUE)) {
      return 3;
    }

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
   * Resolve effective branch mode for a term.
   */
  protected function getBranchMode(array $settings): string {
    if (in_array('branch_replace', $settings, TRUE)) {
      return 'replace';
    }
    if (in_array('branch_open', $settings, TRUE)) {
      return 'open';
    }

    return 'ignore';
  }

  /**
   * Read normalized term setting machine names.
   */
  protected function getTermSettings(TermInterface $term): array {
    $field_names = [
      'field_settings',
      'field_easy_tagging_settings',
      'field_easy_tagging_behavior',
    ];

    foreach ($field_names as $field_name) {
      if (!$term->hasField($field_name) || $term->get($field_name)->isEmpty()) {
        continue;
      }

      $values = [];
      foreach ($term->get($field_name)->getValue() as $item) {
        if (!isset($item['value'])) {
          continue;
        }
        $value = $this->normalizeSettingMachineName((string) $item['value']);
        if ($value !== '') {
          $values[] = $value;
        }
      }

      if ($values) {
        return array_values(array_unique($values));
      }
    }

    return [];
  }

  /**
   * Normalize machine names to a stable comparison key.
   */
  protected function normalizeSettingMachineName(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(' ', '_', $value);
    return preg_replace('/_+/', '_', $value) ?? '';
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
      if ($parent_term instanceof TermInterface && $parent_term->isPublished()) {
        $limit = $this->getChildrenLimit($parent_term);
        if ($limit !== NULL) {
          $selection_limits[$parent_tid] = $limit;
        }
      }
    }
  }

  /**
   * Add a blocked term with a blocker term.
   */
  protected function addBlocked(array &$blocked, int $blocked_tid, int $blocker_tid, string $blocker_label): void {
    if (!isset($blocked[$blocked_tid])) {
      $blocked[$blocked_tid] = [
        'blocked_by' => [],
      ];
    }

    foreach ($blocked[$blocked_tid]['blocked_by'] as $existing) {
      if ((int) $existing['tid'] === $blocker_tid) {
        return;
      }
    }

    $blocked[$blocked_tid]['blocked_by'][] = [
      'tid' => $blocker_tid,
      'label' => $blocker_label,
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

  /**
   * Get published terms for a vocabulary root or subtree.
   */
  public function getPublishedTopLevelTerms(string $vid, int $parent_tid = 0, ?int $max_depth = 1): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $this->getPublishedTreeTermIds($vid, $parent_tid, $max_depth);
    if (!$ids) {
      return [];
    }

    $loaded = $term_storage->loadMultiple($ids);
    $ordered = [];
    foreach ($ids as $tid) {
      if (isset($loaded[$tid]) && $loaded[$tid] instanceof TermInterface && $loaded[$tid]->isPublished()) {
        $ordered[] = $loaded[$tid];
      }
    }

    return $ordered;
  }

  /**
   * Get all published terms in a vocabulary with subtree pruning.
   */
  public function getPublishedTermsForVocabulary(string $vid): array {
    return $this->getPublishedTopLevelTerms($vid, 0, NULL);
  }

  /**
   * Normalize and keep only published term IDs.
   */
  public function sanitizePublishedTermIds(array $term_ids): array {
    return $this->filterPublishedTermIds($term_ids);
  }

  /**
   * Get published term IDs for a tree while pruning unpublished parents.
   */
  protected function getPublishedTreeTermIds(string $vid, int $parent_tid = 0, ?int $max_depth = NULL): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    if ($parent_tid > 0) {
      $parent = $term_storage->load($parent_tid);
      if (!$parent instanceof TermInterface || !$parent->isPublished()) {
        return [];
      }
    }

    $tree = $term_storage->loadTree($vid, $parent_tid, $max_depth, FALSE);
    if (!$tree) {
      return [];
    }

    $tree_ids = [];
    foreach ($tree as $item) {
      if (!empty($item->tid)) {
        $tree_ids[] = (int) $item->tid;
      }
    }
    $tree_ids = array_values(array_unique($tree_ids));
    if (!$tree_ids) {
      return [];
    }

    $published_lookup = array_flip($this->filterPublishedTermIds($tree_ids));
    if (!$published_lookup) {
      return [];
    }

    $allowed_lookup = [];
    $allowed_ids = [];

    foreach ($tree as $item) {
      $tid = (int) ($item->tid ?? 0);
      if (!$tid || !isset($published_lookup[$tid])) {
        continue;
      }

      $parents = isset($item->parents) && is_array($item->parents) ? $item->parents : [$parent_tid];
      $reachable = FALSE;
      foreach ($parents as $candidate_parent) {
        $candidate_parent = (int) $candidate_parent;
        if ($candidate_parent === 0 || $candidate_parent === $parent_tid || isset($allowed_lookup[$candidate_parent])) {
          $reachable = TRUE;
          break;
        }
      }

      if (!$reachable) {
        continue;
      }

      $allowed_lookup[$tid] = TRUE;
      $allowed_ids[] = $tid;
    }

    return $allowed_ids;
  }

  /**
   * Filter term IDs to published terms only.
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

    $ids = $query->execute();
    $published = array_values(array_map('intval', array_keys($ids)));
    if (!$published) {
      return [];
    }

    $published_lookup = array_flip($published);
    return array_values(array_filter($term_ids, static fn (int $tid): bool => isset($published_lookup[$tid])));
  }

}
