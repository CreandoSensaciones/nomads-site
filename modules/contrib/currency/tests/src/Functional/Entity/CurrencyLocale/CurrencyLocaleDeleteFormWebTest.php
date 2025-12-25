<?php

namespace Drupal\Tests\currency\Functional\Entity\CurrencyLocale;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the currency locale delete form.
 *
 * @group Currency
 */
class CurrencyLocaleDeleteFormWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['currency'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the form.
   */
  public function testForm() {
    $user = $this->drupalCreateUser(['currency.currency_locale.delete']);
    $this->drupalLogin($user);

    $storage = \Drupal::entityTypeManager()->getStorage('currency_locale');
    $currency_locale = $storage->create([
      'locale' => 'zz_ZZ',
    ]);
    $currency_locale->save();
    $this->drupalGet('admin/config/regional/currency-formatting/locale/' . $currency_locale->id() . '/delete');
    $this->submitForm([], t('Delete'));
    $storage->resetCache();
    $this->assertFalse((bool) $storage->load($currency_locale->id()));
  }

}
