<?php

declare(strict_types=1);

namespace Drupal\nomads_hero_gallery\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FieldFormatter(
  id: 'nomads_hero_gallery_media',
  label: new TranslatableMarkup('Hero gallery (media images)'),
  description: new TranslatableMarkup('Render media image references with per-position responsive image styles.'),
  field_types: [
    'entity_reference',
  ],
)]
class HeroGalleryMediaFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Number of secondary slots in the full desktop gallery design.
   */
  protected const DESKTOP_TILE_SLOTS = 6;

  /**
   * The image style storage.
   */
  protected ImageStyleStorageInterface $imageStyleStorage;

  /**
   * The responsive image style storage.
   */
  protected EntityStorageInterface $responsiveImageStyleStorage;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a HeroGalleryMediaFormatter instance.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    ImageStyleStorageInterface $image_style_storage,
    EntityStorageInterface $responsive_image_style_storage,
    RendererInterface $renderer,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->imageStyleStorage = $image_style_storage;
    $this->responsiveImageStyleStorage = $responsive_image_style_storage;
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
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('renderer'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'image_style_first' => '',
      'image_style_second' => '',
      'image_style_rest' => '',
      'max_images' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $style_options = ['' => $this->t('- None (original image) -')] + $this->getResponsiveImageStyleOptions();

    $elements['image_style_first'] = [
      '#type' => 'select',
      '#title' => $this->t('Responsive image style for first image'),
      '#default_value' => $this->getSetting('image_style_first'),
      '#options' => $style_options,
    ];

    $elements['image_style_second'] = [
      '#type' => 'select',
      '#title' => $this->t('Responsive image style for second image'),
      '#default_value' => $this->getSetting('image_style_second'),
      '#options' => $style_options,
    ];

    $elements['image_style_rest'] = [
      '#type' => 'select',
      '#title' => $this->t('Responsive image style for remaining images'),
      '#default_value' => $this->getSetting('image_style_rest'),
      '#options' => $style_options,
    ];

    $elements['max_images'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum images to render'),
      '#default_value' => $this->getSetting('max_images'),
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('0 means no limit.'),
    ];

    return $elements;
  }

  /**
   * Builds responsive image style option labels keyed by machine name.
   */
  protected function getResponsiveImageStyleOptions(): array {
    $options = [];
    $styles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($styles, '\Drupal\responsive_image\Entity\ResponsiveImageStyle::sort');

    foreach ($styles as $machine_name => $style) {
      $options[$machine_name] = $style->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $first = (string) $this->getSetting('image_style_first');
    $second = (string) $this->getSetting('image_style_second');
    $rest = (string) $this->getSetting('image_style_rest');
    $max = (int) $this->getSetting('max_images');

    return [
      $this->t('First responsive image style: @style', [
        '@style' => $this->getResponsiveImageStyleLabel($first),
      ]),
      $this->t('Second responsive image style: @style', [
        '@style' => $this->getResponsiveImageStyleLabel($second),
      ]),
      $this->t('Remaining responsive image style: @style', [
        '@style' => $this->getResponsiveImageStyleLabel($rest),
      ]),
      $this->t('Max images: @max', [
        '@max' => $max > 0 ? $max : $this->t('None'),
      ]),
    ];
  }

  /**
   * Gets a display label for a responsive image style setting value.
   */
  protected function getResponsiveImageStyleLabel(string $style_id): string|TranslatableMarkup {
    if ($style_id === '') {
      return $this->t('Original');
    }

    $style = $this->responsiveImageStyleStorage->load($style_id);
    return $style ? $style->label() : $style_id;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $gallery_media_items = $this->getGalleryMediaItems($items, $langcode);
    $gallery_items = $this->buildGalleryItems($gallery_media_items);
    $gallery_id = $this->buildGalleryId($items);

    $max_images = (int) $this->getSetting('max_images');
    $first_style = (string) $this->getSetting('image_style_first');
    $second_style = (string) $this->getSetting('image_style_second');
    $rest_style = (string) $this->getSetting('image_style_rest');

    $limit = $max_images > 0 ? min($max_images, count($gallery_media_items)) : count($gallery_media_items);
    $responsive_styles_to_cache = [];
    $image_styles_to_cache = [];

    $rendered_images = [];
    $count = 0;
    foreach ($gallery_media_items as $media) {
      if ($count >= $limit) {
        break;
      }

      if (!$media instanceof MediaInterface) {
        continue;
      }

      $media_type = $media->bundle->entity;
      $source_field_definition = $media_type?->getSource()->getSourceFieldDefinition($media_type);
      $source_field_name = $source_field_definition?->getName();
      if (!$source_field_name || $source_field_definition?->getType() !== 'image') {
        continue;
      }

      $image_item = $media->get($source_field_name)->first();
      if (!$image_item) {
        continue;
      }

      $image_style = $rest_style;
      if ($count === 0) {
        $image_style = $first_style;
      }
      elseif ($count === 1) {
        $image_style = $second_style;
      }

      if ($image_style !== '') {
        $responsive_styles_to_cache[$image_style] = TRUE;
      }

      $image_render = [
        '#theme' => 'responsive_image_formatter',
        '#item' => $image_item,
        '#item_attributes' => ['loading' => 'lazy'],
        '#responsive_image_style_id' => $image_style,
      ];

      $rendered_images[] = [
        'image' => $image_render,
        'media' => $media,
      ];
      $count++;
    }

    $main_image = $rendered_images[0] ?? NULL;
    $secondary_images = array_slice($rendered_images, 1);
    $slide_count = count($rendered_images);
    $has_multiple_slides = $slide_count > 1;
    $swiper_classes = ['nomads-hero-gallery__swiper', 'hero-swiper', 'swiper'];
    if (!$has_multiple_slides) {
      $swiper_classes[] = 'hero-swiper--single';
    }

    $swiper = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $swiper_classes,
        'aria-label' => $this->t('Hero gallery slideshow'),
        'data-slide-count' => (string) $slide_count,
      ],
      'wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-wrapper'],
        ],
      ],
    ];

    if ($main_image) {
      foreach ($rendered_images as $index => $rendered_image) {
        $slide_classes = ['swiper-slide', 'nomads-hero-gallery__slide'];
        $trigger_classes = ['nomads-hero-gallery__trigger--slide'];
        if ($index > 0) {
          $slide_classes[] = 'nomads-hero-gallery__slide--mobile-only';
          $trigger_classes[] = 'nomads-hero-gallery__trigger--mobile-slide';
        }

        $swiper['wrapper']['slide_' . $index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => $slide_classes,
          ],
          'image' => $this->wrapImageWithGalleryLink($rendered_image['image'], $rendered_image['media'], $gallery_id, $trigger_classes),
        ];
      }
    }
    else {
      $swiper['wrapper']['placeholder'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-slide', 'nomads-hero-gallery__slide'],
        ],
        'image' => $this->buildPlaceholderItem(TRUE),
      ];
    }

    if ($has_multiple_slides) {
      $swiper['button_prev'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-button-prev'],
          'aria-label' => $this->t('Previous image'),
        ],
      ];
      $swiper['button_next'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-button-next'],
          'aria-label' => $this->t('Next image'),
        ],
      ];
      $swiper['pagination'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['swiper-pagination'],
        ],
      ];
    }

    $tiles = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-hero-gallery__tiles'],
      ],
    ];

    foreach ($secondary_images as $index => $rendered_image) {
      if ($index >= self::DESKTOP_TILE_SLOTS) {
        break;
      }

      $tiles['tile_' . $index] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-hero-gallery__tile'],
        ],
        'image' => $this->wrapImageWithGalleryLink($rendered_image['image'], $rendered_image['media'], $gallery_id, [
          'nomads-hero-gallery__trigger--tile',
        ]),
      ];
    }

    $visible_tile_count = min(count($secondary_images), self::DESKTOP_TILE_SLOTS);
    for ($index = $visible_tile_count; $index < self::DESKTOP_TILE_SLOTS; $index++) {
      $tiles['placeholder_' . $index] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'nomads-hero-gallery__tile',
            'nomads-hero-gallery__tile--placeholder',
          ],
        ],
        'image' => $this->buildPlaceholderItem(),
      ];
    }

    $gallery_classes = ['nomads-hero-gallery'];
    $desktop_visible_count = min(count($rendered_images), self::DESKTOP_TILE_SLOTS + 1);

    $elements = [
      0 => [
        '#type' => 'container',
        '#attributes' => [
          'class' => $gallery_classes,
          'data-gallery-id' => $gallery_id,
        ],
        'swiper' => $swiper,
        'tiles' => $tiles,
        'gallery_items' => $this->buildHiddenGalleryLinks($gallery_items, $gallery_id, $desktop_visible_count),
      ],
      '#attached' => [
        'library' => [
          'nomads_hero_gallery/hero_gallery',
          'nomads_hero_gallery/glightbox',
          'nomads_hero_gallery/swiper',
        ],
      ],
    ];

    foreach (array_keys($responsive_styles_to_cache) as $style_name) {
      if ($responsive_image_style = $this->responsiveImageStyleStorage->load($style_name)) {
        $this->renderer->addCacheableDependency($elements, $responsive_image_style);
        foreach ($responsive_image_style->getImageStyleIds() as $image_style_id) {
          $image_styles_to_cache[$image_style_id] = TRUE;
        }
      }
    }
    foreach (array_keys($image_styles_to_cache) as $style_name) {
      if ($image_style = $this->imageStyleStorage->load($style_name)) {
        $this->renderer->addCacheableDependency($elements, $image_style);
      }
    }
    if ($lightbox_style = $this->imageStyleStorage->load('lightbox')) {
      $this->renderer->addCacheableDependency($elements, $lightbox_style);
    }
    foreach ($rendered_images as $rendered_image) {
      $this->renderer->addCacheableDependency($elements, $rendered_image['media']);
    }

    return $elements;
  }

  /**
   * Builds a non-clickable placeholder block for empty gallery slots.
   */
  protected function buildPlaceholderItem(bool $is_lead = FALSE): array {
    $classes = ['nomads-hero-gallery__placeholder'];
    if ($is_lead) {
      $classes[] = 'nomads-hero-gallery__placeholder--lead';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => $classes,
        'aria-hidden' => 'true',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();

    foreach (['image_style_first', 'image_style_second', 'image_style_rest'] as $setting) {
      $style_id = $this->getSetting($setting);
      if ($style_id && $style = ResponsiveImageStyle::load($style_id)) {
        $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'media';
  }

  /**
   * Gets gallery media items from node field_images, capped at 24.
   */
  protected function getGalleryMediaItems(FieldItemListInterface $items, string $langcode): array {
    $entity = $items->getEntity();
    $gallery_field = 'field_images';

    if ($entity instanceof FieldableEntityInterface && $entity->hasField($gallery_field)) {
      $gallery_items = $this->getEntitiesToView($entity->get($gallery_field), $langcode);
    }
    else {
      $gallery_items = $this->getEntitiesToView($items, $langcode);
    }

    $gallery_items = array_values(array_filter($gallery_items, static fn ($item): bool => $item instanceof MediaInterface));
    return array_slice($gallery_items, 0, 24);
  }

  /**
   * Builds gallery metadata for all available media items.
   */
  protected function buildGalleryItems(array $media_items): array {
    $gallery_items = [];

    foreach ($media_items as $media) {
      $image_data = $this->getMediaImageData($media);
      if ($image_data) {
        $gallery_items[] = $image_data;
      }
    }

    return $gallery_items;
  }

  /**
   * Wraps one rendered image in a GLightbox trigger link.
   */
  protected function wrapImageWithGalleryLink(array $image_render, MediaInterface $media, string $gallery_id, array $trigger_classes = []): array {
    $current_image = $this->getMediaImageData($media);
    if (!$current_image) {
      return $image_render;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => array_merge(['nomads-hero-gallery__trigger'], $trigger_classes),
      ],
      'image' => [
        '#type' => 'link',
        '#title' => $image_render,
        '#url' => Url::fromUri($current_image['full_url']),
        '#attributes' => [
          'class' => ['nomads-hero-gallery__lightbox-link', 'nomads-hero-gallery__link'],
          'data-gallery' => $gallery_id,
          'aria-label' => $this->t('Open gallery image'),
        ],
      ],
    ];
  }

  /**
   * Builds hidden gallery links for non-rendered images.
   */
  protected function buildHiddenGalleryLinks(array $gallery_items, string $gallery_id, int $visible_count): array {
    $gallery_markup = '';

    foreach (array_slice($gallery_items, $visible_count) as $gallery_item) {
      $gallery_markup .= '<a href="' . Html::escape($gallery_item['full_url']) . '" class="nomads-hero-gallery-glightbox nomads-hero-gallery__lightbox-hidden visually-hidden" data-gallery="' . Html::escape($gallery_id) . '" aria-hidden="true" tabindex="-1"></a>';
    }

    return [
      '#markup' => $gallery_markup,
    ];
  }

  /**
   * Resolves full image metadata for a media item.
   */
  protected function getMediaImageData(MediaInterface $media): ?array {
    $media_type = $media->bundle->entity;
    $source_field_definition = $media_type?->getSource()->getSourceFieldDefinition($media_type);
    $source_field_name = $source_field_definition?->getName();
    if (!$source_field_name || $source_field_definition?->getType() !== 'image') {
      return NULL;
    }

    $image_item = $media->get($source_field_name)->first();
    if (!$image_item || empty($image_item->entity)) {
      return NULL;
    }

    $file = $image_item->entity;
    $uri = $file->getFileUri();
    $lightbox_style = $this->imageStyleStorage->load('lightbox');

    return [
      'full_url' => $lightbox_style ? $lightbox_style->buildUrl($uri) : $this->fileUrlGenerator->generateAbsoluteString($uri),
    ];
  }

  /**
   * Builds a stable gallery id for the rendered field.
   */
  protected function buildGalleryId(FieldItemListInterface $items): string {
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id() ?? 'new';
    $field_name = $this->fieldDefinition->getName();

    return Html::getId(sprintf('nomads-hero-gallery-%s-%s-%s', $entity_type, $entity_id, $field_name));
  }

}
