<?php

namespace Drupal\Tests\currency_intl\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Module installation and uninstallation.
 *
 * @group Currency Intl
 */
class ModuleInstallUninstallWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['currency_intl'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test uninstall.
   */
  public function testUninstallation() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
    $module_installer = \Drupal::service('module_installer');
    $module_handler = \Drupal::moduleHandler();
    $this->assertTrue($module_handler->moduleExists('currency_intl'));
    $module_installer->uninstall(['currency_intl']);
    $this->assertFalse($module_handler->moduleExists('currency_intl'));
  }

}
