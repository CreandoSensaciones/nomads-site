<?php

namespace Drupal\magical_links\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;

/**
 * Plugin implementation of the 'magical_links' formatter.
 */
#[FieldFormatter(
  id: 'magical_links',
  label: new TranslatableMarkup('Magical Links'),
  field_types: [
    'link',
  ],
)]
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

      if (!empty($settings['trim_length'])) {
        $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
      }

      $icon = $this->getIconByUrl($url->toString());

      $element[$delta] = [
        '#type' => 'link',
        '#title' => [
          '#markup' => $icon . '<span class="magical-links-formatter__text">' . $link_title . '</span>',
        ],
        '#url' => $url,
        '#options' => [
          'attributes' => [
            'class' => ['magical-links-formatter'],
          ],
        ],
        '#attached' => [
          'library' => ['magical_links/formatter'],
        ],
      ];
    }

    return $element;
  }

  /**
   * Build a URL object from a link field item.
   */
  protected function buildUrl(LinkItemInterface $item): Url {
    return $item->getUrl();
  }

  /**
   * Get the icon markup based on the URL.
   */
  protected function getIconByUrl(string $url): string {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (str_contains($host, 'facebook.com')) {
      return '<span class="magical-links-formatter__icon">' . $this->svgFacebook() . '</span>';
    }
    if (str_contains($host, 'instagram.com')) {
      return '<span class="magical-links-formatter__icon">' . $this->svgInstagram() . '</span>';
    }
    if (str_contains($host, 'x.com') || str_contains($host, 'twitter.com')) {
      return '<span class="magical-links-formatter__icon">' . $this->svgX() . '</span>';
    }

    return '<span class="magical-links-formatter__icon">' . $this->svgGlobe() . '</span>';
  }

  protected function svgGlobe(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a16 16 0 0 0 0 18M12 3a16 16 0 0 1 0 18" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
  }

  protected function svgFacebook(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 8h3V5h-3c-2.8 0-4.5 1.8-4.5 4.6V12H7v3h2.5v4.5h3V15H15l.5-3h-3v-2c0-1 .4-2 1.5-2z" fill="currentColor"/></svg>';
  }

  protected function svgInstagram(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="4" width="16" height="16" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17" cy="7" r="1.5" fill="currentColor"/></svg>';
  }

  protected function svgX(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 5h3.5l3.2 4.3L17 5h3l-5.4 6.3L20 19h-3.6l-3.5-4.7L9 19H6l5.7-6.7L6 5z" fill="currentColor"/></svg>';
  }

}
