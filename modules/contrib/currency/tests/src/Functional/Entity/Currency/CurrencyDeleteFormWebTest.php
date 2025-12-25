<?php

namespace Drupal\Tests\currency\Functional\Entity\Currency;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the currency delete form.
 *
 * @group Currency
 */
class CurrencyDeleteFormWebTest extends BrowserTestBase {

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
    $user = $this->drupalCreateUser(['currency.currency.delete']);
    $this->drupalLogin($user);

    $storage = \Drupal::entityTypeManager()->getStorage('currency');

    $currency = $storage->create([
      'currencyCode' => 'ABC',
      'label' => 'ABC',
    ]);
    $currency->save();
    $this->drupalGet('admin/config/regional/currency/' . $currency->id() . '/delete');
    $this->submitForm([], t('Delete'));
    $storage->resetCache();
    $this->assertFalse((bool) $storage->load($currency->id()));
  }

}
