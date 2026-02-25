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
      foreach ($items as $delta => $item) {
        $logo = $item->view();

        $elements[$delta] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-logo-item'],
          ],
          'logo_wrapper' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-logo', 'nomads-logo--has-image'],
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
