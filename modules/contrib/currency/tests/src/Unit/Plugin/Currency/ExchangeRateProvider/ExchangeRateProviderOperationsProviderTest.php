<?php

namespace Drupal\Tests\currency\Unit\Plugin\Currency\ExchangeRateProvider;

use Drupal\currency\Plugin\Currency\ExchangeRateProvider\ExchangeRateProviderOperationsProvider;
use Drupal\Tests\plugin\Unit\PluginType\DefaultPluginTypeOperationsProviderTest;

/**
 * Tests the exchange rate provider operations provider plugin.
 *
 * @coversDefaultClass \Drupal\currency\Plugin\Currency\ExchangeRateProvider\ExchangeRateProviderOperationsProvider
 *
 * @group Currency
 */
class ExchangeRateProviderOperationsProviderTest extends DefaultPluginTypeOperationsProviderTest {

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Plugin\Currency\ExchangeRateProvider\ExchangeRateProviderOperationsProvider
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->sut = new ExchangeRateProviderOperationsProvider($this->stringTranslation);
  }

}
