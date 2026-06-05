<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\filter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\nomads_navigation\FilterQueryNormalizer;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters paragraph rows by month query values over date/date range fields.
 */
#[ViewsFilter('nomads_navigation_paragraph_date_period')]
final class ParagraphDatePeriod extends FilterPluginBase implements ContainerFactoryPluginInterface {

  private const DEFAULT_QUERY_IDENTIFIER = 'month';
  private const SIX_MONTHS_VALUES = ['6months', '6month'];

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName, Drupal.Commenting.VariableComment.Missing
  public $no_operator = TRUE;

  /**
   * Constructs a ParagraphDatePeriod filter plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RequestStack $requestStack,
    private readonly TimeInterface $time,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
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
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('nomads_navigation.filter_query_normalizer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['query_identifier'] = ['default' => self::DEFAULT_QUERY_IDENTIFIER];
    $options['pass_empty_dates'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['query_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query identifier'),
      '#description' => $this->t('Read the Date period navigation value from this query argument.'),
      '#default_value' => (string) ($this->options['query_identifier'] ?? self::DEFAULT_QUERY_IDENTIFIER),
      '#size' => 20,
    ];

    $form['pass_empty_dates'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let empty date fields pass'),
      '#description' => $this->t('When checked, paragraphs with no date or date range values pass the filter.'),
      '#default_value' => !empty($this->options['pass_empty_dates']),
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
    $period = $this->getSelectedPeriod();
    if ($period === NULL) {
      return;
    }

    $date_fields = $this->getParagraphDateFields();
    if ($date_fields === []) {
      $this->query->addWhereExpression($this->options['group'], !empty($this->options['pass_empty_dates']) ? '1 = 1' : '1 = 0');
      return;
    }

    $base_alias = $this->ensureMyTable();
    $paragraph_id = "$base_alias.id";
    $arguments = [];
    $match_expressions = [];
    $non_empty_expressions = [];

    foreach ($date_fields as $field_name => $definition) {
      $table = $this->getFieldTableName('paragraph', $field_name);
      if ($table === NULL || !$this->database->schema()->tableExists($table)) {
        continue;
      }

      $alias = $this->database->escapeTable('nnpdp_' . $field_name);
      $date_only = ($definition->getSetting('datetime_type') ?? NULL) === 'date';
      $value_column = $this->database->escapeField($this->getFieldColumnName($definition, 'value'));
      $end_value_column = $definition->getType() === 'daterange'
        ? $this->database->escapeField($this->getFieldColumnName($definition, 'end_value'))
        : NULL;

      $endpoint_checks = [$this->buildEndpointCheck($alias, $value_column, $period, $date_only, $arguments)];
      if ($period['type'] === 'month' && $end_value_column !== NULL) {
        $endpoint_checks[] = $this->buildEndpointCheck($alias, $end_value_column, $period, $date_only, $arguments);
      }

      $match_expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $paragraph_id AND $alias.deleted = 0 AND (" . implode(' OR ', $endpoint_checks) . '))';

      $non_empty_checks = ["$alias.$value_column IS NOT NULL", "$alias.$value_column <> ''"];
      if ($end_value_column !== NULL) {
        $non_empty_checks = [
          "(($alias.$value_column IS NOT NULL AND $alias.$value_column <> '') OR ($alias.$end_value_column IS NOT NULL AND $alias.$end_value_column <> ''))",
        ];
      }
      $non_empty_expressions[] = "EXISTS (SELECT 1 FROM {{$table}} $alias WHERE $alias.entity_id = $paragraph_id AND $alias.deleted = 0 AND " . implode(' AND ', $non_empty_checks) . ')';
    }

    if ($match_expressions === []) {
      $this->query->addWhereExpression($this->options['group'], !empty($this->options['pass_empty_dates']) ? '1 = 1' : '1 = 0');
      return;
    }

    $where = '(' . implode(' OR ', $match_expressions) . ')';
    if (!empty($this->options['pass_empty_dates']) && $non_empty_expressions !== []) {
      $where = '(' . $where . ' OR NOT (' . implode(' OR ', $non_empty_expressions) . '))';
    }

    $this->query->addWhereExpression($this->options['group'], $where, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('@identifier date period, empty dates @empty', [
      '@identifier' => (string) ($this->options['query_identifier'] ?? self::DEFAULT_QUERY_IDENTIFIER),
      '@empty' => !empty($this->options['pass_empty_dates']) ? 'pass' : 'do not pass',
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
   * Builds a SQL check for one date endpoint.
   *
   * @param array<string, mixed> $arguments
   *   Query arguments, modified by reference.
   */
  private function buildEndpointCheck(string $alias, string $column, array $period, bool $date_only, array &$arguments): string {
    if ($period['type'] === 'six_months') {
      $placeholder = $this->placeholder();
      $arguments[$placeholder] = $date_only ? $period['threshold_date'] : $period['threshold_datetime'];
      return "$alias.$column >= $placeholder";
    }

    $start_placeholder = $this->placeholder();
    $end_placeholder = $this->placeholder();
    $arguments[$start_placeholder] = $date_only ? $period['start_date'] : $period['start_datetime'];
    $arguments[$end_placeholder] = $date_only ? $period['end_date'] : $period['end_datetime'];
    return "($alias.$column >= $start_placeholder AND $alias.$column < $end_placeholder)";
  }

  /**
   * Reads and normalizes the selected date period from the request.
   *
   * @return array<string, string>|null
   *   Period metadata, or NULL when no valid value is selected.
   */
  private function getSelectedPeriod(): ?array {
    $identifier = (string) ($this->options['query_identifier'] ?? self::DEFAULT_QUERY_IDENTIFIER);
    if ($identifier === '') {
      return NULL;
    }

    $value = $this->requestStack->getCurrentRequest()?->query->get($identifier);
    if ($identifier === self::DEFAULT_QUERY_IDENTIFIER) {
      $value = $this->filterQueryNormalizer->normalizeMonth($value);
    }

    if (!is_string($value)) {
      return NULL;
    }

    if (in_array($value, self::SIX_MONTHS_VALUES, TRUE)) {
      $threshold = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
        ->setTimezone(new \DateTimeZone('UTC'))
        ->modify('+180 days')
        ->format('Y-m-d\TH:i:s');

      return [
        'type' => 'six_months',
        'threshold_date' => substr($threshold, 0, 10),
        'threshold_datetime' => $threshold,
      ];
    }

    if (!preg_match('/^(\d{4})-([1-9]|1[0-2])$/', $value, $matches)) {
      return NULL;
    }

    $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00 UTC', (int) $matches[1], (int) $matches[2]));
    $end = $start->modify('+1 month');

    return [
      'type' => 'month',
      'start_date' => $start->format('Y-m-d'),
      'end_date' => $end->format('Y-m-d'),
      'start_datetime' => $start->format('Y-m-d\TH:i:s'),
      'end_datetime' => $end->format('Y-m-d\TH:i:s'),
    ];
  }

  /**
   * Gets date and date range field storage definitions on paragraphs.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   Field storage definitions keyed by field name.
   */
  private function getParagraphDateFields(): array {
    if (!$this->entityTypeManager->hasDefinition('paragraph')) {
      return [];
    }

    $fields = [];
    foreach ($this->entityFieldManager->getFieldStorageDefinitions('paragraph') as $field_name => $definition) {
      if (in_array($definition->getType(), ['datetime', 'daterange'], TRUE)) {
        $fields[$field_name] = $definition;
      }
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
