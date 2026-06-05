<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\sort;

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
use Drupal\views\Attribute\ViewsSort;
use Drupal\views\Plugin\views\query\CastSqlInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sorts matching taxonomy term IDs before non-matching rows.
 */
#[ViewsSort('nomads_navigation_taxonomy_term_id_depth_match')]
final class TaxonomyTermIdDepthMatch extends SortPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum paragraph parent depth to check.
   */
  private const MAX_PARAGRAPH_DEPTH = 8;

  /**
   * Constructs a TaxonomyTermIdDepthMatch sort plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RequestStack $requestStack,
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
      $container->get('request_stack'),
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
    $options['order']['default'] = 'ASC';
    $options['term_ids'] = ['default' => ''];
    $options['query_identifier'] = ['default' => 'tags'];
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
  public function hasExtraOptions(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['query_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query identifier'),
      '#description' => $this->t('Read term IDs from this query argument first. Use the same identifier as the matching exposed filter or navigation block, for example tags or t.'),
      '#default_value' => (string) ($this->options['query_identifier'] ?? 'tags'),
      '#size' => 20,
    ];

    $form['term_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fallback taxonomy term IDs'),
      '#description' => $this->t('Used when the query argument is empty. Enter taxonomy term IDs separated by "~".'),
      '#default_value' => (string) ($this->options['term_ids'] ?? ''),
      '#size' => 40,
    ];

    $vocabulary_options = [];
    foreach ($this->vocabularyStorage->loadMultiple() as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['vocabularies'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#description' => $this->t('Only IDs from the selected vocabularies are used. Leave all unchecked to allow all vocabularies.'),
      '#options' => $vocabulary_options,
      '#default_value' => $this->normalizeStringList((array) ($this->options['vocabularies'] ?? [])),
    ];

    $form['depth'] = [
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => (int) ($this->options['depth'] ?? 0),
      '#description' => $this->t('Positive depth includes children of the entered term IDs. Negative depth includes parents. Zero matches only the entered IDs.'),
    ];

    $form['multiple_value_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Multiple value handling'),
      '#options' => [
        'or' => $this->t('OR: prioritize rows matching any entered ID'),
        'and' => $this->t('AND: prioritize rows matching every entered ID'),
      ],
      '#default_value' => in_array($this->options['multiple_value_operator'] ?? 'or', ['and', 'or'], TRUE) ? $this->options['multiple_value_operator'] : 'or',
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
      '#access' => $paragraph_options !== [],
    ];
    $form['paragraph_bundle_selection_initialized'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function sortOptions(): array {
    return [
      'ASC' => $this->t('Matching first'),
      'DESC' => $this->t('Matching last'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $input_ids = $this->getInputTermIds();
    if ($input_ids === []) {
      return;
    }

    $expanded_groups = $this->expandTermGroups($input_ids);
    if ($expanded_groups === []) {
      return;
    }

    $node_alias = $this->ensureMyTable();
    $node_id = "$node_alias.nid";
    $group_expressions = [];

    foreach ($expanded_groups as $term_ids) {
      $in_list = implode(', ', $term_ids);
      $exists = $this->buildTermMatchExistsExpressions($node_id, $in_list);
      if ($exists === []) {
        $group_expressions[] = '0 = 1';
        continue;
      }
      $group_expressions[] = '(' . implode(' OR ', $exists) . ')';
    }

    $operator = ($this->options['multiple_value_operator'] ?? 'or') === 'and' ? ' AND ' : ' OR ';
    $match_expression = '(' . implode($operator, $group_expressions) . ')';
    $formula = 'CASE WHEN ' . $match_expression . ' THEN 0 ELSE 1 END';

    $this->query->addOrderBy(NULL, $formula, $this->options['order'], 'nomads_term_depth_match');
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    $operator = ($this->options['multiple_value_operator'] ?? 'or') === 'and' ? 'AND' : 'OR';
    $depth = (int) ($this->options['depth'] ?? 0);
    $identifier = (string) ($this->options['query_identifier'] ?? 'tags');
    return (string) $this->t('@identifier IDs, @operator, depth @depth', [
      '@identifier' => $identifier,
      '@operator' => $operator,
      '@depth' => $depth,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    $identifier = (string) ($this->options['query_identifier'] ?? '');
    if ($identifier !== '') {
      $contexts[] = 'url.query_args:' . $identifier;
    }

    return array_values(array_unique($contexts));
  }

  /**
   * Gets input term IDs from the query argument or fallback setting.
   *
   * @return int[]
   *   Input term IDs.
   */
  private function getInputTermIds(): array {
    $identifier = (string) ($this->options['query_identifier'] ?? '');
    if ($identifier !== '') {
      $query_value = $this->requestStack->getCurrentRequest()?->query->all()[$identifier] ?? NULL;
      $ids = $this->parseTermIds($query_value, $identifier);
      if ($ids !== []) {
        return $ids;
      }
    }

    return $this->parseTermIds($this->options['term_ids'] ?? '');
  }

  /**
   * Builds EXISTS expressions for all supported term reference locations.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildTermMatchExistsExpressions(string $node_id, string $in_list): array {
    return array_merge(
      $this->buildNodeTermExistsExpressions($node_id, $in_list),
      $this->buildParagraphTermExistsExpressions($node_id, $in_list, $this->getSelectedParagraphBundles()),
    );
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on nodes.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildNodeTermExistsExpressions(string $node_id, string $in_list): array {
    $expressions = [];
    foreach ($this->getTaxonomyReferenceFields('node') as $field_name => $definition) {
      $table = $this->getFieldTableName('node', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $alias = $this->database->escapeTable('nntids_node_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $node_id AND $alias.deleted = 0 AND $alias.$field IN ($in_list))";
    }

    return $expressions;
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on owned paragraphs.
   *
   * @return string[]
   *   SQL EXISTS expressions.
   */
  private function buildParagraphTermExistsExpressions(string $node_id, string $in_list, array $selected_bundles): array {
    if ($selected_bundles === []) {
      return [];
    }

    $expressions = [];
    foreach ($this->getTaxonomyReferenceFields('paragraph') as $field_name => $definition) {
      $table = $this->getFieldTableName('paragraph', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $field_alias = $this->database->escapeTable('nntids_para_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      foreach ($this->buildParagraphOwnerExpressions($field_alias, $node_id, $selected_bundles) as $owner_expression) {
        $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $field_alias WHERE $field_alias.deleted = 0 AND $field_alias.$field IN ($in_list) AND $owner_expression)";
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
        $paragraph_alias = 'nntids_owner_' . $depth . '_' . $level;
        if ($level === 0) {
          $joins[] = "FROM {paragraphs_item_field_data} $paragraph_alias";
          $where[] = "$paragraph_alias.id = $previous_id";
          $where[] = "$paragraph_alias.type IN ($bundle_list)";
        }
        else {
          $previous_alias = 'nntids_owner_' . $depth . '_' . ($level - 1);
          $joins[] = "INNER JOIN {paragraphs_item_field_data} $paragraph_alias ON $paragraph_alias.id = $previous_id AND $previous_alias.parent_type = 'paragraph'";
        }
        $previous_id = $this->castSql->getFieldAsInt("$paragraph_alias.parent_id");
      }

      $owner_alias = 'nntids_owner_' . $depth . '_' . $depth;
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
   * Parses IDs from a query or configured value.
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
   * Gets selected paragraph bundles for this sort instance.
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

}
