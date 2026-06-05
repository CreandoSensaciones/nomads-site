<?php

declare(strict_types=1);

namespace Drupal\paragraph_relevance\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps only paragraph revisions referenced by current node field values.
 */
#[ViewsFilter('paragraph_relevance_current_node_reference')]
final class CurrentNodeParagraphReference extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $no_operator = TRUE;

  /**
   * Constructs a CurrentNodeParagraphReference filter plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['published_parent_node'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['published_parent_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require published parent node'),
      '#description' => $this->t('When checked, paragraph rows only pass if the current node revision that references them is published.'),
      '#default_value' => !empty($this->options['published_parent_node']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $paragraph_fields = $this->getNodeParagraphReferenceFields();
    if ($paragraph_fields === []) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $base_alias = $this->ensureMyTable();
    $paragraph_id = "$base_alias.id";
    $paragraph_revision_id = "$base_alias.revision_id";
    $exists = [];

    foreach ($paragraph_fields as $field_name => $definition) {
      $table = $this->getFieldTableName('node', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $alias = $this->database->escapeTable('prcnr_' . $field_name);
      $target_id_column = $this->database->escapeField($this->getFieldColumnName($definition, 'target_id'));
      $target_revision_column = $this->database->escapeField($this->getFieldColumnName($definition, 'target_revision_id'));

      $conditions = [
        "$alias.deleted = 0",
        "$alias.$target_id_column = $paragraph_id",
        "$alias.$target_revision_column = $paragraph_revision_id",
      ];

      if (!empty($this->options['published_parent_node'])) {
        $conditions[] = "EXISTS (SELECT 1 FROM {node_field_data} prcnr_node WHERE prcnr_node.nid = $alias.entity_id AND prcnr_node.status = 1)";
      }

      $exists[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE " . implode(' AND ', $conditions) . ')';
    }

    $this->query->addWhereExpression(
      $this->options['group'],
      $exists === [] ? '1 = 0' : '(' . implode(' OR ', $exists) . ')',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return !empty($this->options['published_parent_node'])
      ? (string) $this->t('current published node paragraph references')
      : (string) $this->t('current node paragraph references');
  }

  /**
   * Gets node fields that directly reference paragraph revisions.
   *
   * @return array<string, \Drupal\Core\Field\FieldStorageDefinitionInterface>
   *   Field storage definitions keyed by field name.
   */
  private function getNodeParagraphReferenceFields(): array {
    $fields = [];
    foreach ($this->entityFieldManager->getFieldStorageDefinitions('node') as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if ($definition->getSetting('target_type') !== 'paragraph') {
        continue;
      }
      $fields[$field_name] = $definition;
    }

    return $fields;
  }

  /**
   * Gets the SQL table name for a field.
   */
  private function getFieldTableName(string $entity_type_id, string $field_name): ?string {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlEntityStorageInterface) {
      return NULL;
    }

    return $storage->getTableMapping()->getFieldTableName($field_name);
  }

  /**
   * Gets the SQL column name for a field property.
   */
  private function getFieldColumnName(FieldStorageDefinitionInterface $definition, string $property): string {
    return $this->entityTypeManager
      ->getStorage($definition->getTargetEntityTypeId())
      ->getTableMapping()
      ->getFieldColumnName($definition, $property);
  }

}
