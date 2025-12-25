<?php

namespace Drupal\Tests\currency\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the amount formatting configuration form.
 *
 * @group Currency
 */
class AmountFormattingFormWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['currency'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests listing().
   */
  public function testListing() {
    $account = $this->drupalCreateUser(['access administration pages']);
    $this->drupalLogin($account);
    $this->drupalGet('admin/config/regional/currency-formatting');
    $this->assertSession()->statusCodeEquals('403');

    $account = $this->drupalCreateUser(['currency.amount_formatting.administer']);
    $this->drupalLogin($account);
    $this->drupalGet('admin/config/regional/currency-formatting');
    $this->assertSession()->statusCodeEquals('200');

    $this->assertSession()->checkboxChecked('edit-default-plugin-id-currency-basic');
    $this->submitForm([], t('Save configuration'));
  }

}
