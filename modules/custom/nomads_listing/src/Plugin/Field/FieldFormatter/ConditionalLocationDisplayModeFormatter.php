<?php

declare(strict_types=1);

namespace Drupal\nomads_listing\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Renders location paragraphs with conditional display modes.
 */
#[FieldFormatter(
  id: 'nomads_listing_conditional_location_display_mode',
  label: new TranslatableMarkup('Conditional display mode'),
  description: new TranslatableMarkup('Render location paragraphs in display mode 3 when complete, otherwise group geocoded locations in display mode 2.'),
  field_types: [
    'entity_reference_revisions',
  ],
)]
final class ConditionalLocationDisplayModeFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Complete locations use display mode 3. Incomplete geocoded locations are grouped and use display mode 2.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    if (empty($langcode)) {
      $langcode = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    }

    $elements = $this->viewElements($items, $langcode);
    if ($items instanceof CacheableDependencyInterface) {
      (new CacheableMetadata())
        ->addCacheableDependency($items)
        ->applyTo($elements);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $fallback = [];
    $fallback_delta = NULL;

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if (!$entity instanceof ParagraphInterface || $entity->bundle() !== 'location') {
        continue;
      }

      if (!$this->hasUsableFieldValue($entity, 'field_location')) {
        continue;
      }

      if ($this->shouldUseDetailedDisplay($entity)) {
        $elements[$delta] = $this->renderParagraph($entity, '3', $items, $delta);
        continue;
      }

      $fallback_delta ??= $delta;
      $fallback[] = $this->renderParagraph($entity, '2', $items, $delta);
    }

    if ($fallback !== [] && $fallback_delta !== NULL) {
      $elements[$fallback_delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['location-wrapper', 'shadow-box', 'flexi-box'],
        ],
      ];

      foreach ($fallback as $delta => $build) {
        $elements[$fallback_delta][$delta] = $build;
      }
    }

    ksort($elements);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'paragraph'
      && parent::isApplicable($field_definition);
  }

  /**
   * Determines whether a location paragraph has enough data for mode 3.
   */
  private function shouldUseDetailedDisplay(ParagraphInterface $paragraph): bool {
    return $this->hasUsableFieldValue($paragraph, 'field_location')
      && $this->hasUsableFieldValue($paragraph, 'field_country')
      && $this->hasUsableFieldValue($paragraph, 'field_title')
      && $this->hasUsableFieldValue($paragraph, 'field_description')
      && $this->countFieldItems($paragraph, 'field_images') >= 4;
  }

  /**
   * Checks whether a paragraph field has usable data.
   */
  private function hasUsableFieldValue(ParagraphInterface $paragraph, string $field_name): bool {
    if (!$paragraph->hasField($field_name) || $paragraph->get($field_name)->isEmpty()) {
      return FALSE;
    }

    $field = $paragraph->get($field_name);
    if ($field_name === 'field_title') {
      return trim((string) ($field->value ?? '')) !== '';
    }

    if ($field_name === 'field_description') {
      return trim((string) ($field->value ?? '')) !== '';
    }

    return $this->countFieldItems($paragraph, $field_name) > 0;
  }

  /**
   * Counts non-empty field items.
   */
  private function countFieldItems(ParagraphInterface $paragraph, string $field_name): int {
    if (!$paragraph->hasField($field_name) || $paragraph->get($field_name)->isEmpty()) {
      return 0;
    }

    $count = 0;
    foreach ($paragraph->get($field_name) as $item) {
      if (!$item->isEmpty()) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Renders a paragraph with recursion protection matching the core formatter.
   */
  private function renderParagraph(ParagraphInterface $paragraph, string $view_mode, FieldItemListInterface $items, int|string $delta): array {
    static $depth = 0;
    $depth++;
    if ($depth > 20) {
      $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
        '@entity_type' => $paragraph->getEntityTypeId(),
        '@entity_id' => $paragraph->id(),
      ]);
      $depth = 0;
      return [];
    }

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder($paragraph->getEntityTypeId());
    $build = $view_builder->view($paragraph, $view_mode, $paragraph->language()->getId());
    if ($view_mode === '3') {
      $build['#attributes']['class'][] = 'shadow-box';
      $build['#attributes']['class'][] = 'full-width-box';
    }

    if (!empty($items[$delta]->_attributes) && !$paragraph->isNew() && $paragraph->hasLinkTemplate('canonical')) {
      $items[$delta]->_attributes += ['resource' => $paragraph->toUrl()->toString()];
    }

    $depth = 0;
    return $build;
  }

}
