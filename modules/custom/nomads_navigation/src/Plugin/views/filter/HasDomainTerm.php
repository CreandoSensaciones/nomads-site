<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\CastSqlInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters content by the current domain's configured navigation filter terms.
 */
#[ViewsFilter('nomads_navigation_has_domain_term')]
final class HasDomainTerm extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum paragraph parent depth to check.
   */
  private const MAX_PARAGRAPH_DEPTH = 8;

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $no_operator = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['selected_paragraph_bundles'] = ['default' => []];
    $options['paragraph_bundle_selection_initialized'] = ['default' => FALSE];
    $options['bypass_domain_assigned'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $paragraph_options = $this->getListingParagraphBundleOptions();
    $initialized = (bool) ($this->options['paragraph_bundle_selection_initialized'] ?? FALSE);
    $selected = $initialized
      ? $this->normalizeStringList((array) ($this->options['selected_paragraph_bundles'] ?? []))
      : $this->getDefaultParagraphBundles($paragraph_options);
    $selected = array_values(array_intersect($selected, array_keys($paragraph_options)));

    $form['selected_paragraph_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Paragraph types'),
      '#description' => $this->t('Only terms on the selected listing paragraph types are considered. Node fields are always checked.'),
      '#options' => $paragraph_options,
      '#default_value' => $selected,
      '#access' => $paragraph_options !== [],
    ];
    $form['paragraph_bundle_selection_initialized'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];
    $form['bypass_domain_assigned'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass listings asigned to the actual domain'),
      '#description' => $this->t('When checked, listings assigned to the active domain are shown even when they do not match the configured filter terms.'),
      '#default_value' => !empty($this->options['bypass_domain_assigned']),
    ];
  }

  /**
   * Constructs a HasDomainTerm filter plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly CastSqlInterface $castSql,
    private readonly DomainNegotiatorInterface $domainNegotiator,
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
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('views.cast_sql'),
      $container->get('domain.negotiator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('Current domain navigation filter terms');
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
    $filter_tids = $this->getConfiguredFilterTids();
    if ($filter_tids === []) {
      return;
    }

    $node_alias = $this->ensureMyTable();
    $node_id = "$node_alias.nid";

    $placeholder = $this->placeholder() . '[]';
    $arguments = [$placeholder => $filter_tids];
    $exists = array_merge(
      $this->buildNodeTermExistsExpressions($node_id, $placeholder),
      $this->buildParagraphTermExistsExpressions($node_id, $placeholder, $this->getSelectedParagraphBundles()),
    );
    if (!empty($this->options['bypass_domain_assigned'])) {
      $domain_placeholder = $this->placeholder();
      $domain_expression = $this->buildActiveDomainAssignmentExistsExpression($node_id, $domain_placeholder);
      if ($domain_expression !== NULL) {
        $exists[] = $domain_expression;
        $arguments[$domain_placeholder] = $this->domainNegotiator->getActiveId();
      }
    }

    if ($exists === []) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $this->query->addWhereExpression(
      $this->options['group'],
      '(' . implode(' OR ', $exists) . ')',
      $arguments,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_values(array_unique(array_merge(parent::getCacheContexts(), ['url.site'])));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_values(array_unique(array_merge(parent::getCacheTags(), ['config:nomads_navigation.settings'])));
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on nodes.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildNodeTermExistsExpressions(string $node_id, string $placeholder): array {
    $expressions = [];
    foreach ($this->getTaxonomyReferenceFields('node') as $field_name => $definition) {
      $table = $this->getFieldTableName('node', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $alias = $this->database->escapeTable('nnhdt_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $node_id AND $alias.deleted = 0 AND $alias.$field IN ($placeholder))";
    }

    return $expressions;
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on owned paragraphs.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildParagraphTermExistsExpressions(string $node_id, string $placeholder, array $selected_bundles): array {
    if ($selected_bundles === []) {
      return [];
    }

    $expressions = [];
    foreach ($this->getTaxonomyReferenceFields('paragraph') as $field_name => $definition) {
      $table = $this->getFieldTableName('paragraph', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $field_alias = $this->database->escapeTable('nphdt_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      foreach ($this->buildParagraphOwnerExpressions($field_alias, $node_id, $selected_bundles) as $owner_expression) {
        $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $field_alias WHERE $field_alias.deleted = 0 AND $field_alias.$field IN ($placeholder) AND $owner_expression)";
      }
    }

    return $expressions;
  }

  /**
   * Builds paragraph owner checks for direct and nested paragraph ownership.
   *
   * @return string[]
   *   SQL expressions that are true when the paragraph belongs to the node.
   */
  private function buildParagraphOwnerExpressions(string $field_alias, string $node_id, array $selected_bundles): array {
    $expressions = [];
    $bundle_list = implode(', ', array_map([$this->database, 'quote'], $selected_bundles));
    for ($depth = 0; $depth <= self::MAX_PARAGRAPH_DEPTH; $depth++) {
      $joins = [];
      $where = [];
      $previous_id = "$field_alias.entity_id";
      for ($level = 0; $level <= $depth; $level++) {
        $paragraph_alias = 'npowner_' . $depth . '_' . $level;
        if ($level === 0) {
          $joins[] = "FROM {paragraphs_item_field_data} $paragraph_alias";
          $where[] = "$paragraph_alias.id = $previous_id";
          $where[] = "$paragraph_alias.type IN ($bundle_list)";
        }
        else {
          $previous_alias = 'npowner_' . $depth . '_' . ($level - 1);
          $joins[] = "INNER JOIN {paragraphs_item_field_data} $paragraph_alias ON $paragraph_alias.id = $previous_id AND $previous_alias.parent_type = 'paragraph'";
        }
        $previous_id = $this->castSql->getFieldAsInt("$paragraph_alias.parent_id");
      }

      $owner_alias = 'npowner_' . $depth . '_' . $depth;
      $where[] = "$owner_alias.parent_type = 'node'";
      $where[] = $this->castSql->getFieldAsInt("$owner_alias.parent_id") . " = $node_id";
      $expressions[] = 'EXISTS (SELECT 1 ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where) . ')';
    }

    return $expressions;
  }

  /**
   * Builds an EXISTS expression for assignment to the active domain.
   */
  private function buildActiveDomainAssignmentExistsExpression(string $node_id, string $placeholder): ?string {
    $active_domain_id = $this->domainNegotiator->getActiveId();
    if ($active_domain_id === NULL || $active_domain_id === '') {
      return NULL;
    }

    $table = 'node__field_domain_access';
    if (!$this->database->schema()->tableExists($table)) {
      return NULL;
    }

    return "EXISTS (SELECT 1 FROM {{$table}} nndomain_access WHERE nndomain_access.entity_id = $node_id AND nndomain_access.deleted = 0 AND nndomain_access.field_domain_access_target_id = $placeholder)";
  }

  /**
   * Gets taxonomy term reference field storage definitions.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   Field storage definitions keyed by field name.
   */
  private function getTaxonomyReferenceFields(string $entity_type_id): array {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return [];
    }

    $fields = [];
    foreach ($this->entityFieldManager->getFieldStorageDefinitions($entity_type_id) as $field_name => $definition) {
      if (
        $definition->getType() === 'entity_reference'
        && $definition->getSetting('target_type') === 'taxonomy_term'
      ) {
        $fields[$field_name] = $definition;
      }
    }

    return $fields;
  }

  /**
   * Gets configured filter term IDs, including legacy single-term config.
   *
   * @return int[]
   *   Configured filter term IDs.
   */
  private function getConfiguredFilterTids(): array {
    $settings = $this->configFactory->get('nomads_navigation.settings');
    $filter_tids = $this->normalizeTermIds($settings->get('filter_tids') ?? []);
    if ($filter_tids !== []) {
      return $filter_tids;
    }

    return $this->normalizeTermIds([$settings->get('filter_tid')]);
  }

  /**
   * Normalizes term IDs.
   *
   * @param mixed[] $tids
   *   Raw term IDs.
   *
   * @return int[]
   *   Positive unique term IDs.
   */
  private function normalizeTermIds(array $tids): array {
    return array_values(array_unique(array_filter(array_map('intval', $tids), static fn (int $tid): bool => $tid > 0)));
  }

  /**
   * Gets selected paragraph bundles for this filter instance.
   *
   * @return string[]
   *   Selected paragraph bundle machine names.
   */
  private function getSelectedParagraphBundles(): array {
    $paragraph_options = $this->getListingParagraphBundleOptions();
    if ($paragraph_options === []) {
      return [];
    }

    if (empty($this->options['paragraph_bundle_selection_initialized'])) {
      return $this->getDefaultParagraphBundles($paragraph_options);
    }

    $selected = $this->normalizeStringList((array) ($this->options['selected_paragraph_bundles'] ?? []));
    return array_values(array_intersect($selected, array_keys($paragraph_options)));
  }

  /**
   * Gets paragraph bundle options used by the Listing content type.
   *
   * @return string[]
   *   Paragraph bundle labels keyed by machine name.
   */
  private function getListingParagraphBundleOptions(): array {
    $bundles = [];
    foreach ($this->entityFieldManager->getFieldDefinitions('node', 'listing') as $definition) {
      if (
        !in_array($definition->getType(), ['entity_reference', 'entity_reference_revisions'], TRUE)
        || $definition->getSetting('target_type') !== 'paragraph'
      ) {
        continue;
      }

      $handler_settings = (array) ($definition->getSetting('handler_settings') ?? []);
      foreach ((array) ($handler_settings['target_bundles'] ?? []) as $bundle => $enabled) {
        if ($enabled) {
          $bundles[(string) $bundle] = (string) $bundle;
        }
      }
      foreach ((array) ($handler_settings['target_bundles_drag_drop'] ?? []) as $bundle => $settings) {
        if (!empty($settings['enabled'])) {
          $bundles[(string) $bundle] = (string) $bundle;
        }
      }
    }

    if ($bundles === []) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    foreach ($storage->loadMultiple(array_keys($bundles)) as $bundle => $paragraph_type) {
      $bundles[$bundle] = $paragraph_type->label();
    }

    natcasesort($bundles);
    return $bundles;
  }

  /**
   * Gets the default paragraph bundle selection.
   *
   * @param string[] $paragraph_options
   *   Available paragraph bundle options.
   *
   * @return string[]
   *   Default selected bundle machine names.
   */
  private function getDefaultParagraphBundles(array $paragraph_options): array {
    return isset($paragraph_options['location']) ? ['location'] : [];
  }

  /**
   * Normalizes a string list from checkbox values.
   *
   * @param mixed[] $values
   *   Raw values.
   *
   * @return string[]
   *   Non-empty unique strings.
   */
  private function normalizeStringList(array $values): array {
    $normalized = [];
    foreach ($values as $key => $value) {
      $value = is_string($value) ? $value : (is_string($key) ? $key : '');
      if ($value !== '' && $value !== '0') {
        $normalized[] = $value;
      }
    }

    return array_values(array_unique($normalized));
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
   * Gets the SQL column name for a term reference target ID.
   */
  private function getTargetIdColumn(FieldStorageDefinitionInterface $definition): string {
    return $this->entityTypeManager
      ->getStorage($definition->getTargetEntityTypeId())
      ->getTableMapping()
      ->getFieldColumnName($definition, 'target_id');
  }

}
