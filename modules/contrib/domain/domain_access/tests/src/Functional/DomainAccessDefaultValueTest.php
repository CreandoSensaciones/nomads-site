<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_access\DomainAccessManager;

/**
 * Tests the domain access handling of default field values.
 *
 * @see https://www.drupal.org/node/2779133
 *
 * @group domain_access
 */
class DomainAccessDefaultValueTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['domain', 'domain_access', 'field', 'field_ui'];

  /**
   * Test the usage of DomainAccessManager::getDefaultValue().
   */
  public function testDomainAccessDefaultValue() {
    $admin_user = $this->drupalCreateUser([
      'bypass node access',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer domains',
      'publish to any domain',
    ]);
    $this->drupalLogin($admin_user);

    // Create 5 domains.
    $this->domainCreateTestDomains(5);

    // Visit the article field display administration page.
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);

    // Check the new field exists on the page.
    $this->assertSession()->pageTextContains('Domain Access');
    $this->assertSession()->responseContains('name="field_domain_access[example_com]" value="example_com" checked="checked"');
    // Check the all affiliates field.
    $this->assertSession()->pageTextContains('Send to all affiliates');
    $this->assertSession()->responseNotContains('name="field_domain_all_affiliates[value]" value="1" checked="checked"');

    // Now save the node with the values set.
    $edit = [
      'title[0][value]' => 'Test node',
      'field_domain_access[example_com]' => 'example_com',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');

    // Load the node.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $this->assertNotNull($node, 'Article node created.');
    // Check that the values are set.
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(1, $values, 'Node saved with one domain record.');
    $allValue = DomainAccessManager::getAllValue($node);
    $this->assertEmpty($allValue, 'Not sent to all affiliates.');

    // Logout the admin user.
    $this->drupalLogout();

    // Create a limited value user.
    $test_user = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
    ]);

    // Login and try to edit the created node.
    $this->drupalLogin($test_user);

    $this->drupalGet('node/1/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Now save the node with the values set.
    $edit = [
      'title[0][value]' => 'Test node update',
    ];
    $this->drupalGet('node/1/edit');
    $this->submitForm($edit, 'Save');

    // Load the node.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $this->assertNotNull($node, 'Article node created.');
    // Check that the values are set.
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(1, $values, 'Node saved with one domain record.');
    $allValue = DomainAccessManager::getAllValue($node);
    $this->assertEmpty($allValue, 'Not sent to all affiliates.');

    // Now create as a user with limited rights.
    $editor = $this->drupalCreateUser([
      'create article content on assigned domains',
      'update article content on assigned domains',
      'publish to any assigned domain',
    ]);
    $ids = ['example_com', 'one_example_com'];
    $this->addDomainsToEntity('user', $editor->id(), $ids, DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user = $user_storage->load($editor->id());
    $values = DomainAccessManager::getAccessValues($user);
    $this->assertCount(2, $values, 'User saved with two domain records.');
    // Login as that limited rights' user.
    $this->drupalLogin($editor);

    $field = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->load('node.article.' . DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD);

    // Switch add_current_domain third-party setting to FALSE.
    $field->setThirdPartySetting('domain_access', 'add_current_domain', FALSE);
    $field->save();

    // Create a new article content.
    $this->drupalGet('node/add/article');

    // Check that the example_com domain is not checked anymore.
    $this->assertSession()->pageTextContains('Domain Access');
    $checkbox = $this->assertSession()->fieldExists('field_domain_access[example_com]');
    $this->assertFalse($checkbox->isChecked());

    // Switch add_current_domain third-party setting back to TRUE.
    $field->setThirdPartySetting('domain_access', 'add_current_domain', TRUE);
    $field->save();

    // Create a new article content but on one_example_com domain this time.
    $one = $this->getDomains()['one_example_com'];
    $this->drupalGet($one->getPath() . 'node/add/article');

    // Check that the example_com domain is not checked but one_example_com is.
    $checkbox = $this->assertSession()->fieldExists('field_domain_access[example_com]');
    $this->assertFalse($checkbox->isChecked());
    $one_checkbox = $this->assertSession()->fieldExists('field_domain_access[one_example_com]');
    $this->assertTrue($one_checkbox->isChecked());

  }

}
