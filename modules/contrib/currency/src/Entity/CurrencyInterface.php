<?php

namespace Drupal\currency\Entity;

use Commercie\Currency\CurrencyInterface as GenericCurrencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines a currency.
 */
interface CurrencyInterface extends GenericCurrencyInterface, ConfigEntityInterface {

  /**
   * Formats a currency amount using the default locale pattern.
   *
   * This method applies the configured currency precision and locale settings
   * to format the amount correctly.
   *
   * @param string $amount
   *   A numeric string.
   * @param bool $use_currency_precision
   *   Whether or not to use the precision (number of decimals) that the
   *   currency is configured to. If FALSE, the amount will be formatted as-is.
   * @param string $language_type
   *   One of the \Drupal\Core\Language\LanguageInterface\TYPE_* constants.
   *
   * @return string
   *   The formatted currency amount as a string.
   */
  public function formatAmount($amount, $use_currency_precision = TRUE, $language_type = LanguageInterface::TYPE_CONTENT);

}
