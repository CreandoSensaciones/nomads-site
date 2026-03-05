<?php

declare(strict_types=1);

namespace Drupal\nomads_logo_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

#[FieldFormatter(
  id: 'nomads_logo_or_title_circle',
  label: new TranslatableMarkup('Nomads: Logo or title circle'),
  field_types: [
    'entity_reference',
    'image',
  ],
)]
class NomadsLogoOrTitleCircleFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'size' => 64,
      'use_initials' => FALSE,
      'image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Fallback circle size (px)'),
      '#default_value' => $this->getSetting('size'),
      '#min' => 16,
      '#max' => 256,
      '#step' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Used for fallback avatars only (16 to 256 px).'),
    ];

    $element['use_initials'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use initials in fallback'),
      '#default_value' => $this->getSetting('use_initials'),
      '#description' => $this->t('If checked, show up to 2 initials from the title instead of full title.'),
    ];

    $image_style_options = function_exists('image_style_options') ? image_style_options(FALSE) : [];
    $element['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style'),
      '#default_value' => $this->getSetting('image_style'),
      '#options' => ['' => $this->t('- None (original image) -')] + $image_style_options,
      '#description' => $this->t('Optional image style applied to logo images.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary[] = $this->t('Fallback size: @size px', [
      '@size' => (int) $this->getSetting('size'),
    ]);

    $summary[] = $this->getSetting('use_initials')
      ? $this->t('Fallback text: initials')
      : $this->t('Fallback text: full title');

    $image_style = (string) $this->getSetting('image_style');
    if ($image_style !== '') {
      $style_label = $image_style;
      $style = \Drupal::entityTypeManager()->getStorage('image_style')->load($image_style);
      if ($style) {
        $style_label = $style->label();
      }
      $summary[] = $this->t('Image style: @style', ['@style' => $style_label]);
    }
    else {
      $summary[] = $this->t('Image style: None');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $library = 'nomads_logo_formatter/logo_formatter';
    $entity = $items->getEntity();
    $title = trim((string) $entity->label());
    $entity_url = $entity->hasLinkTemplate('canonical') ? $entity->toUrl() : NULL;

    if (!$items->isEmpty()) {
      $size = max(16, min(256, (int) $this->getSetting('size')));
      $image_style = (string) $this->getSetting('image_style');
      foreach ($items as $delta => $item) {
        $logo = $this->buildLogoImage($item, $title, $size, $image_style);

        if ($logo === NULL) {
          $display_text = $this->buildFallbackText($title);
          $logo = [
            '#type' => 'inline_template',
            '#template' => '<span class="{{ classes|join(" ") }}" title="{{ title }}">{{ text }}</span>',
            '#context' => [
              'classes' => [
                'nomads-logo',
                'nomads-logo--fallback',
                'nomads-logo--fallback-size-' . $size,
              ],
              'title' => $title,
              'text' => $display_text,
            ],
          ];
        }

        $elements[$delta] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-logo-item'],
          ],
          'logo_wrapper' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-logo', $this->isImageRenderable($logo) ? 'nomads-logo--has-image' : 'nomads-logo--fallback'],
            ],
            'image' => $entity_url !== NULL ? [
              '#type' => 'link',
              '#title' => $logo,
              '#url' => $entity_url,
              '#options' => [
                'attributes' => [
                  'class' => ['nomads-logo-link'],
                ],
              ],
            ] : $logo,
          ],
          'title' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-logo-title'],
            ],
            'link' => $this->buildEntityTitleLink($entity, $title),
          ],
          '#attached' => [
            'library' => [$library],
          ],
        ];
      }

      return $elements;
    }

    $display_text = $this->buildFallbackText($title);
    $size = max(16, min(256, (int) $this->getSetting('size')));
    $fallback_circle = [
      '#type' => 'inline_template',
      '#template' => '<span class="{{ classes|join(" ") }}" title="{{ title }}">{{ text }}</span>',
      '#context' => [
        'classes' => [
          'nomads-logo',
          'nomads-logo--fallback',
          'nomads-logo--fallback-size-' . $size,
        ],
        'title' => $title,
        'text' => $display_text,
      ],
    ];

    $elements[0] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-logo-item'],
      ],
      'fallback' => $entity_url !== NULL ? [
        '#type' => 'link',
        '#title' => $fallback_circle,
        '#url' => $entity_url,
        '#options' => [
          'attributes' => [
            'class' => ['nomads-logo-link'],
          ],
        ],
      ] : $fallback_circle,
      '#attached' => [
        'library' => [$library],
      ],
      '#cache' => [
        'contexts' => ['languages:language_interface'],
      ],
    ];

    return $elements;
  }

  /**
   * Builds the rendered logo image from entity reference/image field items.
   */
  protected function buildLogoImage(mixed $item, string $title, int $size, string $image_style = ''): ?array {
    $file = NULL;
    $alt = $title;

    if ($item?->entity instanceof MediaInterface) {
      $media = $item->entity;
      $media_type = $media->bundle->entity ?? NULL;
      $source_field_definition = $media_type ? $media->getSource()->getSourceFieldDefinition($media_type) : NULL;
      $source_field = $source_field_definition?->getName();
      if ($source_field && $media->hasField($source_field) && !$media->get($source_field)->isEmpty()) {
        $source_item = $media->get($source_field)->first();
        if ($source_item?->entity instanceof FileInterface) {
          $file = $source_item->entity;
          $alt = trim((string) ($source_item->alt ?? '')) !== '' ? (string) $source_item->alt : $title;
        }
      }
    }
    elseif ($item?->entity instanceof FileInterface) {
      $file = $item->entity;
      $alt = trim((string) ($item->alt ?? '')) !== '' ? (string) $item->alt : $title;
    }

    if (!$file instanceof FileInterface) {
      return NULL;
    }

    if ($image_style !== '') {
      return [
        '#theme' => 'image_style',
        '#style_name' => $image_style,
        '#uri' => $file->getFileUri(),
        '#alt' => $alt,
        '#attributes' => [
          'loading' => 'lazy',
          'width' => $size,
          'height' => $size,
        ],
      ];
    }

    return [
      '#theme' => 'image',
      '#uri' => $file->getFileUri(),
      '#alt' => $alt,
      '#attributes' => [
        'loading' => 'lazy',
      ],
      '#width' => $size,
      '#height' => $size,
    ];
  }

  /**
   * Determines if the render array is image output.
   */
  protected function isImageRenderable(array $renderable): bool {
    return in_array(($renderable['#theme'] ?? ''), ['image', 'image_style'], TRUE);
  }

  /**
   * Builds fallback text for an empty logo field.
   */
  protected function buildFallbackText(string $title): string {
    if ($title === '') {
      return '•';
    }

    if (!$this->getSetting('use_initials')) {
      return $title;
    }

    $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = [];

    foreach (array_slice($words, 0, 2) as $word) {
      $initials[] = mb_strtoupper(mb_substr($word, 0, 1));
    }

    return $initials ? implode('', $initials) : '•';
  }

  /**
   * Builds a linked title render array when the entity has a canonical URL.
   */
  protected function buildEntityTitleLink(EntityInterface $entity, string $title): array {
    $link_text = $title !== '' ? $title : $this->t('Untitled');

    if ($entity->hasLinkTemplate('canonical')) {
      return Link::fromTextAndUrl($link_text, $entity->toUrl())->toRenderable();
    }

    return [
      '#plain_text' => (string) $link_text,
    ];
  }

}
