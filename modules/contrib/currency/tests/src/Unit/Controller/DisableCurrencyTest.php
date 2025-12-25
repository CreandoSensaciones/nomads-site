<?php

namespace Drupal\Tests\currency\Unit\Controller;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\currency\Controller\DisableCurrency;
use Drupal\currency\Entity\CurrencyInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Tests the disable currency controller.
 *
 * @coversDefaultClass \Drupal\currency\Controller\DisableCurrency
 *
 * @group Currency
 */
class DisableCurrencyTest extends UnitTestCase {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $urlGenerator;

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Controller\DisableCurrency
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

    // @phpstan-ignore-next-line
    $this->sut = new DisableCurrency($this->urlGenerator);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $map = [
      ['url_generator', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->urlGenerator],
    ];
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    // @phpstan-ignore-next-line
    $sut = DisableCurrency::create($container);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(DisableCurrency::class, $sut);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $url = $this->randomMachineName();

    $currency = $this->createMock(CurrencyInterface::class);
    $currency->expects($this->once())
      ->method('disable');
    $currency->expects($this->once())
      ->method('save');

    $this->urlGenerator->expects($this->once())
      ->method('generateFromRoute')
      ->with('entity.currency.collection')
      ->willReturn($url);

    // @phpstan-ignore-next-line
    $response = $this->sut->execute($currency);
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertSame($url, $response->getTargetUrl());
  }

}
