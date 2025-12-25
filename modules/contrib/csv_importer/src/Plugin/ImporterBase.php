<?php

namespace Drupal\csv_importer\Plugin;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for ImporterBase plugins.
 *
 * @see \Drupal\csv_importer\Annotation\Importer
 * @see \Drupal\csv_importer\Plugin\ImporterManager
 * @see \Drupal\csv_importer\Plugin\ImporterInterface
 * @see plugin_api
 */
abstract class ImporterBase extends PluginBase implements ImporterInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs ImporterBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config, FileRepositoryInterface $file_repository, ModuleHandlerInterface $module_handler, LoggerChannelFactoryInterface $logger_factory, LanguageManagerInterface $language_manager, Connection $database, TimeInterface $time, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config;
    $this->fileRepository = $file_repository;
    $this->moduleHandler = $module_handler;
    $this->loggerFactory = $logger_factory;
    $this->languageManager = $language_manager;
    $this->database = $database;
    $this->time = $time;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('file.repository'),
      $container->get('module_handler'),
      $container->get('logger.factory'),
      $container->get('language_manager'),
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function data() {
    $csv = $this->configuration['csv'];
    $return = [];

    if ($csv && is_array($csv)) {
      $csv_fields = $csv[0];
      unset($csv[0]);
      foreach ($csv as $index => $data) {
        foreach ($data as $key => $content) {
          if (empty($content)) {
            continue;
          }

          if (isset($csv_fields[$key])) {
            $content = Unicode::convertToUtf8($content, mb_detect_encoding($content));
            $fields = explode('|', $csv_fields[$key]);

            if (preg_match(static::REGEX_MULTIPLE, $content, $matches)) {
              if (isset($matches[2])) {
                $content = explode('+', $matches[2]);
              }
            }

            $field = $fields[0];
            if (count($fields) > 1) {
              foreach ($fields as $key => $in) {
                $return['content'][$index][$field][$in] = $content;
              }
            }
            elseif (isset($return['content'][$index][$field])) {
              $prev = $return['content'][$index][$field];
              $return['content'][$index][$field] = [];

              if (is_array($prev)) {
                $prev[] = $content;
                $return['content'][$index][$field] = $prev;
              }
              else {
                $return['content'][$index][$field][] = $prev;
                $return['content'][$index][$field][] = $content;
              }
            }
            else {
              $return['content'][$index][current($fields)] = $content;
            }
          }
        }

        if (isset($return[$index])) {
          $return['content'][$index] = array_intersect_key($return[$index], array_flip($this->configuration['fields']));
        }
      }
    }

    $this->moduleHandler->invokeAll('csv_importer_pre_import', [&$return]);

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function add($contents, array &$context) {
    if (!$contents) {
      return NULL;
    }

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($contents);
    }

    $context['sandbox']['progress']++;
    $context['message'] = $this->t('Import entity %index out of %max', [
      '%index' => $context['sandbox']['progress'],
      '%max' => $context['sandbox']['max'],
    ]);

    $entity_type = $this->configuration['entity_type'];
    $entity_type_bundle = $this->configuration['entity_type_bundle'];
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);

    $content = $contents[$context['sandbox']['progress']];

    if ($entity_definition->hasKey('bundle') && $entity_type_bundle) {
      $content[$entity_definition->getKey('bundle')] = $this->configuration['entity_type_bundle'];
    }

    foreach ($content as $key => $item) {
      $content[$key] = $this->attach($item);
    }

    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $entity_storage  */
    $entity_storage = $this->entityTypeManager->getStorage($this->configuration['entity_type']);

    try {
      $entity = NULL;

      if (!empty($content[$entity_definition->getKey('id')])) {
        $entity = $entity_storage->load($content[$entity_definition->getKey('id')]);
      }

      $languages = $this->languageManager->getLanguages();
      $langcode_default = $this->languageManager->getDefaultLanguage()->getId();
      $langcode = $this->languageManager->isMultilingual() && isset($languages[$content['langcode']]) ? $content['langcode'] : $langcode_default;

      if ($entity) {
        if ($entity->hasTranslation($langcode)) {
          $translation = $entity->getTranslation($langcode);
        }
        else {
          $translation = $entity->addTranslation($langcode);
        }

        foreach ($content as $field => $value) {
          if ($field !== 'langcode') {
            $translation->set($field, $value);
          }
        }

        if ($translation->save()) {
          $id = $entity->id();
          $context['results']['updated'][] = $id;

          if ($langcode_default !== $langcode) {
            $context['results']['translations'][] = $id;
          }
        }
      }
      else {
        $entity = $entity_storage->create($content);
        if ($entity->save()) {
          $id = $entity->id();
          $context['results']['added'][] = $id;

          if ($langcode_default !== $langcode) {
            $context['results']['translations'][] = $id;
          }
        }
      }
    }
    catch (\Throwable $exception) {
      $this->messenger->addError($this->t('The import process encountered errors.'));
      $this->loggerFactory->get('csv_importer')->error($exception->getMessage());
    }

    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    return $context;
  }

  /**
   * Attach file(s) from file path(s).
   *
   * @param mixed $item
   *   A file path string or an array of file paths.
   *
   * @return mixed
   *   File ID for single file, array of file references for multiple files,
   *   or the original item if not a file path.
   */
  protected function attach($item) {
    $default_scheme = $this->config->get('system.file')->get('default_scheme');
    if (is_string($item) && file_exists($item)) {
      $created = $this->fileRepository->writeData(file_get_contents($item), $default_scheme . '://' . basename($item), FileExists::Replace);
      return $created->id();
    }

    if (is_array($item)) {
      $ids = [];
      foreach ($item as $path) {
        if (is_string($path) && file_exists($path)) {
          $created = $this->fileRepository->writeData(file_get_contents($path), $default_scheme . '://' . basename($path), FileExists::Replace);
          $ids[] = ['target_id' => $created->id()];
        }
      }
      return !empty($ids) ? $ids : $item;
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function finished($success, array $results, array $operations) {
    if ($success) {
      $added_count = isset($results['added']) ? count($results['added']) : 0;
      $updated_count = isset($results['updated']) ? count($results['updated']) : 0;
      $translation_count = isset($results['translations']) ? count($results['translations']) : 0;

      $this->history($results);

      $this->messenger->addMessage(
        $this->t('@added_count new content added, @updated_count updated and translations created for @translations_count content.', [
          '@added_count' => $added_count,
          '@updated_count' => $updated_count,
          '@translations_count' => $translation_count,
        ]),
      );
    }
    else {
      $this->messenger->addError($this->t('The import process encountered errors.'));
    }
  }

  /**
   * Save the import history.
   *
   * @param array $results
   *   The import results.
   */
  protected function history(array $results): void {
    $added_ids = $results['added'] ?? [];
    $updated_ids = $results['updated'] ?? [];
    $ids = array_merge($added_ids, $updated_ids);

    if (empty($ids)) {
      return;
    }

    $csv_entity = $this->configuration['csv_entity'] ?? NULL;
    $name = '';
    $path = '';

    if ($csv_entity) {
      $name = $csv_entity->getFilename();
      $path = $csv_entity->getFileUri();
    }

    $this->database->insert('csv_importer_history')
      ->fields([
        'name' => $name,
        'path' => $path,
        'entity_type' => $this->configuration['entity_type'],
        'entity_bundle' => $this->configuration['entity_type_bundle'] ?? '',
        'imported_count' => count($ids),
        'entity_ids' => serialize($ids),
        'import_date' => $this->time->getRequestTime(),
        'status' => 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    if ($data = $this->data()) {
      $process['operations'][] = [
        [$this, 'add'],
        [$data['content']],
      ];

      $process['finished'] = [$this, 'finished'];
      batch_set($process);
    }
    else {
      $this->messenger->addError($this->t('The import process encountered errors. No data is available for processing. Please check the CSV file and ensure it is saved in UTF-8 format.'));
    }
  }

}
