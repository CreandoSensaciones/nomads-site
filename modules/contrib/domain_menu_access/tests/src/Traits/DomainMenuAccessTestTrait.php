<?php

namespace Drupal\Tests\domain_menu_access\Traits;

use Drupal\Core\Session\AccountInterface;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait DomainMenuAccessTestTrait {

  /**
   * The path to the config form.
   *
   * @var string
   */
  protected string $configFormPath = '/admin/config/domain/domain_menu_access/config';

  /**
   * A user with full permissions to use the module.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $adminUser;

  /**
   * A user with administration access but not this module.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $editorUser;

  /**
   * Create an admin user.
   */
  public function createAdminUser() {
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'administer domains',
      'administer site configuration',
    ]);
  }

  /**
   * Create an editor user.
   */
  public function createEditorUser() {
    $this->editorUser = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'administer site configuration',
    ]);
  }

}
