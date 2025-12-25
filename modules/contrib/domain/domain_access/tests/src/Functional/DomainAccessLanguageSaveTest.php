<?php

namespace Drupal\Tests\domain_access\Functional;

use Drupal\Tests\domain\Functional\DomainTestBase;
use Drupal\domain_access\DomainAccessManager;
use Drupal\domain_access\DomainAccessManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests saving the domain access field elements in multiple languages.
 *
 * @group domain_access
 */
class DomainAccessLanguageSaveTest extends DomainTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain',
    'domain_access',
    'field',
    'user',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create 5 domains.
    $this->domainCreateTestDomains(5);

    // Add Hungarian and Afrikaans.
    ConfigurableLanguage::createFromLangcode('hu')->save();
    ConfigurableLanguage::createFromLangcode('af')->save();

    // Enable content translation for the current entity type.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * Basic test setup.
   */
  public function testDomainAccessSave() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    // Save a node programmatically.
    $node = $storage->create([
      'type' => 'article',
      'title' => 'Test node',
      'uid' => '1',
      'status' => 1,
      DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD => ['example_com'],
      DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD => 1,
    ]);
    $node->save();

    // Load the node.
    $node = $storage->load(1);

    // Check that two values are set properly.
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(1, $values, 'Node saved with one domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertTrue($value, 'Node saved to all affiliates.');

    // Create an Afrikaans translation assigned to domain 2.
    $translation = $node->addTranslation('af');
    $translation->set('title', $this->randomString());
    $translation->set(DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD, [
      'example_com',
      'one_example_com',
    ]);
    $translation->set(DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD, 0);
    $translation->set('status', 1);
    $node->save();

    // Load and check the translated node.
    $parent_node = $storage->load(1);
    $node = $parent_node->getTranslation('af');
    $values = DomainAccessManager::getAccessValues($node);
    $this->assertCount(2, $values, 'Node saved with two domain records.');
    $value = DomainAccessManager::getAllValue($node);
    $this->assertFalse($value, 'Node not saved to all affiliates.');
  }

}
