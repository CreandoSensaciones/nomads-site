<?php

namespace Drupal\currency\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines a currency currency locale.
 */
interface CurrencyLocaleInterface extends ConfigEntityInterface {

  /**
   * Sets the decimal separator.
   *
   * @param string $separator
   *   The character used as the decimal separator (e.g., "." or ",").
   *
   * @return \Drupal\currency\Entity\CurrencyLocaleInterface
   *   The updated currency locale entity.
   */
  public function setDecimalSeparator($separator);

  /**
   * Gets the decimal separator.
   *
   * @return string
   *   The character used to separate decimals (e.g., "." or ",").
   */
  public function getDecimalSeparator();

  /**
   * Sets the grouping separator.
   *
   * @param string $separator
   *   The character used to separate groups of digits (e.g., "," or ".").
   *
   * @return \Drupal\currency\Entity\CurrencyLocaleInterface
   *   The updated currency locale entity.
   */
  public function setGroupingSeparator($separator);

  /**
   * Gets the grouping separator.
   *
   * @return string
   *   The character used to separate groups of digits.
   */
  public function getGroupingSeparator();

  /**
   * {@inheritdoc}
   *
   * The ID must be the locale, which is a lower case language code, an
   * underscore and an uppercase language code.
   *
   * @return string
   *   The unique identifier of the locale.
   */
  public function id();

  /**
   * Sets the locale.
   *
   * @param string $language_code
   *   The language code, using lowercase letters.
   * @param string $country_code
   *   The country code, using uppercase letters.
   *
   * @return \Drupal\currency\Entity\CurrencyLocaleInterface
   *   The updated currency locale entity.
   *
   * @see self::id()
   */
  public function setLocale($language_code, $country_code);

  /**
   * Gets the locale.
   *
   * @see self::id()
   *
   * @return string
   *   The locale string composed of a language and country code.
   */
  public function getLocale();

  /**
   * Gets the language code.
   *
   * @return string
   *   The language code extracted from the locale.
   */
  public function getLanguageCode();

  /**
   * Gets the country code.
   *
   * @return string
   *   The country code extracted from the locale.
   */
  public function getCountryCode();

  /**
   * Sets the CLDR pattern.
   *
   * @param string $pattern
   *   The Unicode CLDR pattern used for currency formatting.
   *
   * @return \Drupal\currency\Entity\CurrencyLocaleInterface
   *   The updated currency locale entity.
   */
  public function setPattern($pattern);

  /**
   * Returns the CLDR pattern.
   *
   * @return string
   *   The Unicode CLDR pattern used for currency formatting.
   */
  public function getPattern();

}
