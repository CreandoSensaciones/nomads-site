<?php

namespace Drupal\nomads_listing_virtual_fields;

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
    $week_min = $this->getPriceFromField($entity, 'field_min_price_week');
    $week_max = $this->getFirstPriceFromFields($entity, [
      'field_max_price_field',
      'field_max_price_week',
    ]);
    $month_min = $this->getPriceFromField($entity, 'field_min_price_month');
    $month_max = $this->getPriceFromField($entity, 'field_max_price_month');

    $day_group = $this->buildGroupData($day_min, $day_max, $this->t('Per day'));
    $week_group = $this->buildGroupData($week_min, $week_max, $this->t('Per week'));
    $month_group = $this->buildGroupData($month_min, $month_max, $this->t('Per month'));

    $groups = array_filter([
      'day' => $day_group,
      'week' => $week_group,
      'month' => $month_group,
    ]);

    if (empty($groups)) {
      return NULL;
    }

    $label = $this->t('Price Range');
   

    // At most two visible segments. If all 3 groups have data, keep day+month.
    if (isset($groups['day'], $groups['week'], $groups['month'])) {
      $groups = [
        'day' => $groups['day'],
        'month' => $groups['month'],
      ];
    }
    elseif (count($groups) > 2) {
      $groups = array_slice($groups, 0, 2, TRUE);
    }

    $build = [
      '#theme' => 'nomads_price_range',
      '#label' => $label,
      '#groups' => array_values($groups),
      '#attached' => [
        'library' => [
          'nomads_listing_virtual_fields/price-range',
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

    if ($min && $max) {
      $min_number = (string) $min->getNumber();
      $max_number = (string) $max->getNumber();
      if ($min_number === $max_number && $min->getCurrencyCode() === $max->getCurrencyCode()) {
        return [
          'prefix' => $prefix,
          'value' => $this->formatPrice($min),
          'mode' => 'single',
        ];
      }

      return [
        'prefix' => $prefix,
        'min' => $this->formatPrice($min),
        'max' => $this->formatPrice($max),
        'mode' => 'range',
      ];
    }

    $single_value = $min ?: $max;

    return [
      'prefix' => $prefix,
      'value' => $single_value ? $this->formatPrice($single_value) : NULL,
      'mode' => 'single',
    ];
  }

  /**
   * Extracts the first available Price object from a list of field names.
   */
  protected function getFirstPriceFromFields(FieldableEntityInterface $entity, array $field_names): ?Price {
    foreach ($field_names as $field_name) {
      $price = $this->getPriceFromField($entity, $field_name);
      if ($price) {
        return $price;
      }
    }

    return NULL;
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
