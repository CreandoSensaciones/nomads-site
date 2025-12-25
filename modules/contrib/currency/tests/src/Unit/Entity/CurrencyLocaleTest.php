<?php

namespace Drupal\Tests\currency\Unit\Entity;

use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\currency\Entity\CurrencyLocale;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the currency locale entity.
 *
 * @coversDefaultClass \Drupal\currency\Entity\CurrencyLocale
 *
 * @group Currency
 */
class CurrencyLocaleTest extends UnitTestCase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $countryManager;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * The class under test.
   *
   * @var \Drupal\currency\Entity\CurrencyLocale
   */
  protected $sut;

  /**
   * {@inheritdoc}
   *
   * @covers ::setCountryManager
   */
  public function setUp(): void {
    parent::setUp();
    $this->countryManager = $this->createMock(CountryManagerInterface::class);

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->sut = new CurrencyLocale([], $this->randomMachineName());
    $this->sut->setCountryManager($this->countryManager);
    $this->sut->setStringTranslation($this->stringTranslation);
  }

  /**
   * @covers ::setDecimalSeparator
   * @covers ::getDecimalSeparator
   */
  public function testGetDecimalSeparator() {
    $separator = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setDecimalSeparator($separator));
    $this->assertSame($separator, $this->sut->getDecimalSeparator());
  }

  /**
   * @covers ::setGroupingSeparator
   * @covers ::getGroupingSeparator
   */
  public function testGetGroupingSeparator() {
    $separator = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setGroupingSeparator($separator));
    $this->assertSame($separator, $this->sut->getGroupingSeparator());
  }

  /**
   * @covers ::setLocale
   * @covers ::getLocale
   */
  public function testGetLocale() {
    $language_code = $this->randomMachineName();
    $country_code = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setLocale($language_code, $country_code));
    $this->assertSame(strtolower($language_code) . '_' . strtoupper($country_code), $this->sut->getLocale());
  }

  /**
   * @covers ::id
   *
   * @depends testGetLocale
   */
  public function testId() {
    $language_code = $this->randomMachineName();
    $country_code = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setLocale($language_code, $country_code));
    $this->assertSame(strtolower($language_code) . '_' . strtoupper($country_code), $this->sut->id());
  }

  /**
   * @covers ::getLanguageCode
   *
   * @depends testGetLocale
   */
  public function testGetLanguageCode() {
    $language_code = $this->randomMachineName();
    $country_code = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setLocale($language_code, $country_code));
    $this->assertSame(strtolower($language_code), $this->sut->getLanguageCode());
  }

  /**
   * @covers ::getCountryCode
   *
   * @depends testGetLocale
   */
  public function testGetCountryCode() {
    $language_code = $this->randomMachineName();
    $country_code = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setLocale($language_code, $country_code));
    $this->assertSame(strtoupper($country_code), $this->sut->getCountryCode());
  }

  /**
   * @covers ::setPattern
   * @covers ::getPattern
   */
  public function testGetPattern() {
    $pattern = $this->randomMachineName();
    $this->assertSame($this->sut, $this->sut->setPattern($pattern));
    $this->assertSame($pattern, $this->sut->getPattern());
  }

  /**
   * @covers ::label
   * @covers ::getCountryManager
   *
   * @depends testGetLocale
   */
  public function testLabel() {
    $languages = LanguageManager::getStandardLanguageList();

    $language_code = array_rand($languages);

    $country_code_a = strtoupper($this->randomMachineName());
    $country_code_b = strtoupper($this->randomMachineName());
    $country_code_c = strtoupper($this->randomMachineName());

    $country_list = [
      $country_code_a => $this->randomMachineName(),
      $country_code_b => $this->randomMachineName(),
      $country_code_c => $this->randomMachineName(),
    ];

    $this->countryManager->expects($this->atLeastOnce())
      ->method('getList')
      ->willReturn($country_list);

    $this->sut->setLocale($language_code, $country_code_b);

    $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->label());
  }

}
