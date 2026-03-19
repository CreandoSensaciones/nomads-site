<?php

declare(strict_types=1);

namespace Drupal\nomads_hero_gallery\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
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
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FieldFormatter(
  id: 'nomads_hero_gallery_media',
  label: new TranslatableMarkup('Hero gallery (media images)'),
  description: new TranslatableMarkup('Render media image references with per-position image styles.'),
  field_types: [
    'entity_reference',
  ],
)]
class HeroGalleryMediaFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The image style storage.
   */
  protected ImageStyleStorageInterface $imageStyleStorage;

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
    RendererInterface $renderer,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->imageStyleStorage = $image_style_storage;
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
    $style_options = function_exists('image_style_options') ? image_style_options(TRUE) : [
      '' => $this->t('None (original image)'),
    ];

    $elements['image_style_first'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style for first image'),
      '#default_value' => $this->getSetting('image_style_first'),
      '#options' => $style_options,
    ];

    $elements['image_style_second'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style for second image'),
      '#default_value' => $this->getSetting('image_style_second'),
      '#options' => $style_options,
    ];

    $elements['image_style_rest'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style for remaining images'),
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
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $first = (string) $this->getSetting('image_style_first');
    $second = (string) $this->getSetting('image_style_second');
    $rest = (string) $this->getSetting('image_style_rest');
    $max = (int) $this->getSetting('max_images');

    return [
      $this->t('First image style: @style', [
        '@style' => $first !== '' ? $first : $this->t('Original'),
      ]),
      $this->t('Second image style: @style', [
        '@style' => $second !== '' ? $second : $this->t('Original'),
      ]),
      $this->t('Remaining image style: @style', [
        '@style' => $rest !== '' ? $rest : $this->t('Original'),
      ]),
      $this->t('Max images: @max', [
        '@max' => $max > 0 ? $max : $this->t('None'),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);
    $gallery_media_items = $this->getGalleryMediaItems($items, $langcode);
    $gallery_items = $this->buildGalleryItems($gallery_media_items);
    $gallery_id = $this->buildGalleryId($items);

    if (empty($media_items)) {
      return $elements;
    }

    $max_images = (int) $this->getSetting('max_images');
    $first_style = (string) $this->getSetting('image_style_first');
    $second_style = (string) $this->getSetting('image_style_second');
    $rest_style = (string) $this->getSetting('image_style_rest');

    $limit = $max_images > 0 ? min($max_images, count($media_items)) : count($media_items);
    $styles_to_cache = [];

    $rendered_images = [];
    $count = 0;
    foreach ($media_items as $delta => $media) {
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
        $styles_to_cache[$image_style] = TRUE;
      }

      $elements[$delta] = [
        '#theme' => 'image_formatter',
        '#item' => $image_item,
        '#image_style' => $image_style,
      ];

      $elements[$delta] = $this->wrapImageWithGalleryLink($elements[$delta], $media, $gallery_id);

      $this->renderer->addCacheableDependency($elements[$delta], $media);
      $rendered_images[] = $elements[$delta];
      $count++;
    }

    foreach (array_keys($styles_to_cache) as $style_name) {
      if ($image_style = $this->imageStyleStorage->load($style_name)) {
        $this->renderer->addCacheableDependency($elements, $image_style);
      }
    }
    if ($lightbox_style = $this->imageStyleStorage->load('lightbox')) {
      $this->renderer->addCacheableDependency($elements, $lightbox_style);
    }

    if ($max_images === 7 && count($rendered_images) > 0) {
      $lead_image = array_shift($rendered_images);
      $grid = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-hero-gallery__grid'],
        ],
      ];
      foreach ($rendered_images as $index => $image_render) {
        $grid['cell_' . $index] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-hero-gallery__cell'],
          ],
          'image' => $image_render,
        ];
      }

      $elements = [
        0 => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-hero-gallery', 'nomads-hero-gallery--max-7'],
            'data-gallery-id' => $gallery_id,
          ],
          'lead' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-hero-gallery__lead'],
            ],
            'image' => $lead_image,
          ],
          'grid' => $grid,
          'gallery_items' => $this->buildHiddenGalleryLinks($gallery_items, $gallery_id, $count),
        ],
        '#attached' => [
          'library' => [
            'nomads_hero_gallery/hero_gallery',
            'nomads_hero_gallery/glightbox',
          ],
        ],
      ];
      return $elements;
    }

    $elements['#attached']['library'][] = 'nomads_hero_gallery/hero_gallery';
    $elements['#attached']['library'][] = 'nomads_hero_gallery/glightbox';
    $elements['#prefix'] = '<div class="nomads-hero-gallery" data-gallery-id="' . $gallery_id . '">';
    $elements['gallery_items'] = $this->buildHiddenGalleryLinks($gallery_items, $gallery_id, $count);
    $elements['#suffix'] = '</div>';

    return $elements;
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
      $gallery_items = $entity->get($gallery_field)->referencedEntities();
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
  protected function wrapImageWithGalleryLink(array $image_render, MediaInterface $media, string $gallery_id): array {
    $current_image = $this->getMediaImageData($media);
    if (!$current_image) {
      return $image_render;
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-hero-gallery__trigger'],
      ],
      'image' => [
        '#type' => 'link',
        '#title' => $image_render,
        '#url' => Url::fromUri($current_image['full_url']),
        '#attributes' => [
          'class' => ['nomads-hero-gallery-glightbox', 'nomads-hero-gallery__link'],
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
      $gallery_markup .= '<a href="' . Html::escape($gallery_item['full_url']) . '" class="nomads-hero-gallery-glightbox visually-hidden" data-gallery="' . Html::escape($gallery_id) . '" aria-hidden="true" tabindex="-1"></a>';
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
