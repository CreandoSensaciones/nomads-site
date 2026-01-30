<?php

namespace Drupal\magical_links\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds and caches taxonomy-based icon definitions for magical links.
 */
class MagicalLinksDefinitionRepository {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileUrlGeneratorInterface $fileUrlGenerator;
  protected CacheBackendInterface $cache;
  protected LanguageManagerInterface $languageManager;

  /**
   * Static cache keyed by repository cache id.
   *
   * @var array<string, array{definitions: array, cacheable_metadata: \Drupal\Core\Cache\CacheableMetadata}>
   */
  protected array $staticCache = [];

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FileUrlGeneratorInterface $fileUrlGenerator,
    CacheBackendInterface $cache,
    LanguageManagerInterface $languageManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->cache = $cache;
    $this->languageManager = $languageManager;
  }

  /**
   * Returns definitions with cacheability metadata.
   *
   * Cache tags include the vocabulary list tag plus referenced term/media/file
   * tags so icon or label updates invalidate the cached definitions. Cache
   * contexts include interface + content language to reflect translated labels.
   */
  public function getDefinitions(
    string $vocabulary = 'links',
    string $icon_field = 'field_icons',
    string $prefix_field = 'field_prefill',
    bool $include_legacy_prefix_fallback = TRUE,
    bool $blank_website_prefill = TRUE,
  ): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $cache_key = implode(':', [
      'magical_links',
      'definitions',
      $vocabulary,
      $langcode,
      $icon_field,
      $prefix_field,
      $include_legacy_prefix_fallback ? 'legacy' : 'primary',
      $blank_website_prefill ? 'blanksite' : 'keepsite',
    ]);

    if (isset($this->staticCache[$cache_key])) {
      return $this->staticCache[$cache_key];
    }

    $cached = $this->cache->get($cache_key);
    if ($cached && is_array($cached->data)) {
      $metadata = (new CacheableMetadata())
        ->setCacheTags($cached->data['cache_tags'] ?? [])
        ->setCacheContexts($cached->data['cache_contexts'] ?? [])
        ->setCacheMaxAge($cached->data['cache_max_age'] ?? Cache::PERMANENT);
      $result = [
        'definitions' => $cached->data['definitions'] ?? [],
        'cacheable_metadata' => $metadata,
      ];
      $this->staticCache[$cache_key] = $result;
      return $result;
    }

    $metadata = new CacheableMetadata();
    $metadata->addCacheTags(["taxonomy_term_list:{$vocabulary}"]);
    $metadata->addCacheContexts([
      'languages:language_interface',
      'languages:language_content',
    ]);

    $terms = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree($vocabulary, 0, NULL, TRUE);
    if (!$terms) {
      $result = [
        'definitions' => [],
        'cacheable_metadata' => $metadata,
      ];
      $this->storeCache($cache_key, $result);
      $this->staticCache[$cache_key] = $result;
      return $result;
    }

    $labels = [];
    foreach ($terms as $term) {
      $translated = $this->getTranslatedTerm($term, $langcode);
      $labels[(int) $term->id()] = (string) $translated->label();
      $metadata->addCacheableDependency($translated);
    }

    $has_children = [];
    foreach ($terms as $term) {
      $translated = $this->getTranslatedTerm($term, $langcode);
      foreach ($translated->get('parent')->getValue() as $parent_item) {
        $parent_id = (int) ($parent_item['target_id'] ?? 0);
        if ($parent_id) {
          $has_children[$parent_id] = TRUE;
        }
      }
    }

    $definitions = [];
    $order = 0;
    foreach ($terms as $term) {
      $tid = (int) $term->id();
      if (isset($has_children[$tid])) {
        continue;
      }

      $translated = $this->getTranslatedTerm($term, $langcode);
      $parent_ids = array_filter(array_map(static function (array $item): int {
        return (int) ($item['target_id'] ?? 0);
      }, $translated->get('parent')->getValue()));
      $parent_tid = $parent_ids ? (int) reset($parent_ids) : 0;
      $parent_label = $parent_tid ? ($labels[$parent_tid] ?? '') : '';
      $icon = $this->getTermIconData($translated, $icon_field, $metadata);

      $label = (string) $translated->label();
      $prefill = $this->getTermPrefill($translated, $prefix_field, $include_legacy_prefix_fallback);
      $is_website = (mb_strtolower(trim($label)) === 'website');
      if ($is_website && $blank_website_prefill) {
        $prefill = '';
      }

      $definitions[] = [
        'tid' => $tid,
        'label' => $label,
        'prefill' => $prefill,
        'prefix' => $prefill,
        'tooltip' => $label,
        'link_text' => $icon['alt'] ?? '',
        'icon_url' => $icon['url'] ?? '',
        'icon_alt' => $icon['alt'] ?? '',
        'group_key' => $parent_tid ? (string) $parent_tid : 'root',
        'group_label' => (string) $parent_label,
        'order' => $order,
        'is_website' => $is_website,
      ];
      $order++;
    }

    $result = [
      'definitions' => $definitions,
      'cacheable_metadata' => $metadata,
    ];
    $this->storeCache($cache_key, $result);
    $this->staticCache[$cache_key] = $result;

    return $result;
  }

  protected function storeCache(string $cache_key, array $result): void {
    $metadata = $result['cacheable_metadata'] ?? new CacheableMetadata();
    $data = [
      'definitions' => $result['definitions'] ?? [],
      'cache_tags' => $metadata->getCacheTags(),
      'cache_contexts' => $metadata->getCacheContexts(),
      'cache_max_age' => $metadata->getCacheMaxAge(),
    ];
    $this->cache->set($cache_key, $data, Cache::PERMANENT, $metadata->getCacheTags());
  }

  protected function getTranslatedTerm(TermInterface $term, string $langcode): TermInterface {
    if ($term->hasTranslation($langcode)) {
      return $term->getTranslation($langcode);
    }
    return $term;
  }

  protected function getTermPrefill(TermInterface $term, string $primary_field, bool $include_legacy): string {
    $field_names = [$primary_field];
    if ($include_legacy) {
      $legacy_fields = ['field_link', 'field_url', 'field_link_prefix', 'field_prefix'];
      foreach ($legacy_fields as $legacy_field) {
        if (!in_array($legacy_field, $field_names, TRUE)) {
          $field_names[] = $legacy_field;
        }
      }
    }

    foreach ($field_names as $field_name) {
      if (!$term->hasField($field_name) || $term->get($field_name)->isEmpty()) {
        continue;
      }
      $item = $term->get($field_name)->first();
      if (!$item) {
        continue;
      }
      if (method_exists($item, 'getUrl')) {
        return $item->getUrl()->toString();
      }
      if (isset($item->uri)) {
        return (string) $item->uri;
      }
      if (isset($item->value)) {
        return (string) $item->value;
      }
    }

    if ($include_legacy) {
      $description = trim(strip_tags((string) $term->getDescription()));
      if ($description !== '' && preg_match('~^https?://~i', $description)) {
        return $description;
      }
    }

    return '';
  }

  protected function getTermIconData(TermInterface $term, string $icon_field, CacheableMetadata $metadata): array {
    if (!$term->hasField($icon_field) || $term->get($icon_field)->isEmpty()) {
      return [];
    }

    $icon_item = $term->get($icon_field)->first();
    if (!$icon_item || empty($icon_item->entity)) {
      return [];
    }

    $entity = $icon_item->entity;
    if ($entity instanceof FileInterface) {
      $metadata->addCacheableDependency($entity);
      return [
        'url' => $this->fileUrlGenerator->generateString($entity->getFileUri()),
        'alt' => (string) ($icon_item->alt ?? ''),
      ];
    }

    if ($entity instanceof MediaInterface) {
      $metadata->addCacheableDependency($entity);
      $image_item = $this->getMediaImageItem($entity);
      if (!$image_item || empty($image_item->entity)) {
        return [];
      }
      $file = $image_item->entity;
      if (!$file instanceof FileInterface) {
        return [];
      }
      $metadata->addCacheableDependency($file);
      return [
        'url' => $this->fileUrlGenerator->generateString($file->getFileUri()),
        'alt' => (string) ($image_item->alt ?? ''),
      ];
    }

    return [];
  }

  /**
   * Get the image field item from a media entity.
   */
  protected function getMediaImageItem(MediaInterface $media) {
    $source = $media->getSource();
    if (!$source) {
      return NULL;
    }

    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$media_type) {
      return NULL;
    }

    $definition = $source->getSourceFieldDefinition($media_type);
    if (!$definition) {
      return NULL;
    }

    $field_name = $definition->getName();
    if (!$media->hasField($field_name) || $media->get($field_name)->isEmpty()) {
      return NULL;
    }

    return $media->get($field_name)->first();
  }

}
