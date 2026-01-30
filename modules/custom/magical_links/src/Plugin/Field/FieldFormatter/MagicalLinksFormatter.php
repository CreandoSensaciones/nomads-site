<?php

namespace Drupal\magical_links\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;

/**
 * Plugin implementation of the 'magical_links' formatter.
 *
 * @FieldFormatter(
 *   id = "magical_links",
 *   label = @Translation("Magical Links"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class MagicalLinksFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'trim_length' => '80',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['trim_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Trim link text length'),
      '#field_suffix' => $this->t('characters'),
      '#default_value' => $this->getSetting('trim_length'),
      '#min' => 1,
      '#description' => $this->t('Leave blank to allow unlimited link text lengths.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $settings = $this->getSettings();

    if (!empty($settings['trim_length'])) {
      $summary[] = $this->t('Link text trimmed to @limit characters', ['@limit' => $settings['trim_length']]);
    }
    else {
      $summary[] = $this->t('Link text not trimmed');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $entity = $items->getEntity();
    $settings = $this->getSettings();
    $repo = \Drupal::service('magical_links.definition_repository');
    $definitions_data = $repo->getDefinitions('links', 'field_icons', 'field_prefill', FALSE, TRUE);
    $definitions = $definitions_data['definitions'];
    $group_definitions = $this->buildGroupDefinitions($definitions);
    $groups = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\link\LinkItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }

      $url = $this->buildUrl($item);
      $link_title = $url->toString();

      if (!empty($item->title)) {
        $link_title = \Drupal::token()->replace($item->title, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
      }

      $match_title = $link_title;

      if (!empty($settings['trim_length'])) {
        $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
      }

      $definition = $this->matchDefinition($url->toString(), $match_title, $definitions);
      $group_key = $definition['group_key'] ?? '__unmatched';
      if (!isset($groups[$group_key])) {
        $groups[$group_key] = [];
      }
      $is_website = (bool) ($definition['is_website'] ?? FALSE);
      $groups[$group_key][] = [
        'url' => $url,
        'label' => $link_title,
        'icon_url' => $definition['icon_url'] ?? '',
        'icon_alt' => $definition['icon_alt'] ?? '',
        'order' => $definition['order'] ?? 999999,
        'index' => $delta,
        'is_website' => $is_website,
      ];
    }

    $element['#type'] = 'container';
    $element['#attributes']['class'][] = 'magical-links-formatter__groups';
    $element['#attached']['library'][] = 'magical_links/formatter';

    foreach ($group_definitions as $group_key => $group_definition) {
      if (empty($groups[$group_key])) {
        continue;
      }
      $element[] = $this->buildGroupRenderArray($groups[$group_key], $group_definition['label'] ?? '');
    }

    if (!empty($groups['__unmatched'])) {
      $element[] = $this->buildGroupRenderArray($groups['__unmatched'], '');
    }

    $definitions_data['cacheable_metadata']->applyTo($element);

    return $element;
  }

  /**
   * Build a URL object from a link field item.
   */
  protected function buildUrl(LinkItemInterface $item): Url {
    return $item->getUrl();
  }

  /**
   * Build a render array for a group of links.
   */
  protected function buildGroupRenderArray(array $items, string $label): array {
    usort($items, static function (array $a, array $b): int {
      $order_a = (int) ($a['order'] ?? 0);
      $order_b = (int) ($b['order'] ?? 0);
      if ($order_a !== $order_b) {
        return $order_a <=> $order_b;
      }
      return (int) ($a['index'] ?? 0) <=> (int) ($b['index'] ?? 0);
    });

    $group = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['magical-links-formatter__group'],
      ],
    ];

    if ($label !== '') {
      $group['label'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['magical-links-formatter__group-title'],
        ],
        '#markup' => Html::escape($label),
      ];
    }

    $group['items'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['magical-links-formatter__group-items'],
      ],
    ];

    foreach ($items as $delta => $item) {
      $icon_markup = $this->buildIconMarkup($item['icon_url'] ?? '', $item['icon_alt'] ?? '', $item['label'] ?? '');
      $title_markup = $icon_markup . '<span class="magical-links-formatter__item-label">' . Html::escape((string) ($item['label'] ?? '')) . '</span>';
      $is_website = (bool) ($item['is_website'] ?? FALSE);
      $attributes = [
        'class' => ['magical-links-formatter__item'],
        'target' => 'nomads_links',
      ];
      $rel = ['noopener', 'noreferrer'];
      if (!$is_website) {
        $rel[] = 'nofollow';
      }
      $attributes['rel'] = implode(' ', array_unique($rel));

      $group['items'][$delta] = [
        '#type' => 'link',
        '#title' => [
          '#markup' => Markup::create($title_markup),
        ],
        '#url' => $item['url'],
        '#options' => [
          'attributes' => [
            'class' => ['magical-links-formatter__item'],
          ],
        ],
      ];
      $group['items'][$delta]['#options']['attributes'] = $attributes;
    }

    return $group;
  }

  /**
   * Build icon markup with placeholder fallback.
   */
  protected function buildIconMarkup(string $icon_url, string $icon_alt, string $label): string {
    if ($icon_url === '') {
      return '<span class="magical-links-formatter__icon magical-links-formatter__icon--placeholder" aria-hidden="true"></span>';
    }

    $alt = $icon_alt !== '' ? $icon_alt : $label;
    return '<span class="magical-links-formatter__icon"><img src="' . Html::escape($icon_url) . '" alt="' . Html::escape($alt) . '" /></span>';
  }

  /**
   * Match a definition by URL prefix or link title.
   */
  protected function matchDefinition(string $url, string $link_title, array $definitions): array {
    $url_value = trim($url);
    foreach ($definitions as $definition) {
      $prefix = $definition['prefill'] ?? '';
      if ($prefix !== '' && !$this->isGenericPrefix($prefix) && stripos($url_value, $prefix) === 0) {
        return $definition;
      }
    }

    $title_value = mb_strtolower(trim($link_title));
    foreach ($definitions as $definition) {
      $match_values = [
        (string) ($definition['icon_alt'] ?? ''),
        (string) ($definition['label'] ?? ''),
      ];
      foreach ($match_values as $match_value) {
        $match_value = mb_strtolower(trim($match_value));
        if ($match_value !== '' && $title_value === $match_value) {
          return $definition;
        }
      }
    }

    return [];
  }

  /**
   * Build ordered group definitions based on taxonomy tree order.
   */
  protected function buildGroupDefinitions(array $definitions): array {
    $groups = [];
    foreach ($definitions as $definition) {
      $group_key = $definition['group_key'] ?? '';
      if ($group_key === '' || isset($groups[$group_key])) {
        continue;
      }
      $groups[$group_key] = [
        'label' => (string) ($definition['group_label'] ?? ''),
      ];
    }

    return $groups;
  }


  /**
   * Check if a prefix is too generic for matching.
   */
  protected function isGenericPrefix(string $prefix): bool {
    $value = strtolower(trim($prefix));
    if ($value === '') {
      return TRUE;
    }

    foreach (['https://', 'http://', 'www', 'www.', 'https://www.', 'http://www.'] as $generic) {
      if ($value === $generic) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
