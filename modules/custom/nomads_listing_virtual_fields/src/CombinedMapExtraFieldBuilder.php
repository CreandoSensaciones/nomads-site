<?php

namespace Drupal\nomads_listing_virtual_fields;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\Views;

/**
 * Builds the combined map virtual field output.
 */
class CombinedMapExtraFieldBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a CombinedMapExtraFieldBuilder instance.
   */
  public function __construct(TranslationInterface $stringTranslation) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Builds the combined map extra field render array.
   */
  public function build(EntityInterface $listing, EntityViewDisplayInterface $display, string $langcode): ?array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($listing)
      ->addCacheableDependency($display);

    $paragraph_ids = $this->collectLocationParagraphIds($listing, $langcode, $cacheability);
    if (count($paragraph_ids) < 2) {
      return NULL;
    }

    $view = Views::getView('map_virtual_field');
    if ($view === NULL || !$view->access('combined_map')) {
      return NULL;
    }

    $view->element['#nomads_listing_virtual_fields_paragraph_ids'] = $paragraph_ids;
    $view_render_array = $view->buildRenderable('combined_map', [], FALSE);
    if (empty($view_render_array)) {
      return NULL;
    }

    $wrapper_classes = ['combined-map-virtual-field', 'grid-box', 'grid-3-rows'];
    if (count($paragraph_ids) >= 4) {
      $wrapper_classes[] = 'grid-2-columns';
    }

    $component = $display->getComponent('combined_map_virtual_field') ?? [];
    $build = [
      '#theme' => 'combined_map_virtual_field',
      '#label' => $this->t('Combined map virtual field'),
      '#label_display' => (string) ($component['label'] ?? 'hidden'),
      '#wrapper_id' => 'map',
      '#wrapper_classes' => $wrapper_classes,
      '#content' => $view_render_array,
    ];

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Collects location paragraph IDs from field_location_date.
   *
   * @return int[]
   *   Paragraph IDs for location paragraphs.
   */
  protected function collectLocationParagraphIds(EntityInterface $listing, string $langcode, CacheableMetadata $cacheability): array {
    $paragraph_ids = [];
    if (!$listing->hasField('field_location_date') || $listing->get('field_location_date')->isEmpty()) {
      return $paragraph_ids;
    }

    foreach ($listing->get('field_location_date')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'location') {
        continue;
      }

      $translated = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph;
      $cacheability->addCacheableDependency($translated);
      $paragraph_ids[] = (int) $translated->id();
    }

    return array_values(array_unique(array_filter($paragraph_ids)));
  }

}
