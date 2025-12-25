<?php

namespace Drupal\Tests\currency\Unit\Plugin\Currency\ExchangeRateProvider;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\currency\Plugin\Currency\ExchangeRateProvider\FixedRates;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the fixed rates exchange rate provider plugin.
 *
 * @coversDefaultClass \Drupal\currency\Plugin\Currency\ExchangeRateProvider\FixedRates
 *
 * @group Currency
 */
class FixedRatesTest extends UnitTestCase {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Plugin\Currency\ExchangeRateProvider\FixedRates
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $configuration = [];
    $plugin_id = $this->randomMachineName();
    $plugin_definition = [];

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    $this->sut = new FixedRates($configuration, $plugin_id, $plugin_definition, $this->configFactory);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $map = [
      ['config.factory', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->configFactory],
    ];
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $sut = FixedRates::create($container, [], '', []);
    $this->assertInstanceOf(FixedRates::class, $sut);
  }

  /**
   * @covers ::loadAll
   */
  public function testLoadConfiguration() {
    [$rates] = $this->prepareExchangeRates();
    $this->assertSame($rates, $this->sut->loadAll());
  }

  /**
   * @covers ::save
   * @covers ::load
   */
  public function testLoad() {
    [$rates] = $this->prepareExchangeRates();
    $reverse_rate = '0.511291';

    // Test rates that are stored in config.
    $this->assertSame($rates['EUR']['NLG'], $this->sut->load('EUR', 'NLG')->getRate());
    $this->assertSame($rates['NLG']['EUR'], $this->sut->load('NLG', 'EUR')->getRate());
    $this->assertSame($rates['EUR']['DEM'], $this->sut->load('EUR', 'DEM')->getRate());

    // Test a rate that is calculated on-the-fly.
    $this->assertSame($reverse_rate, $this->sut->load('DEM', 'EUR')->getRate());

    // Test an unavailable exchange rate.
    $this->assertNull($this->sut->load('NLG', 'UAH'));
  }

  /**
   * @covers ::loadMultiple
   */
  public function testLoadMultiple() {
    [$rates] = $this->prepareExchangeRates();

    $rates = [
      'EUR' => [
        'NLG' => $rates['EUR']['NLG'],
      ],
      'NLG' => [
        'EUR' => $rates['NLG']['EUR'],
      ],
      'ABC' => [
        'XXX' => NULL,
      ],
    ];

    $returned_rates = $this->sut->loadMultiple([
      // Test a rate that is stored in config.
      'EUR' => ['NLG'],
      // Test a reverse exchange rate.
      'NLG' => ['EUR'],
      // Test an unavailable exchange rate.
      'ABC' => ['XXX'],
    ]);
    $this->assertSame($rates['EUR']['NLG'], $returned_rates['EUR']['NLG']->getRate());
    $this->assertSame($rates['NLG']['EUR'], $returned_rates['NLG']['EUR']->getRate());
    $this->assertNull($returned_rates['ABC']['XXX']);
  }

  /**
   * @covers ::save
   */
  public function testSave() {
    $currency_code_from = $this->randomMachineName(3);
    $currency_code_to = $this->randomMachineName(3);
    $rate = mt_rand();
    [$rates, $rates_data] = $this->prepareExchangeRates();
    $rates[$currency_code_from][$currency_code_to] = $rate;
    $rates_data[] = [
      'currency_code_from' => $currency_code_from,
      'currency_code_to' => $currency_code_to,
      'rate' => $rate,
    ];

    $this->config->expects($this->once())
      ->method('set')
      ->with('rates', $rates_data);
    $this->config->expects($this->once())
      ->method('save');

    $this->sut->save($currency_code_from, $currency_code_to, $rate);
  }

  /**
   * @covers ::delete
   */
  public function testDelete() {
    [$rates, $rates_data] = $this->prepareExchangeRates();
    unset($rates['EUR']['NLG']);
    unset($rates_data[1]);

    $this->config->expects($this->once())
      ->method('set')
      ->with('rates', $rates_data);
    $this->config->expects($this->once())
      ->method('save');

    $this->sut->delete('EUR', 'NLG');
  }

  /**
   * Stores random exchange rates in the mocked config and returns them.
   *
   * @return array
   *   An array of the same format as the return value of
   *   \Drupal\currency\Plugin\Currency\ExchangeRateProvider\FixedRates::loadAll().
   */
  protected function prepareExchangeRates() {
    $rates = [
      'EUR' => [
        'DEM' => '1.95583',
        'NLG' => '2.20371',
      ],
      'NLG' => [
        'EUR' => '0.453780216',
      ],
    ];
    $rates_data = [];
    foreach ($rates as $currency_code_from => $currency_code_from_rates) {
      foreach ($currency_code_from_rates as $currency_code_to => $rate) {
        $rates_data[] = [
          'currency_code_from' => $currency_code_from,
          'currency_code_to' => $currency_code_to,
          'rate' => $rate,
        ];
      }
    }

    $this->config = $this->createMock(Config::class);
    $this->config->expects($this->any())
      ->method('get')
      ->with('rates')
      ->willReturn($rates_data);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('currency.exchanger.fixed_rates')
      ->willReturn($this->config);
    $this->configFactory->expects($this->any())
      ->method('getEditable')
      ->with('currency.exchanger.fixed_rates')
      ->willReturn($this->config);

    return [$rates, $rates_data];
  }

}
