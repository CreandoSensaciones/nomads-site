<?php

namespace Drupal\Tests\domain_menu_access\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_menu_access\Traits\DomainMenuAccessTestTrait;

/**
 * Tests the domain menu access user interface.
 *
 * @group domain_menu_access
 */
class DomainMenuAccessPermissionsTest extends DomainTestBase {

  use DomainTestTrait;
  use DomainMenuAccessTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'menu_link_content',
    'domain',
    'domain_access',
    'domain_menu_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAdminUser();
    $this->createEditorUser();

    $this->domainCreateTestDomains();
  }

  /**
   * Tests access to the settings form.
   */
  public function testSettingsAccess() {
    $path = $this->configFormPath;

    // Visit the domain menu access administration page as admin.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    // Visit the domain menu access administration page as editor.
    $this->drupalLogin($this->editorUser);
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);
  }

}
