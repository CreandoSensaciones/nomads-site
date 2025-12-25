<?php

namespace Drupal\Tests\currency\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the UI of the exchange rate provider configuration form.
 *
 * @group Currency
 */
class PluginBasedExchangeRateProviderFormWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['currency'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test CurrencyExchanger's UI.
   */
  public function testCurrencyExchangerUi() {
    $exchange_delegator = \Drupal::service('currency.exchange_rate_provider');

    $user = $this->drupalCreateUser(['currency.exchange_rate_provider.administer']);
    $this->drupalLogin($user);

    // Test the default configuration.
    $this->assertEquals([
      'currency_fixed_rates' => TRUE,
      'currency_historical_rates' => TRUE,
    ], $exchange_delegator->loadConfiguration());
    // Test overridden configuration.
    $path = 'admin/config/regional/currency-exchange';
    $values = [
      'exchange_rate_providers[currency_fixed_rates][enabled]' => FALSE,
    ];
    $this->drupalGet($path);
    $this->submitForm($values, t('Save'));
    $this->assertEquals([
      'currency_fixed_rates' => FALSE,
      'currency_historical_rates' => TRUE,
    ], $exchange_delegator->loadConfiguration());
  }

}
