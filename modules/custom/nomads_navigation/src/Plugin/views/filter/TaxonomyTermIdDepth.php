<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nomads_navigation\FilterQueryNormalizer;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\CastSqlInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters nodes or paragraphs by taxonomy term IDs, with hierarchy depth.
 */
#[ViewsFilter('nomads_navigation_taxonomy_term_id_depth')]
final class TaxonomyTermIdDepth extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum paragraph parent depth to check.
   */
  private const MAX_PARAGRAPH_DEPTH = 8;

  /**
   * Constructs a TaxonomyTermIdDepth filter plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly VocabularyStorageInterface $vocabularyStorage,
    private readonly TermStorageInterface $termStorage,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly CastSqlInterface $castSql,
    private readonly FilterQueryNormalizer $filterQueryNormalizer,
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
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('views.cast_sql'),
      $container->get('nomads_navigation.filter_query_normalizer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['operator']['default'] = 'or';
    $options['value'] = ['default' => ''];
    $options['vocabularies'] = ['default' => []];
    $options['depth'] = ['default' => 0];
    $options['multiple_value_operator'] = ['default' => 'or'];
    $options['selected_paragraph_bundles'] = ['default' => []];
    $options['paragraph_bundle_selection_initialized'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions($which = 'title') {
    return [
      'or' => $this->t('Is one of'),
      'and' => $this->t('Is all of'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtraOptions(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildExtraOptionsForm($form, $form_state);

    $vocabulary_options = [];
    foreach ($this->vocabularyStorage->loadMultiple() as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['vocabularies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#description' => $this->t('Only IDs from the selected vocabularies are accepted. Leave all unchecked to allow all vocabularies.'),
      '#options' => $vocabulary_options,
      '#default_value' => $this->normalizeStringList((array) ($this->options['vocabularies'] ?? [])),
    ];

    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => (int) ($this->options['depth'] ?? 0),
      '#description' => $this->t('Positive depth includes children of the entered term IDs. Negative depth includes parents. Zero matches only the entered IDs.'),
    ];

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
      '#access' => $paragraph_options !== [] && !$this->isParagraphViewContext(),
    ];
    $form['paragraph_bundle_selection_initialized'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxonomy term IDs'),
      '#description' => $this->t('Enter taxonomy term IDs separated by "~". Example: 1114~593~619.'),
      '#default_value' => is_array($this->value) ? implode('~', $this->value) : (string) $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    if (
      !empty($this->options['expose']['use_operator'])
      && !empty($this->options['expose']['operator_id'])
      && isset($input[$this->options['expose']['operator_id']])
    ) {
      $operator = (string) $input[$this->options['expose']['operator_id']];
      if (isset($this->operatorOptions()[$operator])) {
        $this->operator = $operator;
      }
    }

    $identifier = $this->options['expose']['identifier'] ?? '';
    if ($identifier === '' || !isset($input[$identifier])) {
      return FALSE;
    }

    $ids = $this->parseTermIds($input[$identifier], (string) $identifier);
    if ($ids === [] && empty($this->options['expose']['required'])) {
      return FALSE;
    }

    $this->value = $ids;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $input_ids = $this->parseTermIds($this->value);
    if ($input_ids === []) {
      return;
    }

    $expanded_groups = $this->expandTermGroups($input_ids);
    if ($expanded_groups === []) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $base_alias = $this->ensureMyTable();
    $entity_id = $this->getBaseEntityIdExpression($base_alias);
    if ($entity_id === NULL) {
      $this->query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }

    $arguments = [];
    $group_expressions = [];

    foreach ($expanded_groups as $term_ids) {
      $placeholder = $this->placeholder() . '[]';
      $arguments[$placeholder] = $term_ids;
      $exists = $this->buildTermMatchExistsExpressions($entity_id, $placeholder);
      if ($exists === []) {
        $group_expressions[] = '1 = 0';
        continue;
      }
      $group_expressions[] = '(' . implode(' OR ', $exists) . ')';
    }

    $operator = $this->getMultipleValueOperator() === 'and' ? ' AND ' : ' OR ';
    $this->query->addWhereExpression(
      $this->options['group'],
      '(' . implode($operator, $group_expressions) . ')',
      $arguments,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    $operator = $this->getMultipleValueOperator() === 'and' ? 'AND' : 'OR';
    $depth = (int) ($this->options['depth'] ?? 0);
    return (string) $this->t('IDs separated by ~, @operator, depth @depth', [
      '@operator' => $operator,
      '@depth' => $depth,
    ]);
  }

  /**
   * Builds EXISTS expressions for all supported term reference locations.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildTermMatchExistsExpressions(string $entity_id, string $placeholder): array {
    if ($this->isParagraphViewContext()) {
      return $this->buildDirectParagraphTermExistsExpressions($entity_id, $placeholder);
    }

    return array_merge(
      $this->buildNodeTermExistsExpressions($entity_id, $placeholder),
      $this->buildParagraphTermExistsExpressions($entity_id, $placeholder, $this->getSelectedParagraphBundles()),
    );
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

      $alias = $this->database->escapeTable('nntid_node_' . $field_name);
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

      $field_alias = $this->database->escapeTable('nntid_para_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      foreach ($this->buildParagraphOwnerExpressions($field_alias, $node_id, $selected_bundles) as $owner_expression) {
        $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $field_alias WHERE $field_alias.deleted = 0 AND $field_alias.$field IN ($placeholder) AND $owner_expression)";
      }
    }

    return $expressions;
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on the current paragraph.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildDirectParagraphTermExistsExpressions(string $paragraph_id, string $placeholder): array {
    $expressions = [];
    foreach ($this->getTaxonomyReferenceFields('paragraph') as $field_name => $definition) {
      $table = $this->getFieldTableName('paragraph', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $alias = $this->database->escapeTable('nntid_direct_para_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $paragraph_id AND $alias.deleted = 0 AND $alias.$field IN ($placeholder))";
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
        $paragraph_alias = 'nntid_owner_' . $depth . '_' . $level;
        if ($level === 0) {
          $joins[] = "FROM {paragraphs_item_field_data} $paragraph_alias";
          $where[] = "$paragraph_alias.id = $previous_id";
          $where[] = "$paragraph_alias.type IN ($bundle_list)";
        }
        else {
          $previous_alias = 'nntid_owner_' . $depth . '_' . ($level - 1);
          $joins[] = "INNER JOIN {paragraphs_item_field_data} $paragraph_alias ON $paragraph_alias.id = $previous_id AND $previous_alias.parent_type = 'paragraph'";
        }
        $previous_id = $this->castSql->getFieldAsInt("$paragraph_alias.parent_id");
      }

      $owner_alias = 'nntid_owner_' . $depth . '_' . $depth;
      $where[] = "$owner_alias.parent_type = 'node'";
      $where[] = $this->castSql->getFieldAsInt("$owner_alias.parent_id") . " = $node_id";
      $expressions[] = 'EXISTS (SELECT 1 ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where) . ')';
    }

    return $expressions;
  }

  /**
   * Expands every input term ID into the IDs matched for that input.
   *
   * @param int[] $input_ids
   *   Input term IDs.
   *
   * @return array<int[]>
   *   One expanded ID group per input term.
   */
  private function expandTermGroups(array $input_ids): array {
    $groups = [];
    $terms = $this->termStorage->loadMultiple($input_ids);
    $allowed_vocabularies = $this->getSelectedVocabularies();

    foreach ($input_ids as $tid) {
      $term = $terms[$tid] ?? NULL;
      if (!$term instanceof TermInterface || !$this->termIsAllowed($term, $allowed_vocabularies)) {
        continue;
      }

      $ids = [$tid];
      $depth = (int) ($this->options['depth'] ?? 0);
      if ($depth > 0) {
        foreach ($this->termStorage->loadTree($term->bundle(), $tid, $depth, TRUE) as $child) {
          if ($child instanceof TermInterface && $this->termIsAllowed($child, $allowed_vocabularies)) {
            $ids[] = (int) $child->id();
          }
        }
      }
      elseif ($depth < 0) {
        $ids = array_merge($ids, $this->loadParentIds($tid, abs($depth), $allowed_vocabularies));
      }

      $groups[] = $this->normalizeTermIds($ids);
    }

    return array_values(array_filter($groups));
  }

  /**
   * Loads parent term IDs up to a maximum depth.
   *
   * @param string[] $allowed_vocabularies
   *   Allowed vocabulary IDs.
   *
   * @return int[]
   *   Parent term IDs.
   */
  private function loadParentIds(int $tid, int $depth, array $allowed_vocabularies): array {
    $ids = [];
    $frontier = [$tid];
    for ($level = 0; $level < $depth && $frontier !== []; $level++) {
      $next_frontier = [];
      foreach ($frontier as $frontier_tid) {
        foreach ($this->termStorage->loadParents($frontier_tid) as $parent) {
          if (!$parent instanceof TermInterface || !$this->termIsAllowed($parent, $allowed_vocabularies)) {
            continue;
          }
          $parent_id = (int) $parent->id();
          $ids[] = $parent_id;
          $next_frontier[] = $parent_id;
        }
      }
      $frontier = array_values(array_unique($next_frontier));
    }

    return $this->normalizeTermIds($ids);
  }

  /**
   * Parses IDs from an exposed or configured value.
   *
   * @param mixed $value
   *   Raw value.
   *
   * @return int[]
   *   Positive unique term IDs.
   */
  private function parseTermIds(mixed $value, ?string $query_identifier = NULL): array {
    $limit = match ($query_identifier) {
      'geo' => 1,
      'tags' => 6,
      't' => 12,
      default => NULL,
    };
    if ($limit !== NULL) {
      return $this->filterQueryNormalizer->normalizeTermIds($value, $limit);
    }

    if (is_array($value)) {
      $value = implode('~', $value);
    }
    if (!is_string($value) && !is_numeric($value)) {
      return [];
    }

    $ids = [];
    foreach (preg_split('/[~,]/', (string) $value) ?: [] as $part) {
      $part = trim($part);
      if ($part !== '' && ctype_digit($part)) {
        $ids[] = (int) $part;
      }
    }

    return $this->normalizeTermIds($ids);
  }

  /**
   * Gets selected vocabulary IDs.
   *
   * @return string[]
   *   Selected vocabulary IDs, or an empty array for all vocabularies.
   */
  private function getSelectedVocabularies(): array {
    return $this->normalizeStringList((array) ($this->options['vocabularies'] ?? []));
  }

  /**
   * Gets the configured multi-value operator.
   */
  private function getMultipleValueOperator(): string {
    $operator = is_string($this->operator) ? $this->operator : '';
    if (isset($this->operatorOptions()[$operator])) {
      return $operator;
    }

    return ($this->options['multiple_value_operator'] ?? 'or') === 'and' ? 'and' : 'or';
  }

  /**
   * Checks whether a term belongs to the selected vocabularies.
   *
   * @param string[] $allowed_vocabularies
   *   Allowed vocabulary IDs.
   */
  private function termIsAllowed(TermInterface $term, array $allowed_vocabularies): bool {
    return $allowed_vocabularies === [] || in_array($term->bundle(), $allowed_vocabularies, TRUE);
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

    if ($bundles === [] || !$this->entityTypeManager->hasDefinition('paragraphs_type')) {
      return $bundles;
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
      if (is_string($value)) {
        $candidate = $value;
      }
      elseif ($value && is_string($key)) {
        $candidate = $key;
      }
      else {
        continue;
      }

      if ($candidate !== '' && $candidate !== '0') {
        $normalized[] = $candidate;
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

  /**
   * Gets the base entity ID SQL expression for the current Views table.
   */
  private function getBaseEntityIdExpression(string $base_alias): ?string {
    return $this->isParagraphViewContext() ? "$base_alias.id" : "$base_alias.nid";
  }

  /**
   * Determines whether this filter is attached to a paragraph view/table.
   */
  private function isParagraphViewContext(): bool {
    return in_array($this->table, ['paragraphs_item', 'paragraphs_item_field_data'], TRUE);
  }

}
