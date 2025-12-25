<?php

namespace Drupal\currency;

use Commercie\CurrencyExchange\ExchangeRateInterface as GenericExchangeRateInterface;

/**
 * Defines an exchange rate.
 *
 * Implementations may optionally implement any of the following interfaces:
 * - `\Drupal\Core\Cache\CacheableDependencyInterface`.
 */
interface ExchangeRateInterface extends GenericExchangeRateInterface {

  /**
   * Gets the plugin ID of the exchange rate provider that provided this rate.
   *
   * @return string|null
   *   The provider plugin ID, or NULL if not known.
   */
  public function getExchangeRateProviderId();

  /**
   * Sets the plugin ID of the exchange rate provider that provided this rate.
   *
   * @param string $id
   *   The provider plugin ID.
   *
   * @return $this
   *   The current exchange rate.
   */
  public function setExchangeRateProviderId($id);

}
