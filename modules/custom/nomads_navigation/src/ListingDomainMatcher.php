<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain_config\DomainConfigOverrider;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\query\CastSqlInterface;

/**
 * Matches listing nodes against domain-specific navigation term filters.
 */
final class ListingDomainMatcher {

  /**
   * Maximum paragraph parent depth to check.
   */
  private const MAX_PARAGRAPH_DEPTH = 8;

  /**
   * Cache tags touched during the most recent match operation.
   *
   * @var string[]
   */
  private array $cacheTags = [];

  public function __construct(
    private readonly StorageInterface $configStorage,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly CastSqlInterface $castSql,
  ) {}

  /**
   * Gets domains whose configured listing term filter matches the listing.
   *
   * @param string[] $selected_bundles
   *   Paragraph bundle machine names to inspect.
   *
   * @return \Drupal\domain\DomainInterface[]
   *   Matching domains.
   */
  public function getMatchingDomains(NodeInterface $listing, array $selected_bundles, bool $bypass_domain_assigned = FALSE): array {
    $this->cacheTags = ['config:domain_list', 'config:nomads_navigation.settings'];

    if ($listing->bundle() !== 'listing' || $listing->id() === NULL) {
      return [];
    }

    /** @var \Drupal\domain\DomainStorageInterface $domain_storage */
    $domain_storage = $this->entityTypeManager->getStorage('domain');
    $domains = [];
    foreach ($domain_storage->loadMultipleSorted() as $domain) {
      if (!$domain instanceof DomainInterface || !$domain->status()) {
        continue;
      }

      $this->cacheTags = array_merge($this->cacheTags, $domain->getCacheTags());

      if ($bypass_domain_assigned && $this->isListingAssignedToDomain($listing, (string) $domain->id())) {
        $domains[] = $domain;
        continue;
      }

      if ($this->listingMatchesTerms($listing, $this->getDomainFilterTids($domain), $selected_bundles)) {
        $domains[] = $domain;
      }
    }

    return $domains;
  }

  /**
   * Gets cache tags touched during the most recent match operation.
   *
   * @return string[]
   *   Cache tags.
   */
  public function getCacheTags(): array {
    return array_values(array_unique($this->cacheTags));
  }

  /**
   * Gets paragraph bundle options used by the Listing content type.
   *
   * @return string[]
   *   Paragraph bundle labels keyed by machine name.
   */
  public function getListingParagraphBundleOptions(): array {
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
  public function getDefaultParagraphBundles(array $paragraph_options): array {
    return isset($paragraph_options['location']) ? ['location'] : [];
  }

  /**
   * Gets the display label for a domain.
   */
  public function getDomainSubdomainLabel(DomainInterface $domain): string {
    $host = $domain->getHostname();
    $parts = explode('.', $host);

    return count($parts) > 2 ? $parts[0] : '';
  }

  /**
   * Checks whether a listing matches the configured term IDs.
   *
   * @param int[] $filter_tids
   *   Domain-specific filter term IDs.
   * @param string[] $selected_bundles
   *   Paragraph bundle machine names to inspect.
   */
  private function listingMatchesTerms(NodeInterface $listing, array $filter_tids, array $selected_bundles): bool {
    if ($filter_tids === []) {
      return FALSE;
    }

    $node_id = (int) $listing->id();
    $arguments = [':filter_tids[]' => $filter_tids];
    $exists = array_merge(
      $this->buildNodeTermExistsExpressions(':node_id', ':filter_tids[]'),
      $this->buildParagraphTermExistsExpressions(':node_id', ':filter_tids[]', $selected_bundles),
    );

    if ($exists === []) {
      return FALSE;
    }

    $arguments[':node_id'] = $node_id;
    return (bool) $this->database
      ->query('SELECT 1 WHERE ' . implode(' OR ', $exists), $arguments)
      ->fetchField();
  }

  /**
   * Checks whether the listing is directly assigned to a domain.
   */
  private function isListingAssignedToDomain(NodeInterface $listing, string $domain_id): bool {
    $table = 'node__field_domain_access';
    if (!$this->database->schema()->tableExists($table)) {
      return FALSE;
    }

    return (bool) $this->database
      ->select($table, 'domain_access')
      ->condition('domain_access.entity_id', (int) $listing->id())
      ->condition('domain_access.deleted', 0)
      ->condition('domain_access.field_domain_access_target_id', $domain_id)
      ->range(0, 1)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Gets configured filter term IDs for a domain.
   *
   * @return int[]
   *   Positive unique term IDs.
   */
  private function getDomainFilterTids(DomainInterface $domain): array {
    foreach ($this->getDomainSettingsConfigNames($domain) as $config_name) {
      $this->cacheTags[] = 'config:' . $config_name;
      $settings = $this->configStorage->read($config_name);
      if (!is_array($settings)) {
        continue;
      }

      if (array_key_exists('filter_tids', $settings)) {
        $filter_tids = $this->normalizeTermIds((array) $settings['filter_tids']);
        if ($filter_tids !== [] || !array_key_exists('filter_tid', $settings)) {
          return $filter_tids;
        }
      }

      if (array_key_exists('filter_tid', $settings)) {
        return $this->normalizeTermIds([$settings['filter_tid']]);
      }
    }

    $settings = $this->configStorage->read('nomads_navigation.settings');
    if (!is_array($settings)) {
      return [];
    }

    $filter_tids = $this->normalizeTermIds((array) ($settings['filter_tids'] ?? []));
    if ($filter_tids !== []) {
      return $filter_tids;
    }

    return $this->normalizeTermIds([$settings['filter_tid'] ?? NULL]);
  }

  /**
   * Gets domain-specific settings config names in override order.
   *
   * @return string[]
   *   Config names.
   */
  private function getDomainSettingsConfigNames(DomainInterface $domain): array {
    $domain_id = (string) $domain->id();
    return [
      DomainConfigOverrider::getConfigNameByDomain('nomads_navigation.settings', $domain_id),
    ];
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

      $alias = $this->database->escapeTable('nnld_node_' . $field_name);
      $field = $this->database->escapeField($this->getTargetIdColumn($definition));
      $expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $node_id AND $alias.deleted = 0 AND $alias.$field IN ($placeholder))";
    }

    return $expressions;
  }

  /**
   * Builds EXISTS expressions for taxonomy term fields on owned paragraphs.
   *
   * @param string[] $selected_bundles
   *   Paragraph bundle machine names to inspect.
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

      $field_alias = $this->database->escapeTable('nnld_para_' . $field_name);
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
   * @param string[] $selected_bundles
   *   Paragraph bundle machine names to inspect.
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
        $paragraph_alias = 'nnld_owner_' . $depth . '_' . $level;
        if ($level === 0) {
          $joins[] = "FROM {paragraphs_item_field_data} $paragraph_alias";
          $where[] = "$paragraph_alias.id = $previous_id";
          $where[] = "$paragraph_alias.type IN ($bundle_list)";
        }
        else {
          $previous_alias = 'nnld_owner_' . $depth . '_' . ($level - 1);
          $joins[] = "INNER JOIN {paragraphs_item_field_data} $paragraph_alias ON $paragraph_alias.id = $previous_id AND $previous_alias.parent_type = 'paragraph'";
        }
        $previous_id = $this->castSql->getFieldAsInt("$paragraph_alias.parent_id");
      }

      $owner_alias = 'nnld_owner_' . $depth . '_' . $depth;
      $where[] = "$owner_alias.parent_type = 'node'";
      $where[] = $this->castSql->getFieldAsInt("$owner_alias.parent_id") . " = $node_id";
      $expressions[] = 'EXISTS (SELECT 1 ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where) . ')';
    }

    return $expressions;
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
