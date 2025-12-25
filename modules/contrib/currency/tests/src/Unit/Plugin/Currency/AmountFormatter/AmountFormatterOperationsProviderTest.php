<?php

namespace Drupal\Tests\currency\Unit\Plugin\Currency\AmountFormatter;

use Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterOperationsProvider;
use Drupal\Tests\plugin\Unit\PluginType\DefaultPluginTypeOperationsProviderTest;

/**
 * Tests the amount formatter operations provider plugin.
 *
 * @coversDefaultClass \Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterOperationsProvider
 *
 * @group Currency
 */
class AmountFormatterOperationsProviderTest extends DefaultPluginTypeOperationsProviderTest {

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterOperationsProvider
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->sut = new AmountFormatterOperationsProvider($this->stringTranslation);
  }

}
