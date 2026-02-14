<?php

namespace Drupal\nomads_prices_virtual_fields;

use Drupal\commerce_price\Price;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds render arrays for price virtual fields.
 */
class PriceVirtualFieldBuilder {

  use StringTranslationTrait;

  /**
   * The Commerce currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected CurrencyFormatterInterface $currencyFormatter;

  /**
   * Constructs a PriceVirtualFieldBuilder instance.
   */
  public function __construct(CurrencyFormatterInterface $currency_formatter, TranslationInterface $string_translation) {
    $this->currencyFormatter = $currency_formatter;
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the price range virtual field.
   */
  public function buildPriceRange(ParagraphInterface $paragraph, string $langcode): ?array {
    $entity = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph;

    $day_min = $this->getPriceFromField($entity, 'field_min_price');
    $day_max = $this->getPriceFromField($entity, 'field_max_price');
    $month_min = $this->getPriceFromField($entity, 'field_min_price_month');
    $month_max = $this->getPriceFromField($entity, 'field_max_price_month');

    $day_group = $this->buildGroupData($day_min, $day_max, $this->t('Per day'));
    $month_group = $this->buildGroupData($month_min, $month_max, $this->t('Per month'));

    if (!$day_group && !$month_group) {
      return NULL;
    }

    $label = $this->t('Price Range');
    if ($day_group && !$month_group) {
      $label = $this->t('Price Range per day');
    }
    elseif ($month_group && !$day_group) {
      $label = $this->t('Price Range per month');
    }

    $build = [
      '#theme' => 'nomads_price_range',
      '#label' => $label,
      '#day_prefix' => $day_group['prefix'] ?? NULL,
      '#day_min' => $day_group['min'] ?? NULL,
      '#day_max' => $day_group['max'] ?? NULL,
      '#day_mode' => $day_group['mode'] ?? NULL,
      '#month_prefix' => $month_group['prefix'] ?? NULL,
      '#month_min' => $month_group['min'] ?? NULL,
      '#month_max' => $month_group['max'] ?? NULL,
      '#month_mode' => $month_group['mode'] ?? NULL,
      '#attached' => [
        'library' => [
          'nomads_prices_virtual_fields/price-range',
        ],
      ],
    ];

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['languages:language_interface']);
    $cacheability->addCacheableDependency($paragraph);
    $cacheability->applyTo($build);

    return $build;
  }

  /**
   * Builds formatted data for a price group.
   */
  protected function buildGroupData(?Price $min, ?Price $max, $prefix): ?array {
    if (!$min && !$max) {
      return NULL;
    }
    if (!$min && $max) {
      return NULL;
    }

    if ($min && $max) {
      return [
        'prefix' => $prefix,
        'min' => $this->formatPrice($min),
        'max' => $this->formatPrice($max),
        'mode' => 'range',
      ];
    }

    return [
      'prefix' => $prefix,
      'min' => $this->formatPrice($min),
      'max' => NULL,
      'mode' => 'starting_at',
    ];
  }

  /**
   * Extracts a Price object from a Commerce price field.
   */
  protected function getPriceFromField(FieldableEntityInterface $entity, string $field_name): ?Price {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return NULL;
    }

    $item = $field->first();
    if (!$item || !$item instanceof PriceItem) {
      return NULL;
    }

    $price = $item->toPrice();
    return $price instanceof Price ? $price : NULL;
  }

  /**
   * Formats a Price object using the Commerce formatter.
   */
  protected function formatPrice(Price $price): string {
    return $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode(), [
      'minimum_fraction_digits' => 0,
      'maximum_fraction_digits' => 0,
    ]);
  }

}
