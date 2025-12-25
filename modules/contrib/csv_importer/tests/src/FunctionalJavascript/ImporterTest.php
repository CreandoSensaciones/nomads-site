<?php

namespace Drupal\Tests\csv_importer\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\language\Traits\LanguageTestTrait;

/**
 * Tests the CSV Importer functionality using JavaScript.
 *
 * @group csv_importer
 */
class ImporterTest extends WebDriverTestBase {

  use LanguageTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'csv_importer',
    'node',
    'field',
    'text',
    'file',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Initialize services.
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityDisplayRepository = $this->container->get('entity_display.repository');
    $this->moduleExtensionList = $this->container->get('extension.list.module');
    $this->languageManager = $this->container->get('language_manager');

    $account = $this->drupalCreateUser([
      'administer site configuration',
      'administer users',
      'administer nodes',
      'access user profiles',
      'access csv importer',
    ]);

    $this->drupalLogin($account);

    $content_type = [
      'type' => 'page',
      'name' => 'Basic page',
      'description' => 'A page content type.',
    ];

    NodeType::create($content_type)->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_text'),
      'bundle' => 'page',
      'label' => 'Text',
      'settings' => ['display_summary' => TRUE],
    ])->save();

    $this->entityDisplayRepository->getFormDisplay('node', 'page', 'default')->save();
    $this->entityDisplayRepository->getViewDisplay('node', 'page', 'default')->save();

    static::createLanguageFromLangcode('fr');
    static::enableBundleTranslation('node', 'page');
    static::setFieldTranslatable('node', 'page', 'field_text', TRUE);
  }

  /**
   * Tests that the CSV Importer page is accessible.
   */
  public function testPageLoad() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->assertSession()->pageTextContains('Import CSV');
    $this->assertSession()->fieldExists('Select entity type');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('Select entity bundle');
    $this->assertSession()->fieldExists('Select delimiter');
    $this->assertSession()->fieldExists('Select CSV file');
  }

  /**
   * Tests the CSV file upload (add) process.
   */
  public function testFileUploadAddProcess() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = $this->moduleExtensionList->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    $this->assertSession()->waitForText('3 new content added', 30000);
  }

  /**
   * Tests the CSV file upload (update) process.
   */
  public function testFileUploadUpdateProcess() {
    $node = Node::create([
      'nid' => 1010,
      'type' => 'page',
      'title' => 'Original page 1',
      'field_text' => [
        ['value' => 'Original body value 1'],
        ['value' => 'Original body value 2'],
      ],
    ]);
    $node->enforceIsNew(TRUE);
    $node->save();

    // Assert the node is created correctly.
    $created_node = Node::load(1010);
    $this->assertEquals('Original page 1', $created_node->getTitle());
    $this->assertEquals('Original body value 1', $created_node->get('field_text')->get(0)->value);
    $this->assertEquals('Original body value 2', $created_node->get('field_text')->get(1)->value);

    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = $this->moduleExtensionList->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    // Wait for batch processing to complete (up to 30 seconds).
    $this->assertSession()->waitForText('new content added', 30000);
    $this->assertSession()->pageTextContains('2 new content added');
    $this->entityTypeManager->getStorage('node')->resetCache([1010]);
    $updated_node = Node::load(1010);
    $this->assertEquals('Test page 3 updated', $updated_node->getTitle());
    $this->assertEquals('Text 5 value', $updated_node->get('field_text')->get(0)->value);
    $this->assertEquals('Text 6 value', $updated_node->get('field_text')->get(1)->value);
  }

  /**
   * Tests the CSV file upload process for multiple fields.
   */
  public function testFileUploadWithMultipleField() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = $this->moduleExtensionList->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    $this->assertSession()->waitForText('new content added', 30000);
    $this->assertSession()->pageTextContains('3 new content added');

    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->entityTypeManager->getStorage('node')->load(1000);
    $body_values = $node->get('field_text')->getValue();
    $this->assertEquals('Text 1 value', $body_values[0]['value']);
    $this->assertEquals('Text 2 value', $body_values[1]['value']);

    $node = $this->entityTypeManager->getStorage('node')->load(1001);
    $body_values = $node->get('field_text')->getValue();
    $this->assertEquals('Text 3 value', $body_values[0]['value']);
    $this->assertEquals('Text 4 value', $body_values[1]['value']);
  }

  /**
   * Tests the history page when no imports exist.
   */
  public function testEmptyHistoryPage() {
    $this->drupalGet('/admin/content/csv-importer/history');
    $this->assertSession()->pageTextContains('History');
    $this->assertSession()->pageTextContains('No CSV imports found');
  }

  /**
   * Tests the import history and revert functionality.
   */
  public function testHistoryAndRevert() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = $this->moduleExtensionList->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    $this->assertSession()->waitForText('new content added', 30000);
    $this->assertSession()->pageTextContains('3 new content added');

    $this->drupalGet('/admin/content/csv-importer/history');
    $this->assertSession()->pageTextContains('History');
    $this->assertSession()->pageTextContains('sample.csv');
    $this->assertSession()->pageTextContains('Active');
    $this->assertSession()->linkExists('Revert');

    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_1000 = $node_storage->load(1000);
    $node_1001 = $node_storage->load(1001);
    $node_1010 = $node_storage->load(1010);
    $this->assertNotNull($node_1000, 'Node 1000 exists before revert');
    $this->assertNotNull($node_1001, 'Node 1001 exists before revert');
    $this->assertNotNull($node_1010, 'Node 1010 exists before revert');

    $this->clickLink('Revert');

    $this->assertSession()->waitForText('Successfully reverted import', 15000);
    $this->assertSession()->pageTextContains('Deleted 3 entities');
    $this->assertSession()->pageTextContains('Rolled back');
    $this->assertSession()->linkNotExists('Revert');

    $node_storage->resetCache([1000, 1001, 1010]);
    $node_1000 = $node_storage->load(1000);
    $node_1001 = $node_storage->load(1001);
    $node_1010 = $node_storage->load(1010);
    $this->assertNull($node_1000, 'Node 1000 was deleted after revert');
    $this->assertNull($node_1001, 'Node 1001 was deleted after revert');
    $this->assertNull($node_1010, 'Node 1010 was deleted after revert');

    $database = $this->container->get('database');
    $import_record = $database->select('csv_importer_history', 'h')
      ->fields('h')
      ->condition('name', 'sample.csv')
      ->execute()
      ->fetchObject();

    $this->assertEquals(1, $import_record->status, 'Import status is set to 1 (reverted)');
    $this->assertEquals('node', $import_record->entity_type);
    $this->assertEquals('page', $import_record->entity_bundle);
    $this->assertEquals(3, $import_record->imported_count);
  }

  /**
   * Tests the CSV Importer translation functionality.
   */
  public function testTranslationImport() {
    $node = Node::create([
      'nid' => 1011,
      'type' => 'page',
      'title' => 'Original page 1',
      'field_text' => [
        ['value' => 'Original body value 1'],
        ['value' => 'Original body value 2'],
      ],
    ]);
    $node->enforceIsNew(TRUE);
    $node->save();

    $this->assertNotEmpty(
      $this->languageManager->getLanguage('fr'),
      'Failed to add the French language (fr).'
    );

    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = $this->moduleExtensionList->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample_translation.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    $this->assertSession()->waitForText('translations created', 30000);

    $this->entityTypeManager->getStorage('node')->resetCache([1011]);
    $original_node = Node::load(1011);
    $translated_node = $original_node->getTranslation('fr');

    $this->assertEquals('Titre français traduit', $translated_node->getTitle());
    $this->assertEquals('Valeur du texte 7', $translated_node->get('field_text')->get(0)->value);
    $this->assertEquals('Valeur du texte 8', $translated_node->get('field_text')->get(1)->value);
  }

}
