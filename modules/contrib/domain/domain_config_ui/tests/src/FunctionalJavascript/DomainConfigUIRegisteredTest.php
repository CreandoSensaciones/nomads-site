<?php

namespace Drupal\Tests\domain_config_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\domain\Traits\DomainTestTrait;
use Drupal\Tests\domain_config_ui\Traits\DomainConfigUITestTrait;
use Drupal\domain_config_ui\DomainConfigUITrait;

/**
 * Tests the domain config ui for registered paths.
 *
 * @group domain_config_ui
 */
class DomainConfigUIRegisteredTest extends WebDriverTestBase {

  use DomainConfigUITrait;
  use DomainConfigUITestTrait;
  use DomainTestTrait;

  /**
   * Disabled config schema checking.
   *
   * Domain Config actually duplicates schemas provided by other modules,
   * so it cannot define its own.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // phpcs:ignore

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'domain_config_ui_test',
    'domain_config_ui',
    'language',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createAdminUser();
    $this->createEditorUser();

    $this->setBaseHostname();
    $this->domainCreateTestDomains(5);

    $this->createLanguage();
  }

  /**
   * Tests that domain-specific configuration are limited to registered paths.
   */
  public function testRegisteredPaths() {
    $session = $this->getSession();
    $page = $session->getPage();
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    // Activate the "Remember domain selection" configuration option.
    $this->drupalGet('/admin/config/domain/config-ui');
    $assert->waitForButton('Save configuration');
    $page->checkField('edit-remember-domain');
    $page->pressButton('Save configuration');
    $this->assertTrue($assert->waitForText('The configuration options have been saved'));
    $this->htmlOutput($page->getHtml());

    // Visit the site information page to define the default selected domain.
    $this->drupalGet('/admin/config/system/site-information');
    // Select the one.example.com domain.
    $page->selectFieldOption('domain', 'one_example_com');
    $this->assertTrue($assert->waitForText('This configuration will be saved for the Test One domain'));
    $this->htmlOutput($page->getHtml());

    // Update configuration for an unregistered path.
    // This update should not create a domain-specific configuration.
    $this->drupalGet('/admin/config/domain_config_ui_test/form_unregistered');
    $assert->waitForButton('Save configuration');
    $field1_value = 'New field 1 value for unregistered';
    $page->fillField('field1', $field1_value);
    $page->pressButton('Save configuration');
    $this->assertTrue($assert->waitForText('The configuration options have been saved'));
    $this->htmlOutput($page->getHtml());

    // Test that the domain-specific configuration has not been created.
    $config_name = 'domain.config.one_example_com.domain_config_ui_test_unregistered.settings';
    $config = \Drupal::configFactory()->get($config_name);

    // Config being new means it was not found in storage.
    $this->assertTrue($config->isNew(), 'The domain-specific configuration has not been created for an unregistered path.');
  }

}
