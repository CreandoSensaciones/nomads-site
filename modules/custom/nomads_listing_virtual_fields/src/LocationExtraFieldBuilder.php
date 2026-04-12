<?php

namespace Drupal\nomads_listing_virtual_fields;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds the location virtual field output.
 */
class LocationExtraFieldBuilder {

  use StringTranslationTrait;

  /**
   * Whether geo terms should be linked.
   */
  protected bool $linkTerms = TRUE;

  /**
   * Maximum number of bundles to render in multi-bundles variant.
   */
  protected int $maxBundles = 6;

  /**
   * Constructs a LocationExtraFieldBuilder instance.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Builds the location extra field render array.
   */
  public function build(EntityInterface $listing, EntityViewDisplayInterface $display, string $langcode): ?array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($listing)
      ->addCacheableDependency($display);

    $this->linkTerms = (bool) $display->getThirdPartySetting('nomads_listing_virtual_fields', 'link_terms', TRUE);
    $this->maxBundles = max(1, (int) $display->getThirdPartySetting('nomads_listing_virtual_fields', 'max_bundles', 6));

    $paragraphs = $this->collectLocationParagraphs($listing, $langcode, $cacheability);
    if ($paragraphs === []) {
      return NULL;
    }

    $bundles = $this->normalizeBundles($paragraphs, $cacheability);
    if ($bundles === []) {
      return NULL;
    }

    $content = $this->buildAggregatedContent($bundles);
    if ($content === NULL) {
      return NULL;
    }

    $component = $display->getComponent('location_vfield') ?? [];
    $build = [
      '#theme' => 'location_virtual_field',
      '#label' => $this->t('Location vfield'),
      '#label_display' => (string) ($component['label'] ?? 'hidden'),
      '#wrapper_classes' => $content['classes'],
      '#content' => $content['content'],
    ];
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Collect location paragraphs from field_location_date.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Translated location paragraphs.
   */
  protected function collectLocationParagraphs(EntityInterface $listing, string $langcode, CacheableMetadata $cacheability): array {
    $paragraphs = [];
    if (!$listing->hasField('field_location_date') || $listing->get('field_location_date')->isEmpty()) {
      return $paragraphs;
    }

    foreach ($listing->get('field_location_date')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== 'location') {
        continue;
      }

      $translated = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph;
      $paragraphs[] = $translated;
      $cacheability->addCacheableDependency($translated);
    }

    return $paragraphs;
  }

  /**
   * Build normalized data for all location paragraphs.
   */
  protected function normalizeBundles(array $paragraphs, CacheableMetadata $cacheability): array {
    $bundles = [];

    foreach ($paragraphs as $paragraph) {
      $country_terms = $this->getCountryTerms($paragraph);
      foreach ($country_terms as $country_term) {
        $cacheability->addCacheableDependency($country_term);
      }

      $range = $this->extractDateRange($paragraph);
      $country_context = $this->buildCountryContext($country_terms);

      $bundles[] = [
        'country' => (string) ($country_context['country'] ?? ''),
        'country_markup' => (string) ($country_context['country_markup'] ?? ''),
        'breadcrumb' => (string) ($country_context['breadcrumb'] ?? ''),
        'breadcrumb_markup' => (string) ($country_context['breadcrumb_markup'] ?? ''),
        'tail' => (string) ($country_context['tail'] ?? ''),
        'tail_markup' => (string) ($country_context['tail_markup'] ?? ''),
        'places' => (array) ($country_context['places'] ?? []),
        'region_country_mode' => !empty($country_context['region_country_mode']),
        'date_from' => $range['from'],
        'date_to' => $range['to'],
        'has_date' => $range['from'] instanceof \DateTimeImmutable && $range['to'] instanceof \DateTimeImmutable,
        'freetagging' => '',
      ];
    }

    return $bundles;
  }

  /**
   * Build the single combined output.
   */
  protected function buildAggregatedContent(array $bundles): ?array {
    $bundle_count = count($bundles);
    $has_any_date = FALSE;
    foreach ($bundles as $bundle) {
      if (!empty($bundle['has_date'])) {
        $has_any_date = TRUE;
        break;
      }
    }

    $variant = $this->determineVariant($bundle_count, $has_any_date);
    if ($variant === '') {
      return NULL;
    }

    $filtered = FALSE;
    $rendered_bundles = $bundles;

    switch ($variant) {
      case 'single-location':
        $first = $bundles[0];
        $content = [
          '#type' => 'inline_template',
          '#template' => '<span>{{ location|raw }}</span>',
          '#context' => [
            'location' => $this->buildLocationFullMarkup($first),
          ],
        ];
        $render_count = 1;
        break;

      case 'single-range':
        $first = $bundles[0];
        $content = [
          '#type' => 'container',
          '#attributes' => ['class' => ['location-date__single-range']],
          'location' => [
            '#type' => 'inline_template',
            '#template' => '<span class="location">{{ location|raw }}</span>',
            '#context' => [
              'location' => $this->buildLocationFullMarkup($first),
            ],
          ],
          'sep' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => ['class' => ['sep']],
            '#plain_text' => ' : ',
          ],
          'date' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => ['class' => ['date']],
            '#plain_text' => $this->formatDateRange($first['date_from'], $first['date_to'], TRUE),
          ],
        ];
        $render_count = 1;
        break;

      case 'multi-countries':
        $countries = [];
        foreach ($bundles as $bundle) {
          $country = trim((string) ($bundle['country'] ?? ''));
          if ($country !== '') {
            $countries[$country] = (string) ($bundle['country_markup'] ?? Html::escape($country));
          }
        }

        if ($countries === []) {
          return NULL;
        }

        $content = [
          '#type' => 'inline_template',
          '#template' => '<span>{{ countries|raw }}</span>',
          '#context' => [
            'countries' => implode(', ', array_values($countries)),
          ],
        ];
        $render_count = $bundle_count;
        break;

      case 'multi-bundles':
        $rendered_bundles = $this->sortBundlesByStartDate($bundles);
        if (count($rendered_bundles) > $this->maxBundles) {
          $filtered = TRUE;
          $now = new \DateTimeImmutable('now');
          $rendered_bundles = array_values(array_filter($rendered_bundles, static function (array $bundle) use ($now): bool {
            return $bundle['date_to'] instanceof \DateTimeImmutable && $bundle['date_to'] > $now;
          }));
          $rendered_bundles = $this->sortBundlesByStartDate($rendered_bundles);
        }

        $rendered_bundles = array_slice($rendered_bundles, 0, $this->maxBundles);
        if ($rendered_bundles === []) {
          return NULL;
        }

        $content = [
          '#type' => 'container',
        ];

        foreach ($rendered_bundles as $index => $bundle) {
          $item = [
            '#type' => 'inline_template',
            '#template' => '<span class="bundle bundle{{ index }}"><span class="location">{{ location|raw }}</span>{% if date %}<span class="date">{{ date }}</span>{% endif %}</span>',
            '#context' => [
              'index' => $index + 1,
              'location' => (string) ($bundle['country_markup'] ?? Html::escape((string) ($bundle['country'] ?? ''))),
              'date' => !empty($bundle['has_date']) ? $this->formatDateRange($bundle['date_from'], $bundle['date_to'], FALSE) : '',
            ],
          ];

          $content['bundle_' . $index] = $item;
        }

        $render_count = count($rendered_bundles);
        break;

      default:
        return NULL;
    }

    $classes = [
      'location-date',
      'taxonomy-breadcrumb',
      $bundle_count === 1 ? 'location-date--single' : 'location-date--multi',
      'location-date--' . $variant,
      'location-date--count-' . $render_count,
    ];

    if ($filtered) {
      $classes[] = 'location-date--filtered';
    }

    if ($this->hasAnyValue($rendered_bundles, 'breadcrumb')) {
      $classes[] = 'location-date--has-breadcrumb';
    }
    if ($this->hasAnyNonEmptyArray($rendered_bundles, 'places')) {
      $classes[] = 'location-date--has-places';
    }
    if ($this->hasAnyValue($rendered_bundles, 'freetagging')) {
      $classes[] = 'location-date--has-freetagging';
    }

    return [
      'classes' => $classes,
      'content' => $content,
    ];
  }

  /**
   * Determine variant by bundle count and date presence.
   */
  protected function determineVariant(int $bundle_count, bool $has_any_date): string {
    if ($bundle_count === 1 && !$has_any_date) {
      return 'single-location';
    }
    if ($bundle_count === 1 && $has_any_date) {
      return 'single-range';
    }
    if ($bundle_count > 1 && !$has_any_date) {
      return 'multi-countries';
    }
    if ($bundle_count > 1 && $has_any_date) {
      return 'multi-bundles';
    }

    return '';
  }

  /**
   * Returns the country term when available.
   */
  protected function getCountryTerms(ParagraphInterface $paragraph): array {
    if (!$paragraph->hasField('field_country') || $paragraph->get('field_country')->isEmpty()) {
      return [];
    }

    $terms = [];
    foreach ($paragraph->get('field_country')->referencedEntities() as $term) {
      if ($term instanceof TermInterface) {
        $terms[] = $term;
      }
    }

    return $terms;
  }

  /**
   * Build country context from field_country terms only.
   */
  protected function buildCountryContext(array $country_terms): array {
    if ($country_terms === []) {
      return [
        'country' => '',
        'country_markup' => '',
        'breadcrumb' => '',
        'breadcrumb_markup' => '',
        'tail' => '',
        'tail_markup' => '',
        'places' => [],
        'region_country_mode' => FALSE,
      ];
    }

    $selected_terms = [];
    foreach (array_values($country_terms) as $index => $term) {
      $tid = (int) $term->id();
      if (isset($selected_terms[$tid])) {
        continue;
      }
      $selected_terms[$tid] = [
        'term' => $term,
        'index' => $index,
        'types' => $this->getTermTypeMap($term),
        'lineage' => $this->getTermLineage($term),
      ];
    }

    $breadcrumb_candidates = [];
    $explicit_country_candidates = [];
    $inferred_country_candidates = [];
    $place_terms = [];

    foreach ($selected_terms as $item) {
      $term = $item['term'];
      $types = $item['types'];

      if (isset($types['country'])) {
        $explicit_country_candidates[] = [
          'term' => $term,
          'depth' => count($item['lineage']),
          'index' => $item['index'],
        ];
      }
      elseif (isset($types['place']) || isset($types['free'])) {
        $place_terms[] = [
          'term' => $term,
          'index' => $item['index'],
        ];
      }

      foreach ($item['lineage'] as $ancestor) {
        $ancestor_types = $this->getTermTypeMap($ancestor);
        if ($this->isRegionTypeMap($ancestor_types) && isset($ancestor_types['breadcrumb'])) {
          $breadcrumb_candidates[(int) $ancestor->id()] = $ancestor;
        }
        if (isset($ancestor_types['country'])) {
          $inferred_country_candidates[(int) $ancestor->id()] = [
            'term' => $ancestor,
            'depth' => count($this->getTermLineage($ancestor)),
          ];
        }
      }
    }

    // If a region term is explicitly tagged in field_country, render as:
    // Region - Country1, Country2, ... (independent of breadcrumb tagging).
    $explicit_region_candidates = [];
    $explicit_country_terms = [];
    foreach ($selected_terms as $item) {
      $term = $item['term'];
      $types = $item['types'];
      if ($this->isRegionTypeMap($types)) {
        $explicit_region_candidates[] = [
          'term' => $term,
          'index' => $item['index'],
        ];
      }
      if (isset($types['country'])) {
        $explicit_country_terms[(int) $term->id()] = [
          'term' => $term,
          'index' => $item['index'],
        ];
      }
    }

    if ($explicit_region_candidates !== []) {
      usort($explicit_region_candidates, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);
      $region_term = $explicit_region_candidates[0]['term'];

      $country_items = array_values($explicit_country_terms);
      usort($country_items, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);
      $country_items = array_values(array_filter($country_items, static fn(array $item): bool => (int) $item['term']->id() !== (int) $region_term->id()));

      $country_labels = array_map(static fn(array $item): string => $item['term']->label(), $country_items);
      $country_markup_parts = array_map(fn(array $item): string => $this->buildTermMarkup($item['term']), $country_items);

      return [
        'country' => $region_term->label(),
        'country_markup' => $this->buildTermMarkup($region_term),
        'breadcrumb' => $region_term->label(),
        'breadcrumb_markup' => $this->buildTermMarkup($region_term),
        'tail' => implode(', ', $country_labels),
        'tail_markup' => implode(', ', $country_markup_parts),
        'places' => $country_labels,
        'region_country_mode' => TRUE,
      ];
    }

    $country_term = NULL;
    if ($explicit_country_candidates !== []) {
      usort($explicit_country_candidates, static function (array $a, array $b): int {
        if ($a['depth'] !== $b['depth']) {
          return $a['depth'] <=> $b['depth'];
        }
        return $a['index'] <=> $b['index'];
      });
      $country_term = $explicit_country_candidates[0]['term'];
    }
    elseif ($inferred_country_candidates !== []) {
      $inferred = array_values($inferred_country_candidates);
      usort($inferred, static function (array $a, array $b): int {
        if ($a['depth'] !== $b['depth']) {
          return $b['depth'] <=> $a['depth'];
        }
        return ((int) $a['term']->id()) <=> ((int) $b['term']->id());
      });
      $country_term = $inferred[0]['term'];
    }
    elseif ($selected_terms !== []) {
      $first = reset($selected_terms);
      $country_term = $first['term'];
    }

    $breadcrumb_terms = array_values($breadcrumb_candidates);
    usort($breadcrumb_terms, function (TermInterface $a, TermInterface $b): int {
      $depth_a = count($this->getTermLineage($a));
      $depth_b = count($this->getTermLineage($b));
      if ($depth_a !== $depth_b) {
        return $depth_a <=> $depth_b;
      }
      return ((int) $a->id()) <=> ((int) $b->id());
    });

    if ($country_term instanceof TermInterface) {
      $breadcrumb_has_country = FALSE;
      foreach ($breadcrumb_terms as $term) {
        if ((int) $term->id() === (int) $country_term->id()) {
          $breadcrumb_has_country = TRUE;
          break;
        }
      }
      if (!$breadcrumb_has_country) {
        $breadcrumb_terms[] = $country_term;
      }
    }

    usort($place_terms, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);
    $tail_parts = array_values(array_map(static fn(array $item): string => $item['term']->label(), $place_terms));
    $tail_markup_parts = array_values(array_map(fn(array $item): string => $this->buildTermMarkup($item['term']), $place_terms));

    $country_label = $country_term instanceof TermInterface ? $country_term->label() : '';
    $country_markup = $country_term instanceof TermInterface ? $this->buildTermMarkup($country_term) : '';

    return [
      'country' => $country_label,
      'country_markup' => $country_markup,
      'breadcrumb' => implode(' > ', array_map(static fn(TermInterface $term): string => $term->label(), $breadcrumb_terms)),
      'breadcrumb_markup' => implode(' &gt; ', array_map(fn(TermInterface $term): string => $this->buildTermMarkup($term), $breadcrumb_terms)),
      'tail' => implode(', ', $tail_parts),
      'tail_markup' => implode(', ', $tail_markup_parts),
      'places' => $tail_parts,
      'region_country_mode' => FALSE,
    ];
  }

  /**
   * Get taxonomy lineage from root to current term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Root-first lineage.
   */
  protected function getTermLineage(TermInterface $term): array {
    $lineage = [];
    $seen = [];
    $current = $term;

    while ($current instanceof TermInterface) {
      $id = (int) $current->id();
      if (isset($seen[$id])) {
        break;
      }
      $seen[$id] = TRUE;
      array_unshift($lineage, $current);

      if (!$current->hasField('parent') || $current->get('parent')->isEmpty()) {
        break;
      }

      $parent = $current->get('parent')->entity;
      if (!$parent instanceof TermInterface) {
        break;
      }
      $current = $parent;
    }

    return $lineage;
  }

  /**
   * Get common prefix terms from multiple term lineages.
   *
   * @param \Drupal\taxonomy\TermInterface[][] $lineages
   *   Root-first term lineages.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Shared prefix lineage.
   */
  protected function getCommonLineagePrefix(array $lineages): array {
    if ($lineages === []) {
      return [];
    }

    $prefix = array_shift($lineages);
    foreach ($lineages as $lineage) {
      $max = min(count($prefix), count($lineage));
      $common = [];
      for ($i = 0; $i < $max; $i++) {
        if ((int) $prefix[$i]->id() !== (int) $lineage[$i]->id()) {
          break;
        }
        $common[] = $prefix[$i];
      }
      $prefix = $common;
      if ($prefix === []) {
        break;
      }
    }

    return $prefix;
  }

  /**
   * Decide if an ancestor term should appear in breadcrumb context.
   *
   * Regions are only included when field_type contains "breadcrumb".
   * Countries are always included.
   */
  protected function shouldIncludeInBreadcrumb(TermInterface $term): bool {
    $types = $this->getTermTypeMap($term);
    if ($types === []) {
      return TRUE;
    }

    if (isset($types['country'])) {
      return TRUE;
    }

    $is_region = $this->isRegionTypeMap($types);
    if ($is_region) {
      return isset($types['breadcrumb']);
    }

    return TRUE;
  }

  /**
   * Build field_type map for a taxonomy term.
   */
  protected function getTermTypeMap(TermInterface $term): array {
    if (!$term->hasField('field_type') || $term->get('field_type')->isEmpty()) {
      return [];
    }

    $types = [];
    foreach ($term->get('field_type')->getValue() as $item) {
      $value = trim((string) ($item['value'] ?? ''));
      if ($value !== '') {
        $types[$value] = TRUE;
      }
    }

    return $types;
  }

  /**
   * Build a safe link/plain-text fragment for a term.
   */
  protected function buildTermMarkup(TermInterface $term): string {
    $label = Html::escape($term->label());
    $class = Html::escape($this->getTermSemanticClass($term));
    if (!$this->shouldLinkGeoTerm($term)) {
      return '<span class="' . $class . '">' . $label . '</span>';
    }

    return '<span class="' . $class . '"><a href="' . Html::escape($term->toUrl()->toString()) . '">' . $label . '</a></span>';
  }

  /**
   * Determine if a term should be linked in teaser output.
   *
   * Link region/country/place terms, except free-tagging terms.
   */
  protected function shouldLinkGeoTerm(TermInterface $term): bool {
    if (!$this->linkTerms) {
      return FALSE;
    }

    $types = $this->getTermTypeMap($term);
    if (isset($types['free'])) {
      return FALSE;
    }

    $is_geo = $this->isRegionTypeMap($types) || isset($types['country']) || isset($types['place']);
    return $is_geo;
  }

  /**
   * Resolve a semantic CSS class for a term.
   */
  protected function getTermSemanticClass(TermInterface $term): string {
    $types = $this->getTermTypeMap($term);

    if (isset($types['free'])) {
      return 'free';
    }
    if (isset($types['place'])) {
      return 'place';
    }
    if (isset($types['country'])) {
      return 'country';
    }

    $is_region = $this->isRegionTypeMap($types);
    if ($is_region) {
      return 'region';
    }

    return 'free';
  }

  /**
   * Extract date range from field_date.
   */
  protected function extractDateRange(ParagraphInterface $paragraph): array {
    $result = ['from' => NULL, 'to' => NULL];
    if (!$paragraph->hasField('field_date') || $paragraph->get('field_date')->isEmpty()) {
      return $result;
    }

    $item = $paragraph->get('field_date')->first();
    if (!$item) {
      return $result;
    }

    $from = trim((string) ($item->value ?? ''));
    $to = trim((string) ($item->end_value ?? ''));

    $from_date = $this->parseDate($from);
    $to_date = $this->parseDate($to);

    if (!$from_date instanceof \DateTimeImmutable || !$to_date instanceof \DateTimeImmutable) {
      return $result;
    }

    if ($to_date < $from_date) {
      return $result;
    }

    $result['from'] = $from_date;
    $result['to'] = $to_date;

    return $result;
  }

  /**
   * Parse date string into immutable date.
   */
  protected function parseDate(string $value): ?\DateTimeImmutable {
    if ($value === '') {
      return NULL;
    }

    $timestamp = strtotime($value);
    if ($timestamp === FALSE) {
      return NULL;
    }

    return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
  }

  /**
   * Extract additional place labels from location paragraph.
   */
  protected function extractPlaces(ParagraphInterface $paragraph): array {
    $places = [];

    foreach (['field_settlement', 'field_surroundings'] as $field_name) {
      if (!$paragraph->hasField($field_name) || $paragraph->get($field_name)->isEmpty()) {
        continue;
      }

      $allowed = $paragraph->get($field_name)->getFieldDefinition()->getSetting('allowed_values') ?? [];
      foreach ($paragraph->get($field_name)->getValue() as $value) {
        $key = (string) ($value['value'] ?? '');
        if ($key === '') {
          continue;
        }
        $places[] = (string) ($allowed[$key] ?? $key);
      }
    }

    return array_values(array_unique(array_filter($places, static fn(string $value): bool => trim($value) !== '')));
  }

  /**
   * Extract free tagging label for class state.
   */
  protected function extractFreeTagging(ParagraphInterface $paragraph): string {
    if (!$paragraph->hasField('field_title') || $paragraph->get('field_title')->isEmpty()) {
      return '';
    }

    return trim((string) ($paragraph->get('field_title')->value ?? ''));
  }

  /**
   * Build a full location label from normalized bundle data.
   */
  protected function buildLocationFullMarkup(array $bundle): string {
    $breadcrumb = trim((string) ($bundle['breadcrumb'] ?? ''));
    $breadcrumb_markup = trim((string) ($bundle['breadcrumb_markup'] ?? ''));
    $tail = trim((string) ($bundle['tail'] ?? ''));
    $tail_markup = trim((string) ($bundle['tail_markup'] ?? ''));
    $country = trim((string) ($bundle['country'] ?? ''));
    $country_markup = trim((string) ($bundle['country_markup'] ?? ''));
    $region_country_mode = !empty($bundle['region_country_mode']);

    if ($breadcrumb !== '' && $tail !== '') {
      $tail_count = !empty($bundle['places']) && is_array($bundle['places']) ? count($bundle['places']) : 0;
      $separator = $region_country_mode ? ' - ' : ($tail_count <= 1 ? ' &gt; ' : ' - ');
      return ($breadcrumb_markup !== '' ? $breadcrumb_markup : Html::escape($breadcrumb)) . $separator . ($tail_markup !== '' ? $tail_markup : Html::escape($tail));
    }
    if ($breadcrumb !== '') {
      return $breadcrumb_markup !== '' ? $breadcrumb_markup : Html::escape($breadcrumb);
    }
    if ($tail !== '') {
      return $tail_markup !== '' ? $tail_markup : Html::escape($tail);
    }

    return $country_markup !== '' ? $country_markup : Html::escape($country);
  }

  /**
   * TRUE when a type map matches a region-like taxonomy term.
   */
  protected function isRegionTypeMap(array $types): bool {
    return isset($types['region']) || isset($types['continent']) || isset($types['continental_subregion']) || isset($types['subregion']);
  }

  /**
   * Format the date range as month output.
   */
  protected function formatDateRange(?\DateTimeImmutable $from, ?\DateTimeImmutable $to, bool $full): string {
    if (!$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable) {
      return '';
    }

    $months = $this->selectRangeMonths($from, $to);
    if ($months === []) {
      return '';
    }

    if (count($months) === 1) {
      return $this->formatMonthLabel($months[0], $full);
    }

    $first = $months[0];
    $second = $months[1];

    $first_include_year = $this->shouldIncludeYearForMonth($first);
    $second_include_year = $this->shouldIncludeYearForMonth($second);
    $same_year = $first->format('Y') === $second->format('Y');

    if ($first_include_year && $second_include_year && $same_year) {
      return $this->formatMonthLabel($first, $full, FALSE) . ' - ' . $this->formatMonthLabel($second, $full, FALSE) . ' ' . $this->formatYearLabel($second, $full);
    }

    return $this->formatMonthLabel($first, $full) . ' - ' . $this->formatMonthLabel($second, $full);
  }

  /**
   * Formats month label for full/short variants.
   *
   * Months with full names up to 5 letters are never shortened.
   */
  protected function formatMonthLabel(\DateTimeImmutable $date, bool $full, ?bool $include_year_override = NULL): string {
    $full_name = $date->format('F');
    $include_year = $include_year_override ?? $this->shouldIncludeYearForMonth($date);
    if ($full || strlen($full_name) <= 5) {
      return $full_name . ($include_year ? ' ' . $this->formatYearLabel($date, $full) : '');
    }

    return $date->format('M') . ($include_year ? ' ' . $this->formatYearLabel($date, $full) : '');
  }

  /**
   * Formats year suffix for full/short date variants.
   */
  protected function formatYearLabel(\DateTimeImmutable $date, bool $full): string {
    return $date->format($full ? 'Y' : 'y');
  }

  /**
   * TRUE when month labels should include the year.
   *
   * Include year when the month is in the past, or 12+ months in the future.
   */
  protected function shouldIncludeYearForMonth(\DateTimeImmutable $date): bool {
    $now = new \DateTimeImmutable('now');

    $current_index = ((int) $now->format('Y') * 12) + (int) $now->format('n');
    $target_index = ((int) $date->format('Y') * 12) + (int) $date->format('n');
    $month_delta = $target_index - $current_index;

    return $month_delta < 0 || $month_delta > 11;
  }

  /**
   * Sort bundles by start date, pushing no-date bundles to the end.
   */
  protected function sortBundlesByStartDate(array $bundles): array {
    usort($bundles, static function (array $a, array $b): int {
      $a_has = $a['date_from'] instanceof \DateTimeImmutable;
      $b_has = $b['date_from'] instanceof \DateTimeImmutable;

      if ($a_has && $b_has) {
        return $a['date_from']->getTimestamp() <=> $b['date_from']->getTimestamp();
      }
      if ($a_has) {
        return -1;
      }
      if ($b_has) {
        return 1;
      }

      return strcmp((string) ($a['country'] ?? ''), (string) ($b['country'] ?? ''));
    });

    return $bundles;
  }

  /**
   * Month selection logic for date ranges.
   *
   * Rules:
   * - Same month: single month.
   * - Different months:
   *   - if end-month covered days <= 3, use start month.
   *   - else if start-month covered days <= 3, use end month.
   *   - else use both months.
   * - Fallback: if single month was chosen but that chosen month has < 7 covered
   *   days and the other month has > 3 covered days, use both months.
   */
  private function selectRangeMonths(\DateTimeImmutable $from, \DateTimeImmutable $to): array {
    if ($to < $from) {
      return [];
    }

    $same_month = $from->format('Y-m') === $to->format('Y-m');
    if ($same_month) {
      return [$from];
    }

    $start_month_end = $from->modify('last day of this month')->setTime(23, 59, 59);
    $end_month_start = $to->modify('first day of this month')->setTime(0, 0, 0);

    $d_from = (int) floor(($start_month_end->getTimestamp() - $from->getTimestamp()) / 86400) + 1;
    $d_to = (int) floor(($to->getTimestamp() - $end_month_start->getTimestamp()) / 86400) + 1;
    $month_span = ((int) $to->format('Y') - (int) $from->format('Y')) * 12 + ((int) $to->format('n') - (int) $from->format('n'));

    // For ranges spanning 3+ calendar months, trim tiny edge months and keep
    // the first/last meaningful months in the span.
    if ($month_span >= 2) {
      $display_from = $from;
      $display_to = $to;

      if ($d_from <= 3) {
        $display_from = $from->modify('first day of next month');
      }
      if ($d_to <= 3) {
        $display_to = $to->modify('first day of previous month');
      }

      if ($display_to < $display_from) {
        return [$from];
      }
      if ($display_from->format('Y-m') === $display_to->format('Y-m')) {
        return [$display_from];
      }

      return [$display_from, $display_to];
    }

    $chosen = 'both';
    if ($d_to <= 3) {
      $chosen = 'start';
    }
    elseif ($d_from <= 3) {
      $chosen = 'end';
    }

    if ($chosen === 'start' && $d_from < 7 && $d_to > 3) {
      $chosen = 'both';
    }
    elseif ($chosen === 'end' && $d_to < 7 && $d_from > 3) {
      $chosen = 'both';
    }

    return match ($chosen) {
      'start' => [$from],
      'end' => [$to],
      default => [$from, $to],
    };
  }

  /**
   * TRUE when any bundle has a non-empty scalar for key.
   */
  protected function hasAnyValue(array $bundles, string $key): bool {
    foreach ($bundles as $bundle) {
      $value = trim((string) ($bundle[$key] ?? ''));
      if ($value !== '') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * TRUE when any bundle has a non-empty array for key.
   */
  protected function hasAnyNonEmptyArray(array $bundles, string $key): bool {
    foreach ($bundles as $bundle) {
      if (!empty($bundle[$key]) && is_array($bundle[$key])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
