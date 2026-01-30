<?php

namespace Drupal\nomads_taxonomy_icons\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
      'link_label' => FALSE,
      'link_icon' => FALSE,
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
      $this->t('Link label: @value', [
        '@value' => $this->getSetting('link_label') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Link icon: @value', [
        '@value' => $this->getSetting('link_icon') ? $this->t('Yes') : $this->t('No'),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $base_classes = $this->splitClasses((string) $this->getSetting('css_classes'));
    $first_item_classes = $this->splitClasses((string) $this->getSetting('first_item_css_classes'));
    $pills_after_first = (bool) $this->getSetting('pills_after_first');
    $link_label = (bool) $this->getSetting('link_label');
    $link_icon = (bool) $this->getSetting('link_icon');

    foreach ($items as $delta => $item) {
      if (!$item->entity) {
        continue;
      }

      $term = $item->entity;
      $classes = $base_classes;
      if ($delta === 0 && !empty($first_item_classes)) {
        $classes = array_merge($classes, $first_item_classes);
      }
      $classes[] = 'nomads-taxonomy-icons__item';
      if ($pills_after_first && $delta > 0) {
        $classes[] = 'nomads-taxonomy-icons__item--pill';
      }

      $item_attributes = (array) ($item->_attributes ?? []);
      $item_attributes['class'] = array_values(array_unique(array_merge($item_attributes['class'] ?? [], $classes)));
      $item->_attributes = $item_attributes;

      $element = [
      ];

      $show_icon = !$pills_after_first || $delta === 0;
      if ($show_icon) {
        $icon = $this->buildTermIcon($term);
        if ($icon) {
          $element['icon'] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-taxonomy-icons__icon'],
            ],
            'media' => $icon,
          ];
          if ($link_icon) {
            $element['icon']['media'] = [
              '#type' => 'link',
              '#title' => $icon,
              '#url' => $term->toUrl(),
            ];
          }
        }
      }

      $label_text = Html::escape($term->label());
      $label_value = $label_text;
      if ($link_label) {
        $label_value = [
          '#type' => 'link',
          '#title' => $label_text,
          '#url' => $term->toUrl(),
        ];
      }
      if ($pills_after_first && $delta > 0) {
        $element['label'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $label_text,
          '#attributes' => [
            'class' => ['nomads-taxonomy-icons__pill'],
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
        ];
        if ($link_label) {
          $element['label']['link'] = $label_value;
        }
      }

      $element['#attached']['library'][] = 'nomads_taxonomy_icons/taxonomy-icons';
      $elements[$delta] = $element;
    }

    return $elements;
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
    if (!$icon_item || empty($icon_item->entity)) {
      return NULL;
    }

    $image_style = (string) $this->getSetting('image_style');
    $field_type = $icon_field->getFieldDefinition()->getType();

    if ($field_type === 'image') {
      $file = $icon_item->entity;
      $uri = $file->getFileUri();
      $alt = $icon_item->alt ?? '';
      $title = $icon_item->title ?? '';
      return $this->buildImageRenderArray($uri, $alt, $title, $image_style);
    }

    $entity = $icon_item->entity;
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
   * Build an image render array with an optional style.
   */
  protected function buildImageRenderArray(string $uri, string $alt, string $title, string $style_name): array {
    if ($style_name !== '') {
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

    $file = $image_item->entity;
    return $file->getFileUri();
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

}
