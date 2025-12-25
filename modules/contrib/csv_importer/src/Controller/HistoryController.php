<?php

namespace Drupal\csv_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for CSV import history management.
 */
class HistoryController extends ControllerBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * HistoryController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Displays the import history page with list of imported files.
   *
   * @return array
   *   A render array for the imports page.
   */
  public function history(): array {
    $header = [
      'name' => $this->t('Filename'),
      'entity_type' => $this->t('Entity type'),
      'entity_bundle' => $this->t('Bundle'),
      'imported_count' => $this->t('Imported count'),
      'import_date' => $this->t('Date imported'),
      'status' => $this->t('Status'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];

    $query = $this->database->select('csv_importer_history', 'cih')
      ->fields('cih')
      ->orderBy('import_date', 'DESC');

    $imports = $query->execute()->fetchAll();

    foreach ($imports as $import) {
      $status_int = (int) $import->status;
      $status = $status_int === 0 ? $this->t('Active') : $this->t('Rolled back');
      $status_class = $status_int === 0 ? 'status-active' : 'status-reverted';

      $operations = [];

      if ($status_int === 0) {
        $operations['revert'] = [
          'title' => $this->t('Revert'),
          'url' => Url::fromRoute('csv_importer.revert', ['import_id' => $import->id]),
          'attributes' => [
            'class' => ['button', 'button--small', 'button--danger'],
          ],
        ];
      }

      $rows[] = [
        'name' => $import->name,
        'entity_type' => $import->entity_type,
        'entity_bundle' => $import->entity_bundle ?: $this->t('N/A'),
        'imported_count' => $import->imported_count,
        'import_date' => $this->dateFormatter->format($import->import_date, 'short'),
        'status' => [
          'data' => $status,
          'class' => [$status_class],
        ],
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build = [];

    $build['description'] = [
      '#markup' => $this->t('This page shows all CSV imports that have been performed. You can revert imports to delete all entities that were created during the import.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $build['imports_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No CSV imports found.'),
      '#attached' => [
        'library' => ['csv_importer/admin'],
      ],
    ];

    return $build;
  }

  /**
   * Revert an import by deleting all associated entities.
   *
   * @param int $import_id
   *   The import ID to revert.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function revert(int $import_id): RedirectResponse {
    $import = $this->database->select('csv_importer_history', 'cih')
      ->fields('cih')
      ->condition('id', $import_id)
      ->condition('status', 0)
      ->execute()
      ->fetchObject();

    if (!$import) {
      $this->messenger()->addError($this->t('Import not found or already reverted.'));
      return $this->redirect('csv_importer.history');
    }

    $entity_ids = unserialize($import->entity_ids, ['allowed_classes' => FALSE]);
    $entity_ids = is_array($entity_ids) ? $entity_ids : [];
    $deleted_count = 0;

    if (!empty($entity_ids)) {
      try {
        $storage = $this->entityTypeManager->getStorage($import->entity_type);
        $entities = $storage->loadMultiple($entity_ids);

        foreach ($entities as $entity) {
          try {
            $entity->delete();
            $deleted_count++;
          }
          catch (\Exception $e) {
            $this->getLogger('csv_importer')->error('Failed to delete entity @id: @message', [
              '@id' => $entity->id(),
              '@message' => $e->getMessage(),
            ]);
          }
        }

        $this->database->update('csv_importer_history')
          ->fields(['status' => 1])
          ->condition('id', $import_id)
          ->execute();

        $this->messenger()->addStatus($this->t('Successfully reverted import. Deleted @count entities.', ['@count' => $deleted_count]));
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error during revert: @message', ['@message' => $e->getMessage()]));
        $this->getLogger('csv_importer')->error('Revert failed for import @id: @message', [
          '@id' => $import_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }
    else {
      $this->messenger()->addWarning($this->t('No entities to revert for this import.'));
    }

    return $this->redirect('csv_importer.history');
  }

}
