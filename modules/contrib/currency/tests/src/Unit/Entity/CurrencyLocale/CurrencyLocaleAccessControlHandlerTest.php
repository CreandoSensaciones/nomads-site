<?php

namespace Drupal\Tests\currency\Unit\Entity\CurrencyLocale;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\currency\Entity\CurrencyLocale\CurrencyLocaleAccessControlHandler;
use Drupal\currency\Entity\CurrencyLocaleInterface;
use Drupal\currency\LocaleResolverInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\TestTools\Random;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the currency locale access control handler.
 *
 * @coversDefaultClass \Drupal\currency\Entity\CurrencyLocale\CurrencyLocaleAccessControlHandler
 *
 * @group Currency
 */
class CurrencyLocaleAccessControlHandlerTest extends UnitTestCase {

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheContextsManager;

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Entity\CurrencyLocale\CurrencyLocaleAccessControlHandler
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $this->cacheContextsManager->expects($this->any())
      ->method('assertValidTokens')
      ->willReturn(TRUE);

    $container = new Container();
    $container->set('cache_contexts_manager', $this->cacheContextsManager);
    \Drupal::setContainer($container);

    $this->entityType = $this->createMock(EntityTypeInterface::class);

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->sut = new CurrencyLocaleAccessControlHandler($this->entityType, $this->moduleHandler);
  }

  /**
   * @covers ::createInstance
   * @covers ::__construct
   */
  public function testCreateInstance() {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
      ->method('get')
      ->with('module_handler')
      ->willReturn($this->moduleHandler);

    $sut = CurrencyLocaleAccessControlHandler::createInstance($container, $this->entityType);
    $this->assertInstanceOf(CurrencyLocaleAccessControlHandler::class, $sut);
  }

  /**
   * Tests access check logic based on different conditions.
   *
   * @covers ::checkAccess
   * @dataProvider providerTestCheckAccess
   */
  public function testCheckAccess($expected_value, $operation, $has_permission, $permission, $locale = NULL) {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())
      ->method('hasPermission')
      ->with($permission)
      ->willReturn((bool) $has_permission);

    $currency_locale = $this->createMock(CurrencyLocaleInterface::class);
    $currency_locale->expects($this->any())
      ->method('getLocale')
      ->willReturn($locale);

    $this->moduleHandler->expects($this->any())
      ->method('invokeAll')
      ->willReturn([]);

    $method = new \ReflectionMethod($this->sut, 'checkAccess');
    $method->setAccessible(TRUE);

    $this->assertSame($expected_value, $method->invoke($this->sut, $currency_locale, $operation, $account)->isAllowed());
  }

  /**
   * Provides data to self::testCheckAccess().
   */
  public static function providerTestCheckAccess() {
    return [
      // The default currency locale cannot be deleted, even with permission.
      [FALSE, 'delete', TRUE, 'currency.currency_locale.delete', LocaleResolverInterface::DEFAULT_LOCALE],
      // Any non-default currency locale can be deleted with permission.
      [TRUE, 'delete', TRUE, 'currency.currency_locale.delete', Random::machineName()],
      // No currency locale can be deleted without permission.
      [FALSE, 'delete', FALSE, 'currency.currency_locale.delete', Random::machineName()],
      // Any currency locale can be updated with permission.
      [TRUE, 'update', TRUE, 'currency.currency_locale.update', Random::machineName()],
      // No currency locale can be updated without permission.
      [FALSE, 'update', FALSE, 'currency.currency_locale.update', Random::machineName()],
    ];
  }

  /**
   * @covers ::checkCreateAccess
   *
   * @dataProvider providerTestCheckCreateAccess
   */
  public function testCheckCreateAccess($expected_value, $has_permission) {
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('currency.currency_locale.create')
      ->willReturn($has_permission);
    $context = [];

    $method = new \ReflectionMethod($this->sut, 'checkCreateAccess');
    $method->setAccessible(TRUE);

    $this->assertSame($expected_value, $method->invoke($this->sut, $account, $context)->isAllowed());
  }

  /**
   * Provides data to self::testCheckCreateAccess().
   */
  public static function providerTestCheckCreateAccess() {
    return [
      [TRUE, TRUE],
      [FALSE, FALSE],
    ];
  }

}
