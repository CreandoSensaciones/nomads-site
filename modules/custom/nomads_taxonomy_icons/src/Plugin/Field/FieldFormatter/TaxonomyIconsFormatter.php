<?php

namespace Drupal\nomads_taxonomy_icons\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\MediaInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter for taxonomy term reference fields with icons.
 */
#[FieldFormatter(
  id: 'nomads_taxonomy_icons',
  label: new TranslatableMarkup('Taxonomy icons'),
  field_types: [
    'entity_reference',
  ],
)]
class TaxonomyIconsFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a TaxonomyIconsFormatter instance.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'image_style' => '',
      'css_classes' => '',
      'first_item_css_classes' => '',
      'pills_after_first' => FALSE,
      'first_level_categories' => FALSE,
      'link_label' => FALSE,
      'link_icon' => FALSE,
      'max_number' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $style_options = function_exists('image_style_options') ? image_style_options(TRUE) : [
      '' => $this->t('None (original image)'),
    ];

    $element['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style'),
      '#default_value' => $this->getSetting('image_style'),
      '#options' => $style_options,
    ];

    $element['css_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS Classes'),
      '#default_value' => $this->getSetting('css_classes'),
      '#description' => $this->t('Space-separated list of classes applied to each item wrapper.'),
    ];

    $element['first_item_css_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First item CSS classes'),
      '#default_value' => $this->getSetting('first_item_css_classes'),
      '#description' => $this->t('Space-separated list of classes applied only to the first item wrapper.'),
    ];

    $element['pills_after_first'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pills after first item'),
      '#default_value' => $this->getSetting('pills_after_first'),
    ];

    $element['first_level_categories'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('First level items turn into categories'),
      '#default_value' => $this->getSetting('first_level_categories'),
    ];

    $element['link_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link label to the referenced entity'),
      '#default_value' => $this->getSetting('link_label'),
    ];

    $element['link_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link icon to the referenced entity'),
      '#default_value' => $this->getSetting('link_icon'),
    ];

    $element['max_number'] = [
      '#type' => 'number',
      '#title' => $this->t('Max. number'),
      '#default_value' => $this->getSetting('max_number'),
      '#min' => 0,
      '#description' => $this->t('Set to 0 for no limit. When the rendered item count exceeds this number, only terms marked with "primary" are shown directly and the rest are collapsible. In category mode, each category label also counts as one item.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $image_style = (string) $this->getSetting('image_style');
    $css_classes = trim((string) $this->getSetting('css_classes'));
    $first_item_classes = trim((string) $this->getSetting('first_item_css_classes'));

    return [
      $this->t('Image style: @style', [
        '@style' => $image_style !== '' ? $image_style : $this->t('Original'),
      ]),
      $this->t('CSS classes: @classes', [
        '@classes' => $css_classes !== '' ? $css_classes : $this->t('None'),
      ]),
      $this->t('First item CSS classes: @classes', [
        '@classes' => $first_item_classes !== '' ? $first_item_classes : $this->t('None'),
      ]),
      $this->t('Pills after first item: @value', [
        '@value' => $this->getSetting('pills_after_first') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('First level categories: @value', [
        '@value' => $this->getSetting('first_level_categories') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Link label: @value', [
        '@value' => $this->getSetting('link_label') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Link icon: @value', [
        '@value' => $this->getSetting('link_icon') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Max. number: @value', [
        '@value' => ((int) $this->getSetting('max_number') <= 0) ? $this->t('Unlimited') : $this->getSetting('max_number'),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $base_classes = $this->splitClasses((string) $this->getSetting('css_classes'));
    $first_item_classes = $this->splitClasses((string) $this->getSetting('first_item_css_classes'));
    $pills_after_first = (bool) $this->getSetting('pills_after_first');
    $first_level_categories = (bool) $this->getSetting('first_level_categories');
    $link_label = (bool) $this->getSetting('link_label');
    $link_icon = (bool) $this->getSetting('link_icon');
    $max_number = max(0, (int) $this->getSetting('max_number'));

    $source_items = $this->getRenderableItems($items);

    $entries = [];
    foreach ($source_items as $delta => $item) {
      $term = $this->resolveTermFromItem($item);
      if (!$term) {
        continue;
      }
      $entries[] = [
        'delta' => $delta,
        'item' => $item,
        'term' => $term,
      ];
    }

    if ($entries === []) {
      return [];
    }

    $field_name = $items->getFieldDefinition()->getName();
    if ($field_name === 'field_type') {
      $entries = $this->sortEntriesByDelta($entries);
    }
    else {
      $entries = $this->sortEntriesByVocabularyTree($entries);
    }

    if ($first_level_categories) {
      $grouped_elements = $this->buildGroupedElements(
        $entries,
        $base_classes,
        $first_item_classes,
        $pills_after_first,
        $link_label,
        $link_icon,
        $max_number,
      );
      if (!empty($grouped_elements)) {
        return $grouped_elements;
      }
    }

    return $this->buildFlatElements(
      $entries,
      $base_classes,
      $first_item_classes,
      $pills_after_first,
      $link_label,
      $link_icon,
      $max_number,
    );
  }

  /**
   * Build flat output (non-grouped mode).
   */
  protected function buildFlatElements(
    array $entries,
    array $base_classes,
    array $first_item_classes,
    bool $pills_after_first,
    bool $link_label,
    bool $link_icon,
    int $max_number,
  ): array {
    $over_limit = $max_number > 0 && count($entries) > $max_number;
    $visible_entries = $entries;
    $hidden_entries = [];

    if ($over_limit) {
      [$visible_entries, $hidden_entries] = $this->splitEntriesByPrimary($entries);
    }

    $elements = [];
    $pill_items = [];
    foreach (array_values($visible_entries) as $index => $entry) {
      $delta = $entry['delta'];
      $item = $entry['item'];
      $term = $entry['term'];

      $classes = $this->buildItemClasses($base_classes, $first_item_classes, $pills_after_first, $index);
      $element = $this->buildTermElement($term, $index, $pills_after_first, $link_label, $link_icon);

      if ($pills_after_first && $index > 0) {
        $pill_items[] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => $classes,
          ],
          'content' => $element,
        ];
      }
      else {
        $item_attributes = (array) ($item->_attributes ?? []);
        $item_attributes['class'] = array_values(array_unique(array_merge($item_attributes['class'] ?? [], $classes)));
        $item->_attributes = $item_attributes;
        $elements[] = $element;
      }
    }

    if ($pills_after_first && $pill_items !== []) {
      $elements[] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-pills', 'nomads-pills--taxonomy-icons'],
        ],
        'items' => $pill_items,
      ];
    }

    if ($hidden_entries !== []) {
      $elements[] = $this->buildCollapsibleElement(
        $hidden_entries,
        $base_classes,
        $first_item_classes,
        $pills_after_first,
        $link_label,
        $link_icon,
        [],
        count($visible_entries),
      );
    }

    return $elements;
  }

  /**
   * Build grouped output using first-level terms as section titles.
   */
  protected function buildGroupedElements(
    array $entries,
    array $base_classes,
    array $first_item_classes,
    bool $pills_after_first,
    bool $link_label,
    bool $link_icon,
    int $max_number,
  ): array {
    $category_cache = [];
    $groups = [];
    $group_order = [];
    $top_level_entries = [];

    foreach ($entries as $entry) {
      $term = $entry['term'];
      if (!$term) {
        continue;
      }

      $category_term = $this->getTopLevelTerm($term, $category_cache);
      if (!$category_term) {
        continue;
      }

      $category_id = (int) $category_term->id();
      if (!isset($groups[$category_id])) {
        $groups[$category_id] = [
          'term' => $category_term,
          'items' => [],
        ];
        $group_order[] = $category_id;
      }

      if ((int) $term->id() === $category_id) {
        $top_level_entries[$category_id][] = $entry;
        continue;
      }

      $groups[$category_id]['items'][] = $entry;
    }

    $renderable_groups = [];
    foreach ($group_order as $category_id) {
      $group = $groups[$category_id];
      $items = $group['items'];
      if ($items === [] && !empty($top_level_entries[$category_id])) {
        $items = $top_level_entries[$category_id];
      }
      if ($items === []) {
        continue;
      }

      $renderable_groups[$category_id] = [
        'term' => $group['term'],
        'items' => array_values($items),
      ];
    }

    if ($renderable_groups === []) {
      return [];
    }

    $term_count = 0;
    foreach ($renderable_groups as $group) {
      $term_count += count($group['items']);
    }
    $rendered_item_count = $term_count + count($renderable_groups);
    $over_limit = $max_number > 0 && $rendered_item_count > $max_number;

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'nomads-icon-formatter',
          'nomads-icon-formatter--taxonomy-icons',
          'nomads-taxonomy-icons--grouped',
        ],
      ],
      '#attached' => [
        'library' => [
          'nomads_taxonomy_icons/taxonomy-icons',
        ],
      ],
    ];
    $has_groups = FALSE;

    foreach ($renderable_groups as $category_id => $group) {
      $category_term = $group['term'];
      $items = $group['items'];
      $visible_entries = $items;
      $hidden_entries = [];
      if ($over_limit) {
        [$visible_entries, $hidden_entries] = $this->splitEntriesByPrimary($items);
      }

      if ($visible_entries === [] && $hidden_entries === []) {
        continue;
      }

      $group_build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'nomads-taxonomy-icons__group',
            'nomads-icon-formatter__group',
          ],
        ],
      ];

      $group_build['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => Html::escape($category_term->label()),
        '#attributes' => [
          'class' => [
            'nomads-icon-formatter__title',
            'nomads-taxonomy-icons__group-title',
          ],
        ],
      ];

      $items_classes = [
        'nomads-taxonomy-icons__items',
      ];
      if ($pills_after_first) {
        $items_classes[] = 'nomads-pills';
        $items_classes[] = 'nomads-pills--taxonomy-icons';
      }

      $group_build['items'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => $items_classes,
        ],
      ];

      foreach (array_values($visible_entries) as $index => $entry) {
        $term = $entry['term'] ?? NULL;
        if (!$term) {
          continue;
        }

        $classes = $this->buildItemClasses($base_classes, $first_item_classes, $pills_after_first, $index);
        $element = $this->buildTermElement($term, $index, $pills_after_first, $link_label, $link_icon);

        $group_build['items']['visible_' . $index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => $classes,
          ],
          'content' => $element,
        ];
      }

      if ($hidden_entries !== []) {
        $group_build['collapsible'] = $this->buildCollapsibleElement(
          $hidden_entries,
          $base_classes,
          $first_item_classes,
          $pills_after_first,
          $link_label,
          $link_icon,
          ['nomads-taxonomy-icons__collapsible--category'],
          count($visible_entries),
        );
      }

      $build['group_' . $category_id] = $group_build;
      $has_groups = TRUE;
    }

    return $has_groups ? [0 => $build] : [];
  }

  /**
   * Split entries into visible (primary) and hidden terms.
   */
  protected function splitEntriesByPrimary(array $entries): array {
    $visible = [];
    $hidden = [];

    foreach (array_values($entries) as $entry) {
      $term = $entry['term'] ?? NULL;
      if ($term && $this->isPrimaryTerm($term)) {
        $visible[] = $entry;
      }
      else {
        $hidden[] = $entry;
      }
    }

    if ($visible === [] && $hidden !== []) {
      $visible[] = array_shift($hidden);
    }

    return [$visible, $hidden];
  }

  /**
   * Build CSS classes for one term item wrapper.
   */
  protected function buildItemClasses(
    array $base_classes,
    array $first_item_classes,
    bool $pills_after_first,
    int $index,
  ): array {
    $classes = $base_classes;
    if ($index === 0 && !empty($first_item_classes)) {
      $classes = array_merge($classes, $first_item_classes);
    }
    $classes[] = 'nomads-taxonomy-icons__item';
    $classes[] = 'nomads-icon-formatter__item';
    if ($pills_after_first && $index > 0) {
      $classes[] = 'nomads-taxonomy-icons__item--pill';
    }

    return $classes;
  }

  /**
   * Build a collapsible wrapper for hidden term entries.
   */
  protected function buildCollapsibleElement(
    array $entries,
    array $base_classes,
    array $first_item_classes,
    bool $pills_after_first,
    bool $link_label,
    bool $link_icon,
    array $extra_wrapper_classes = [],
    int $index_offset = 0,
  ): array {
    $wrapper_id = Html::getUniqueId('nomads-taxonomy-icons-collapsible');
    $content_classes = ['nomads-taxonomy-icons__collapsible-content'];
    if ($pills_after_first) {
      $content_classes[] = 'nomads-pills';
      $content_classes[] = 'nomads-pills--taxonomy-icons';
    }

    $wrapper_classes = array_merge(['nomads-taxonomy-icons__collapsible'], $extra_wrapper_classes);
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $wrapper_classes,
      ],
    ];

    $build['toggle'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => '',
      '#attributes' => [
        'type' => 'button',
        'class' => ['nomads-taxonomy-icons__collapsible-toggle'],
        'aria-expanded' => 'false',
        'aria-controls' => $wrapper_id,
      ],
    ];

    $build['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => $content_classes,
      ],
    ];

    foreach (array_values($entries) as $index => $entry) {
      $term = $entry['term'] ?? NULL;
      if (!$term) {
        continue;
      }
      $effective_index = $index + $index_offset;

      $classes = $this->buildItemClasses($base_classes, $first_item_classes, $pills_after_first, $effective_index);
      $element = $this->buildTermElement($term, $effective_index, $pills_after_first, $link_label, $link_icon);

      $build['content']['item_' . $index] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => $classes,
        ],
        'content' => $element,
      ];
    }

    return $build;
  }

  /**
   * Check if a term carries the "primary" setting.
   */
  protected function isPrimaryTerm($term): bool {
    $field_names = [
      'field_setting',
      'field_settings',
      'field_easy_tagging_settings',
      'field_easy_tagging_behavior',
    ];

    foreach ($field_names as $field_name) {
      if (!$term->hasField($field_name) || $term->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($term->get($field_name)->getValue() as $item) {
        if (!isset($item['value'])) {
          continue;
        }
        $normalized = $this->normalizeSettingMachineName((string) $item['value']);
        if (in_array($normalized, ['primary', 'primery'], TRUE)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Normalize machine names to a stable comparison key.
   */
  protected function normalizeSettingMachineName(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace([' ', '-'], '_', $value);
    return preg_replace('/_+/', '_', $value) ?? '';
  }

  /**
   * Build the render array for a single term item.
   */
  protected function buildTermElement(
    $term,
    int $index,
    bool $pills_after_first,
    bool $link_label,
    bool $link_icon,
  ): array {
    $element = [];

    $show_icon = !$pills_after_first || $index === 0;
    if ($show_icon) {
      $element['icon'] = $this->buildIconElement($term, $link_icon);
    }

    $label_text = Html::escape($term->label());
    $label_value = $label_text;
    if ($link_label) {
      $label_value = [
        '#type' => 'link',
        '#title' => $label_text,
        '#url' => $term->toUrl(),
        '#attributes' => [
          'class' => ['nomads-pill__link'],
        ],
      ];
    }

    if ($pills_after_first && $index > 0) {
      $element['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label_text,
        '#attributes' => [
          'class' => ['nomads-taxonomy-icons__pill', 'nomads-pill', 'nomads-pill__label'],
        ],
      ];
      if ($link_label) {
        $element['label']['#value'] = '';
        $element['label']['link'] = $label_value;
      }
    }
    else {
      $element['label'] = [
        '#type' => $link_label ? 'container' : 'html_tag',
        '#tag' => $link_label ? NULL : 'span',
        '#value' => $link_label ? NULL : $label_text,
        '#attributes' => [
          'class' => ['nomads-taxonomy-icons__label', 'nomads-icon-formatter__label'],
        ],
      ];
      if ($link_label) {
        $element['label']['link'] = $label_value;
      }
    }

    $element['#attached']['library'][] = 'nomads_taxonomy_icons/taxonomy-icons';

    return $element;
  }

  /**
   * Find the top-level term for a taxonomy term.
   */
  protected function getTopLevelTerm($term, array &$cache) {
    $term_id = (int) $term->id();
    if (isset($cache[$term_id])) {
      return $cache[$term_id];
    }

    $visited = [];
    $current = $term;
    while ($current && $current->hasField('parent') && !$current->get('parent')->isEmpty()) {
      $parents = $current->get('parent')->referencedEntities();
      $parent = reset($parents);
      if (!$parent) {
        break;
      }
      $parent_id = (int) $parent->id();
      if (isset($visited[$parent_id])) {
        break;
      }
      $visited[$parent_id] = TRUE;
      $current = $parent;
    }

    $cache[$term_id] = $current ?: $term;
    return $cache[$term_id];
  }

  /**
   * Build the term icon render array from field_icons.
   */
  protected function buildTermIcon($term): ?array {
    if (!$term->hasField('field_icons') || $term->get('field_icons')->isEmpty()) {
      return NULL;
    }

    $icon_field = $term->get('field_icons');
    $icon_item = $icon_field->first();
    if (!$icon_item) {
      return NULL;
    }

    $image_style = (string) $this->getSetting('image_style');
    $field_type = $icon_field->getFieldDefinition()->getType();

    if ($field_type === 'image') {
      $file = $icon_item->entity;
      if (!$file && !empty($icon_item->target_id)) {
        $file = $this->entityTypeManager->getStorage('file')->load($icon_item->target_id);
      }
      if (!$file) {
        return NULL;
      }
      $uri = $file->getFileUri();
      $alt = $icon_item->alt ?? '';
      $title = $icon_item->title ?? '';
      return $this->buildImageRenderArray($uri, $alt, $title, $image_style);
    }

    $entity = $icon_item->entity;
    if (!$entity && !empty($icon_item->target_id)) {
      $target_type = $icon_field->getFieldDefinition()->getSetting('target_type');
      if ($target_type) {
        $entity = $this->entityTypeManager->getStorage($target_type)->load($icon_item->target_id);
      }
    }
    if ($entity instanceof MediaInterface) {
      $uri = $this->getMediaImageUri($entity);
      if (!$uri) {
        return NULL;
      }
      return $this->buildImageRenderArray($uri, $entity->label() ?? '', '', $image_style);
    }

    return NULL;
  }

  /**
   * Build the icon wrapper with image icon or fallback SVG.
   */
  protected function buildIconElement($term, bool $link_icon): array {
    $icon = $this->buildTermIcon($term) ?? $this->buildMissingIconRenderArray();

    $element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-taxonomy-icons__icon', 'nomads-icon-formatter__icon'],
      ],
      'media' => $icon,
    ];

    if ($link_icon) {
      $element['media'] = [
        '#type' => 'link',
        '#title' => $icon,
        '#url' => $term->toUrl(),
      ];
    }

    return $element;
  }

  /**
   * Build a default SVG circle icon when no icon is available.
   */
  protected function buildMissingIconRenderArray(): array {
    return [
      '#markup' => Markup::create('<svg class="nomads-taxonomy-icons__missing-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-hidden="true" focusable="false"><circle cx="16" cy="16" r="14" fill="#f6f6f6" /></svg>'),
    ];
  }

  /**
   * Build an image render array with an optional style.
   */
  protected function buildImageRenderArray(string $uri, string $alt, string $title, string $style_name): array {
    if ($style_name !== '') {
      $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
      if ($style) {
        $original_extension = pathinfo($uri, PATHINFO_EXTENSION);
        $derivative_extension = $style->getDerivativeExtension($original_extension);
        if ($derivative_extension !== $original_extension) {
          $derivative_uri = $style->buildUri($uri);
          if (!file_exists($derivative_uri)) {
            $style->createDerivative($uri, $derivative_uri);
          }
          if (file_exists($derivative_uri)) {
            return [
              '#theme' => 'image',
              '#uri' => $derivative_uri,
              '#alt' => $alt,
              '#title' => $title,
            ];
          }
        }
      }

      return [
        '#theme' => 'image_style',
        '#style_name' => $style_name,
        '#uri' => $uri,
        '#alt' => $alt,
        '#title' => $title,
      ];
    }

    return [
      '#theme' => 'image',
      '#uri' => $uri,
      '#alt' => $alt,
      '#title' => $title,
    ];
  }

  /**
   * Resolve image URI from a media entity.
   */
  protected function getMediaImageUri(MediaInterface $media): ?string {
    $source = $media->getSource();
    if (!$source) {
      return NULL;
    }

    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$media_type) {
      return $this->getMediaFallbackImageUri($media);
    }

    $source_field_definition = $source->getSourceFieldDefinition($media_type);
    if (!$source_field_definition) {
      return $this->getMediaFallbackImageUri($media);
    }

    $source_field = $source_field_definition->getName();
    if (!$media->hasField($source_field)) {
      return $this->getMediaFallbackImageUri($media);
    }

    $image_item = $media->get($source_field)->first();
    if (!$image_item) {
      return $this->getMediaFallbackImageUri($media);
    }
    if (empty($image_item->entity) && !empty($image_item->target_id)) {
      $image_item->entity = $this->entityTypeManager->getStorage('file')->load($image_item->target_id);
    }
    if (empty($image_item->entity)) {
      return $this->getMediaFallbackImageUri($media);
    }

    $file = $image_item->entity;
    return $file->getFileUri();
  }

  /**
   * Fallback: return the first image field file URI on a media entity.
   */
  protected function getMediaFallbackImageUri(MediaInterface $media): ?string {
    $definitions = $media->getFieldDefinitions();
    foreach ($definitions as $field_name => $definition) {
      if ($definition->getType() !== 'image') {
        continue;
      }
      if (!$media->hasField($field_name) || $media->get($field_name)->isEmpty()) {
        continue;
      }
      $image_item = $media->get($field_name)->first();
      if ($image_item && !empty($image_item->entity)) {
        return $image_item->entity->getFileUri();
      }
    }

    return NULL;
  }

  /**
   * Split a class string into an array.
   */
  protected function splitClasses(string $classes): array {
    $classes = trim($classes);
    if ($classes === '') {
      return [];
    }

    return preg_split('/\s+/', $classes) ?: [];
  }

  /**
   * Resolve a referenced taxonomy term from a field item.
   */
  protected function resolveTermFromItem($item) {
    if (!empty($item->entity)) {
      return $item->entity;
    }

    if (empty($item->target_id)) {
      return NULL;
    }

    return $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load((int) $item->target_id);
  }

  /**
   * Return the most complete item list available for rendering.
   *
   * In some Views/contextual-filter executions the formatter receives a
   * subset of referenced items (often the matched term only). When that
   * happens, render from the host entity field values instead.
   */
  protected function getRenderableItems(FieldItemListInterface $items): array {
    $normalized = [];
    foreach ($items as $delta => $item) {
      $normalized[$delta] = $item;
    }

    $entity = $items->getEntity();
    if (!$entity) {
      return $normalized;
    }

    $field_name = $items->getFieldDefinition()->getName();
    if (!$entity->hasField($field_name)) {
      return $normalized;
    }

    $entity_items = $entity->get($field_name);
    if ($entity_items->count() <= count($normalized)) {
      return $normalized;
    }

    $expanded = [];
    foreach ($entity_items as $delta => $item) {
      $expanded[$delta] = $item;
    }

    return $expanded;
  }

  /**
   * Sort entries by taxonomy vocabulary tree order.
   */
  protected function sortEntriesByVocabularyTree(array $entries): array {
    if (count($entries) < 2) {
      return $entries;
    }

    $entries_by_vid = [];
    foreach ($entries as $index => $entry) {
      $term = $entry['term'] ?? NULL;
      if (!$term || !method_exists($term, 'bundle')) {
        continue;
      }
      $vid = (string) $term->bundle();
      if ($vid === '') {
        continue;
      }
      $entries_by_vid[$vid][] = $index;
    }

    if ($entries_by_vid === []) {
      return $entries;
    }

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tree_positions = [];
    foreach (array_keys($entries_by_vid) as $vid) {
      $tree = $term_storage->loadTree($vid, 0, NULL, TRUE);
      foreach (array_values($tree) as $position => $tree_term) {
        $tree_positions[(int) $tree_term->id()] = $position;
      }
    }

    uasort($entries, static function (array $a, array $b) use ($tree_positions): int {
      $a_term = $a['term'] ?? NULL;
      $b_term = $b['term'] ?? NULL;
      $a_id = $a_term ? (int) $a_term->id() : 0;
      $b_id = $b_term ? (int) $b_term->id() : 0;
      $a_pos = $tree_positions[$a_id] ?? PHP_INT_MAX;
      $b_pos = $tree_positions[$b_id] ?? PHP_INT_MAX;
      if ($a_pos !== $b_pos) {
        return $a_pos <=> $b_pos;
      }

      return ((int) ($a['delta'] ?? 0)) <=> ((int) ($b['delta'] ?? 0));
    });

    return array_values($entries);
  }

  /**
   * Sort entries by field item delta.
   */
  protected function sortEntriesByDelta(array $entries): array {
    if (count($entries) < 2) {
      return $entries;
    }

    uasort($entries, static function (array $a, array $b): int {
      return ((int) ($a['delta'] ?? 0)) <=> ((int) ($b['delta'] ?? 0));
    });

    return array_values($entries);
  }

}
