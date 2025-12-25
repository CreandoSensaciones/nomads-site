<?php

namespace Drupal\Tests\currency\Functional;

use Drupal\currency\Entity\Currency;
use Drupal\currency\Entity\CurrencyInterface;
use Drupal\currency\Entity\CurrencyLocale;
use Drupal\currency\Entity\CurrencyLocaleInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests installation and uninstallation.
 *
 * @group Currency
 */
class ModuleInstallUninstallWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['currency'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests installation and uninstallation.
   */
  public function testInstallationAndUninstallation() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_handler = \Drupal::moduleHandler();

    $this->assertTrue(Currency::load('XXX') instanceof CurrencyInterface);
    $this->assertTrue(CurrencyLocale::load('en_US') instanceof CurrencyLocaleInterface);

    $module_installer->uninstall(['currency']);
    $this->assertFalse($module_handler->moduleExists('currency'));
  }

}
