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
 * Builds the date/location block virtual field output.
 */
class DateLocationBlockExtraFieldBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a DateLocationBlockExtraFieldBuilder instance.
   */
  public function __construct(TranslationInterface $stringTranslation) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Builds the date/location block render array.
   */
  public function build(EntityInterface $listing, EntityViewDisplayInterface $display, string $langcode): ?array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($listing)
      ->addCacheableDependency($display);

    $paragraph_ids = $this->collectLocationParagraphIds($listing, $langcode, $cacheability);
    if ($paragraph_ids === []) {
      return NULL;
    }

    $view = Views::getView('location_periods');
    if ($view === NULL || !$view->access('date_location_table')) {
      return NULL;
    }

    $view->element['#nomads_listing_virtual_fields_paragraph_ids'] = $paragraph_ids;
    $view_render_array = $view->buildRenderable('date_location_table', [], FALSE);
    if (empty($view_render_array)) {
      return NULL;
    }

    $component = $display->getComponent('date_location_block_virtual_field') ?? [];
    $build = [
      '#theme' => 'date_location_block_virtual_field',
      '#label' => $this->t('Date Location Block'),
      '#label_display' => (string) ($component['label'] ?? 'hidden'),
      '#wrapper_id' => 'date-location-block',
      '#wrapper_classes' => ['date-location-block-virtual-field'],
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
