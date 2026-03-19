<?php

namespace Drupal\nomads_listing_virtual_fields;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds the listing menu virtual field output.
 */
class ListingMenuExtraFieldBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a ListingMenuExtraFieldBuilder instance.
   */
  public function __construct(TranslationInterface $stringTranslation) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Builds the listing menu extra field render array.
   */
  public function build(EntityInterface $listing, EntityViewDisplayInterface $display, string $langcode): ?array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($listing)
      ->addCacheableDependency($display);

    $items = $this->collectItems($listing, $langcode, $cacheability);
    if ($items === []) {
      return NULL;
    }

    $component = $display->getComponent('listing_menu_virtual_field') ?? [];
    $build = [
      '#theme' => 'listing_menu_virtual_field',
      '#label' => $this->t('Listing menu virtual field'),
      '#label_display' => (string) ($component['label'] ?? 'hidden'),
      '#wrapper_classes' => ['listing-menu-virtual-field'],
      '#items' => $items,
    ];
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Collects unique paragraph bundle anchors in listing field order.
   *
   * @return array<int, array<string, mixed>>
   *   Menu link render metadata.
   */
  protected function collectItems(EntityInterface $listing, string $langcode, CacheableMetadata $cacheability): array {
    $items = [];
    $location_count = $this->countLocationParagraphs($listing, $langcode, $cacheability);
    if ($location_count < 1) {
      return $items;
    }

    $items[] = [
      'title' => $location_count > 1 ? $this->t('Locations') : $this->t('Location'),
      'href' => '#location',
    ];

    if ($location_count >= 2) {
      $items[] = [
        'title' => $this->t('Map'),
        'href' => '#map',
      ];
    }

    if ($listing->hasField('field_links') && count($listing->get('field_links')) > 3) {
      $items[] = [
        'title' => $this->t('Links'),
        'href' => '#links',
      ];
    }

    return $items;
  }

  /**
   * Counts translated location paragraphs from field_location_date.
   */
  protected function countLocationParagraphs(EntityInterface $listing, string $langcode, CacheableMetadata $cacheability): int {
    if (!$listing->hasField('field_location_date') || $listing->get('field_location_date')->isEmpty()) {
      return 0;
    }

    $count = 0;
    foreach ($listing->get('field_location_date')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'location') {
        continue;
      }

      $translated = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph;
      $cacheability->addCacheableDependency($translated);
      $count++;
    }

    return $count;
  }

}
