<?php

declare(strict_types=1);

namespace Drupal\nomads_tiles\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TranslatableInterface as TranslatableDataInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FieldFormatter(
  id: 'nomads_tiles_media',
  label: new TranslatableMarkup('Nomads Tiles (media images)'),
  description: new TranslatableMarkup('Render media image references as a mix of data tiles and image tiles.'),
  field_types: [
    'entity_reference',
  ],
)]
class NomadsTilesMediaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a NomadsTilesMediaFormatter instance.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('renderer'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'pattern_1d' => 'D I I I I I',
      'pattern_2d' => 'D I I I D I',
      'pattern_3d' => 'D I D I D I',
      'min_total_tiles' => 0,
      'max_total_tiles' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['pattern_1d'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern for 1 data tile'),
      '#default_value' => $this->getSetting('pattern_1d'),
      '#description' => $this->t('Use D and I tokens (other characters are ignored).'),
    ];
    $elements['pattern_2d'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern for 2 data tiles'),
      '#default_value' => $this->getSetting('pattern_2d'),
      '#description' => $this->t('Use D and I tokens (other characters are ignored).'),
    ];
    $elements['pattern_3d'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern for 3 data tiles'),
      '#default_value' => $this->getSetting('pattern_3d'),
      '#description' => $this->t('Use D and I tokens (other characters are ignored).'),
    ];
    $elements['min_total_tiles'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum total tiles'),
      '#default_value' => $this->getSetting('min_total_tiles'),
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('Fill with empty tiles when output is below this number.'),
    ];
    $elements['max_total_tiles'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum total tiles'),
      '#default_value' => $this->getSetting('max_total_tiles'),
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('0 means no limit.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('Pattern 1D: @pattern', ['@pattern' => $this->getSetting('pattern_1d')]);
    $summary[] = $this->t('Pattern 2D: @pattern', ['@pattern' => $this->getSetting('pattern_2d')]);
    $summary[] = $this->t('Pattern 3D: @pattern', ['@pattern' => $this->getSetting('pattern_3d')]);

    $min = (int) $this->getSetting('min_total_tiles');
    $max = (int) $this->getSetting('max_total_tiles');
    $summary[] = $this->t('Min tiles: @min', ['@min' => $min]);
    $summary[] = $this->t('Max tiles: @max', ['@max' => $max ?: $this->t('None')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $entity = $items->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return [];
    }

    if ($entity instanceof TranslatableDataInterface && $entity->isTranslatable()) {
      $view_langcode = $entity->language()->getId();
    }
    else {
      $view_langcode = NULL;
    }

    $field_name = $this->fieldDefinition->getName();
    $display = EntityViewDisplay::collectRenderDisplay($entity, $this->viewMode);

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($entity);
    $cacheability->addCacheableDependency($display);

    $tile_groups = $this->getTileGroups($display, $entity);
    $data_tiles = $this->buildDataTiles($display, $entity, $tile_groups, $view_langcode, $cacheability, $field_name);

    $image_tiles = $this->buildImageTiles($items, $cacheability);

    $output_tiles = $this->buildTileSequence($data_tiles, $image_tiles);

    $wrapper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'nomads-tiles',
          'nomads-tiles--field-' . Html::getClass($field_name),
        ],
        'data-gallery-id' => $this->buildGalleryId($entity, $field_name),
      ],
      '#attached' => [
        'library' => [
          'nomads_tiles/tiles',
          'nomads_tiles/glightbox',
          'nomads_tiles/mobile_swiper',
        ],
      ],
    ];

    foreach ($output_tiles as $delta => $tile_build) {
      $wrapper['tile_' . $delta] = $tile_build;
    }
    $wrapper['mobile_swiper'] = $this->buildMobileSwiper($output_tiles);
    $wrapper['gallery_items'] = $this->buildHiddenGalleryLinks($items, $output_tiles, $cacheability);

    $cacheability->applyTo($wrapper);

    return [$wrapper];
  }

  /**
   * Builds a mobile-only Swiper from the visible tile sequence.
   */
  protected function buildMobileSwiper(array $output_tiles): array {
    $swiper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-tiles__mobile-swiper', 'swiper'],
        'aria-label' => $this->t('More images'),
      ],
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-wrapper'],
        ],
      ],
    ];

    $slide_delta = 0;
    foreach ($output_tiles as $tile_build) {
      $mobile_tile = $tile_build;
      if ($this->isImageTile($mobile_tile)) {
        $mobile_tile['#type'] = 'html_tag';
        $mobile_tile['#tag'] = 'div';
        $mobile_tile['#value'] = '';
        unset($mobile_tile['#url'], $mobile_tile['#title']);
        $mobile_tile['#attributes']['class'] = array_values(array_diff($mobile_tile['#attributes']['class'], ['nomads-tiles-glightbox']));
        unset($mobile_tile['#attributes']['data-gallery']);
      }
      $mobile_tile['#attributes']['class'][] = 'nomads-tiles__mobile-tile';

      $swiper['wrapper']['slide_' . $slide_delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-slide', 'nomads-tiles__mobile-slide'],
        ],
        'tile' => $mobile_tile,
      ];
      $slide_delta++;
    }

    return $swiper;
  }

  /**
   * Determines if a tile render array is an image tile.
   */
  protected function isImageTile(array $tile_build): bool {
    $classes = $tile_build['#attributes']['class'] ?? [];
    return in_array('image-tile', $classes, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if ($field_definition->getType() !== 'entity_reference') {
      return FALSE;
    }

    if ($field_definition->getSetting('target_type') !== 'media') {
      return FALSE;
    }

    $handler_settings = $field_definition->getSetting('handler_settings');
    $target_bundles = $handler_settings['target_bundles'] ?? [];
    if (empty($target_bundles)) {
      return FALSE;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('media_type');
    foreach ($target_bundles as $bundle) {
      $media_type = $storage->load($bundle);
      if ($media_type && $media_type->getSource()->getPluginId() === 'image') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Find tile groups in the active display.
   *
   * @return array
   *   Tile groups keyed by machine name.
   */
  protected function getTileGroups(EntityViewDisplayInterface $display, FieldableEntityInterface $entity): array {
    $tile_groups = [];

    if (function_exists('field_group_info_groups')) {
      $groups = field_group_info_groups($entity->getEntityTypeId(), $entity->bundle(), 'view', $display->getMode());
      foreach ($groups as $group_name => $group) {
        if (!$this->isNomadsDataTileGroup($group)) {
          continue;
        }
        $tile_groups[$group_name] = [
          'children' => $group->children ?? [],
          'weight' => $group->weight ?? 0,
          'label' => $group->label ?? '',
          'format_settings' => $group->format_settings ?? [],
        ];
      }
    }

    if (empty($tile_groups)) {
      $settings = $display->getThirdPartySettings('field_group');
      if (!empty($settings) && is_array($settings)) {
        foreach ($settings as $group_name => $group) {
          if (!$this->isNomadsDataTileGroup($group)) {
            continue;
          }
          $tile_groups[$group_name] = $group;
        }
      }
    }

    if (empty($tile_groups)) {
      return [];
    }

    uasort($tile_groups, static function (array $a, array $b): int {
      $weight_a = $a['weight'] ?? 0;
      $weight_b = $b['weight'] ?? 0;
      if ($weight_a === $weight_b) {
        return 0;
      }
      return ($weight_a < $weight_b) ? -1 : 1;
    });

    return $tile_groups;
  }

  /**
   * Determine if a group is a Nomads data tile group.
   *
   * @param mixed $group
   *   Group definition array or object.
   */
  protected function isNomadsDataTileGroup($group): bool {
    if (is_object($group)) {
      $format_type = $group->format_type ?? '';
      if ($format_type === 'nomads_data_tile') {
        return TRUE;
      }
      if (!empty($group->format_settings['formatter']) && $group->format_settings['formatter'] === 'nomads_data_tile') {
        return TRUE;
      }
      return FALSE;
    }

    if (!is_array($group)) {
      return FALSE;
    }

    if (($group['format_type'] ?? '') === 'nomads_data_tile') {
      return TRUE;
    }

    return !empty($group['format_settings']['formatter']) && $group['format_settings']['formatter'] === 'nomads_data_tile';
  }

  /**
   * Build data tiles from tile groups.
   *
   * @return array
   *   Array with [data_tiles, suppress_fields].
   */
  protected function buildDataTiles(EntityViewDisplayInterface $display, FieldableEntityInterface $entity, array $tile_groups, ?string $view_langcode, CacheableMetadata $cacheability, string $image_field_name): array {
    $data_tiles = [];
    $extra_field_builds = NULL;

    if (empty($tile_groups)) {
      return $data_tiles;
    }

    foreach ($tile_groups as $group) {
      $children = $group['children'] ?? [];
      if (empty($children) || !is_array($children)) {
        continue;
      }

      $format_settings = $group['format_settings'] ?? [];
      $group_classes = $this->extractGroupClasses($format_settings);
      $tile_build = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'tile',
            'data-tile',
          ],
        ],
      ];
      $tile_build['tile_items'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'tile-items',
          ],
        ],
      ];
      if (!empty($group_classes)) {
        $tile_build['#attributes']['class'] = array_merge($tile_build['#attributes']['class'], $group_classes);
      }

      $label = $group['label'] ?? '';
      if (!empty($format_settings['show_label']) && $label !== '') {
        $label_value = $label;
        if (empty($format_settings['label_as_html'])) {
          $label_value = Markup::create(Html::escape($label));
        }
        $tile_build['group_label'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $label_value,
          '#weight' => -10,
        ];
      }

      $has_content = FALSE;
      $rendered_children = [];

      $child_weight = 0;
      foreach ($children as $child) {
        if (!is_string($child) || $child === $image_field_name) {
          continue;
        }
        $component = $display->getComponent($child);
        if (!$component) {
          continue;
        }

        if ($entity->hasField($child)) {
          $field_items = $entity->get($child);
          $field_items->filterEmptyItems();
          if ($field_items->isEmpty()) {
            continue;
          }

          $formatter = $display->getRenderer($child);
          if (!$formatter) {
            continue;
          }

          $entity_id = $entity->id() ?? 0;
          $formatter->prepareView([$entity_id => $field_items]);
          $access = $field_items->access('view', NULL, TRUE);
          $field_build = $access->isAllowed() ? $formatter->view($field_items, $view_langcode) : [];
          $this->renderer->addCacheableDependency($field_build, $access);
          $cacheability->addCacheableDependency($field_build);
        }
        else {
          if ($extra_field_builds === NULL) {
            $extra_field_builds = $this->buildExtraFieldComponents($entity, $display, (string) $this->viewMode);
          }
          if (empty($extra_field_builds[$child])) {
            continue;
          }
          $field_build = $extra_field_builds[$child];
          if (isset($component['weight'])) {
            $field_build['#weight'] = $component['weight'];
          }
          $cacheability->addCacheableDependency($field_build);
        }

        if (!empty($field_build)) {
          $has_content = TRUE;
          $field_build['#weight'] = $child_weight;
          $rendered_children[] = [
            'key' => $child,
            'build' => $field_build,
            'row_marker' => $this->extractTileRowMarker($component, $field_build),
          ];
        }

        $child_weight++;
      }

      if ($has_content) {
        foreach ($this->groupTileRowChildren($rendered_children) as $item) {
          $tile_build['tile_items'][$item['key']] = $item['build'];
        }
        $data_tiles[] = $tile_build;
      }
    }

    // Number data tiles for stable theming.
    foreach ($data_tiles as $index => &$tile) {
      $tile['#attributes']['class'][] = 'data-tile-' . ($index + 1);
    }
    unset($tile);

    return $data_tiles;
  }

  /**
   * Build image tiles from referenced media items.
   */
  protected function buildImageTiles(FieldItemListInterface $items, CacheableMetadata $cacheability): array {
    $image_tiles = [];
    $media_entities = $items->referencedEntities();
    $gallery_id = $this->buildGalleryId($items->getEntity(), $this->fieldDefinition->getName());
    $tile_style = $this->entityTypeManager->getStorage('image_style')->load('tile');
    $lightbox_style = $this->entityTypeManager->getStorage('image_style')->load('lightbox');

    if (empty($media_entities)) {
      return $image_tiles;
    }

    if ($tile_style) {
      $cacheability->addCacheableDependency($tile_style);
    }
    if ($lightbox_style) {
      $cacheability->addCacheableDependency($lightbox_style);
    }

    foreach ($media_entities as $delta => $media) {
      if (!$media instanceof MediaInterface) {
        continue;
      }

      $image_data = $this->getMediaImageData($media, $tile_style, $lightbox_style);
      if (!$image_data) {
        continue;
      }

      $tile = [
        '#type' => 'link',
        '#title' => '',
        '#url' => \Drupal\Core\Url::fromUri($image_data['full_url']),
        '#attributes' => [
          'class' => [
            'tile',
            'image-tile',
            'nomads-tiles-glightbox',
          ],
          'style' => "background-image:url('{$image_data['tile_url']}')",
          'data-delta' => (string) $delta,
          'data-media-id' => (string) $media->id(),
          'data-gallery' => $gallery_id,
          'aria-label' => $this->t('Open image @number', ['@number' => $delta + 1]),
        ],
      ];

      $cacheability->addCacheableDependency($media);
      $cacheability->addCacheableDependency($image_data['file']);
      $image_tiles[] = $tile;
    }

    return $image_tiles;
  }

  /**
   * Builds hidden gallery links for images omitted from the tile layout.
   */
  protected function buildHiddenGalleryLinks(FieldItemListInterface $items, array $output_tiles, CacheableMetadata $cacheability): array {
    $gallery_markup = '';
    $visible_media_ids = [];
    $gallery_id = $this->buildGalleryId($items->getEntity(), $this->fieldDefinition->getName());
    $lightbox_style = $this->entityTypeManager->getStorage('image_style')->load('lightbox');

    if ($lightbox_style) {
      $cacheability->addCacheableDependency($lightbox_style);
    }

    foreach ($output_tiles as $tile) {
      $media_id = $tile['#attributes']['data-media-id'] ?? NULL;
      if ($media_id !== NULL) {
        $visible_media_ids[(string) $media_id] = TRUE;
      }
    }

    foreach ($items->referencedEntities() as $media) {
      if (!$media instanceof MediaInterface) {
        continue;
      }
      if (isset($visible_media_ids[(string) $media->id()])) {
        continue;
      }

      $image_data = $this->getMediaImageData($media, NULL, $lightbox_style);
      if (!$image_data) {
        continue;
      }

      $cacheability->addCacheableDependency($media);
      $cacheability->addCacheableDependency($image_data['file']);
      $gallery_markup .= '<a href="' . Html::escape($image_data['full_url']) . '" class="nomads-tiles-glightbox visually-hidden" data-gallery="' . Html::escape($gallery_id) . '" aria-hidden="true" tabindex="-1"></a>';
    }

    return [
      '#markup' => $gallery_markup,
    ];
  }

  /**
   * Build the final tile sequence based on settings.
   */
  protected function buildTileSequence(array $data_tiles, array $image_tiles): array {
    $output = [];
    $data_count = count($data_tiles);

    $min_total = max(0, (int) $this->getSetting('min_total_tiles'));
    $max_total = max(0, (int) $this->getSetting('max_total_tiles'));

    $apply_max = static function (array $tiles, int $max): array {
      if ($max > 0 && count($tiles) > $max) {
        return array_slice($tiles, 0, $max);
      }
      return $tiles;
    };

    if ($min_total > 0 && $min_total < (($data_count * 2) - 1)) {
      $output = array_merge($data_tiles, $image_tiles);
      $output = $apply_max($output, $max_total);
      return $this->applyMinFill($output, $min_total, $max_total);
    }

    $tokens = $this->getStartPatternTokens($data_count);
    $data_index = 0;
    $image_index = 0;

    foreach ($tokens as $token) {
      if ($max_total > 0 && count($output) >= $max_total) {
        break;
      }
      if ($token === 'D') {
        if (isset($data_tiles[$data_index])) {
          $output[] = $data_tiles[$data_index];
          $data_index++;
        }
      }
      elseif ($token === 'I') {
        if (isset($image_tiles[$image_index])) {
          $output[] = $image_tiles[$image_index];
          $image_index++;
        }
      }
    }

    while (isset($image_tiles[$image_index])) {
      if ($max_total > 0 && count($output) >= $max_total) {
        break;
      }
      $output[] = $image_tiles[$image_index];
      $image_index++;
    }

    return $this->applyMinFill($output, $min_total, $max_total);
  }

  /**
   * Get start pattern tokens for a given number of data tiles.
   */
  protected function getStartPatternTokens(int $data_count): array {
    if ($data_count <= 0) {
      return [];
    }

    if ($data_count === 1) {
      return $this->parsePatternTokens((string) $this->getSetting('pattern_1d'));
    }

    if ($data_count === 2) {
      return $this->parsePatternTokens((string) $this->getSetting('pattern_2d'));
    }

    if ($data_count === 3) {
      return $this->parsePatternTokens((string) $this->getSetting('pattern_3d'));
    }

    $length = ($data_count * 2) - 1;
    $tokens = [];
    for ($i = 0; $i < $length; $i++) {
      $tokens[] = ($i % 2 === 0) ? 'D' : 'I';
    }

    return $tokens;
  }

  /**
   * Parse D/I pattern tokens.
   */
  protected function parsePatternTokens(string $pattern): array {
    preg_match_all('/[DI]/i', $pattern, $matches);
    $tokens = $matches[0] ?? [];
    return array_map('strtoupper', $tokens);
  }

  /**
   * Apply min fill with empty tiles.
   */
  protected function applyMinFill(array $output, int $min_total, int $max_total): array {
    if ($min_total <= 0 || count($output) >= $min_total) {
      return $output;
    }

    $empty_index = 1;
    while (count($output) < $min_total) {
      if ($max_total > 0 && count($output) >= $max_total) {
        break;
      }
      $output[] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => [
            'tile',
            'empty-tile',
            'empty-tile-' . $empty_index,
          ],
        ],
      ];
      $empty_index++;
    }

    return $output;
  }

  /**
   * Resolve media image URL.
   */
  protected function getMediaImageData(MediaInterface $media, ?ImageStyleInterface $tile_style, ?ImageStyleInterface $lightbox_style = NULL): ?array {
    $source = $media->getSource();
    if (!$source) {
      return NULL;
    }

    $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$media_type) {
      return NULL;
    }

    $source_field_definition = $source->getSourceFieldDefinition($media_type);
    if (!$source_field_definition) {
      return NULL;
    }

    $source_field = $source_field_definition->getName();
    if (!$media->hasField($source_field)) {
      return NULL;
    }

    $image_item = $media->get($source_field)->first();
    if (!$image_item || empty($image_item->entity)) {
      return NULL;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $image_item->entity;
    $uri = $file->getFileUri();
    $tile_url = $tile_style ? $tile_style->buildUrl($uri) : $this->fileUrlGenerator->generateAbsoluteString($uri);
    $full_url = $lightbox_style ? $lightbox_style->buildUrl($uri) : $this->fileUrlGenerator->generateAbsoluteString($uri);

    return [
      'file' => $file,
      'full_url' => $full_url,
      'tile_url' => $tile_url,
    ];
  }

  /**
   * Build a stable gallery id per entity field instance.
   */
  protected function buildGalleryId(FieldableEntityInterface $entity, string $field_name): string {
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id() ?? 'new';

    return Html::getId(sprintf('nomads-tiles-%s-%s-%s', $entity_type, $entity_id, $field_name));
  }

  /**
   * Extract class names from field group format settings.
   */
  protected function extractGroupClasses(array $format_settings): array {
    $raw_classes = '';

    if (!empty($format_settings['classes']) && is_string($format_settings['classes'])) {
      $raw_classes = $format_settings['classes'];
    }
    elseif (!empty($format_settings['instance_settings']['classes']) && is_string($format_settings['instance_settings']['classes'])) {
      $raw_classes = $format_settings['instance_settings']['classes'];
    }

    if ($raw_classes === '') {
      return [];
    }

    $parts = preg_split('/\s+/', trim($raw_classes)) ?: [];
    return array_values(array_filter($parts, static fn(string $class): bool => $class !== ''));
  }

  /**
   * Group consecutive rendered children that share a tile-row marker class.
   */
  protected function groupTileRowChildren(array $children): array {
    $grouped = [];
    $count = count($children);
    $index = 0;

    while ($index < $count) {
      $current = $children[$index];
      $marker = $current['row_marker'] ?? NULL;

      if ($marker === NULL) {
        $grouped[] = [
          'key' => $current['key'],
          'build' => $current['build'],
        ];
        $index++;
        continue;
      }

      $row_children = [$current];
      $index++;

      while ($index < $count && (($children[$index]['row_marker'] ?? NULL) === $marker)) {
        $row_children[] = $children[$index];
        $index++;
      }

      $wrapper_key = Html::getId('tile-row-' . implode('-', array_map(static fn(array $child): string => $child['key'], $row_children)));
      $wrapper = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'tile_row',
            'tile-row-' . count($row_children),
            $marker,
          ],
        ],
      ];

      foreach ($row_children as $position => $row_child) {
        $row_child['build']['#weight'] = $position;
        $wrapper[$row_child['key']] = $row_child['build'];
      }

      $grouped[] = [
        'key' => $wrapper_key,
        'build' => $wrapper,
      ];
    }

    return $grouped;
  }

  /**
   * Extract the first tile-row marker from a component or rendered field.
   */
  protected function extractTileRowMarker(array $component, array $field_build): ?string {
    foreach ($this->extractComponentClasses($component, $field_build) as $class) {
      if (str_starts_with($class, 'tile-row-')) {
        return $class;
      }
    }

    return NULL;
  }

  /**
   * Extract configured class names for a field display component.
   */
  protected function extractComponentClasses(array $component, array $field_build = []): array {
    $raw_classes = [];
    $third_party_settings = $component['third_party_settings'] ?? [];

    if (!empty($third_party_settings['field_formatter_class']['class']) && is_string($third_party_settings['field_formatter_class']['class'])) {
      $raw_classes[] = $third_party_settings['field_formatter_class']['class'];
    }

    if (!empty($third_party_settings['field_group']['classes']) && is_string($third_party_settings['field_group']['classes'])) {
      $raw_classes[] = $third_party_settings['field_group']['classes'];
    }

    if (!empty($third_party_settings['field_group']['format_settings']['classes']) && is_string($third_party_settings['field_group']['format_settings']['classes'])) {
      $raw_classes[] = $third_party_settings['field_group']['format_settings']['classes'];
    }

    if (!empty($field_build['#attributes']['class']) && is_array($field_build['#attributes']['class'])) {
      $raw_classes[] = implode(' ', $field_build['#attributes']['class']);
    }

    $classes = [];
    foreach ($raw_classes as $raw_class_string) {
      $parts = preg_split('/\s+/', trim($raw_class_string)) ?: [];
      foreach ($parts as $class) {
        if ($class !== '') {
          $classes[$class] = $class;
        }
      }
    }

    return array_values($classes);
  }

  /**
   * Build extra field components via entity view hooks.
   */
  protected function buildExtraFieldComponents(FieldableEntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): array {
    $build = [];
    $view_hook = $entity->getEntityTypeId() . '_view';
    $module_handler = \Drupal::moduleHandler();

    $module_handler->invokeAll($view_hook, [&$build, $entity, $display, $view_mode]);
    $module_handler->invokeAll('entity_view', [&$build, $entity, $display, $view_mode]);

    return $build;
  }

  /**
   * Mark fields for suppression in hook_entity_display_build_alter().
   */
}
