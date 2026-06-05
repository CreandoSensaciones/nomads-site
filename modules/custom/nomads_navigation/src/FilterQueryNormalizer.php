<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Normalizes listing filter query arguments used by Nomads navigation.
 */
final class FilterQueryNormalizer {

  public const ALLOWED_QUERY_ARGS = ['geo', 'month', 'tags', 't', 'sort'];
  public const CACHE_CONTEXTS = [
    'url.query_args:geo',
    'url.query_args:month',
    'url.query_args:tags',
    'url.query_args:t',
    'url.query_args:sort',
  ];

  private const SEPARATOR = '~';
  private const SIX_MONTHS_VALUE = '6months';

  public function __construct(
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Keeps only normalized listing filter query arguments.
   *
   * @param array<string, mixed> $query
   *   Raw query arguments.
   *
   * @return array<string, string>
   *   Normalized query arguments.
   */
  public function normalize(array $query): array {
    $normalized = [];

    $geo = $this->normalizeTermIds($query['geo'] ?? NULL, 1);
    if ($geo !== []) {
      $normalized['geo'] = (string) $geo[0];
    }

    $month = $this->normalizeMonth($query['month'] ?? NULL);
    if ($month !== NULL) {
      $normalized['month'] = $month;
    }

    $tags = $this->normalizeTermIds($query['tags'] ?? NULL, 6);
    if ($tags !== []) {
      $normalized['tags'] = implode(self::SEPARATOR, $tags);
    }

    $specific = $this->normalizeTermIds($query['t'] ?? NULL, 12);
    if ($specific !== []) {
      $normalized['t'] = implode(self::SEPARATOR, $specific);
    }

    $sort = $this->normalizeSort($query['sort'] ?? NULL);
    if ($sort !== NULL) {
      $normalized['sort'] = $sort;
    }

    return $normalized;
  }

  /**
   * Adds or replaces one normalized query argument.
   *
   * @param array<string, mixed> $query
   *   Existing query arguments.
   */
  public function withValue(array $query, string $key, int|string $value): array {
    $query = $this->normalize($query);
    $query[$key] = (string) $value;

    return $this->normalize($query);
  }

  /**
   * Removes one query argument after normalization.
   *
   * @param array<string, mixed> $query
   *   Existing query arguments.
   */
  public function withoutValue(array $query, string $key): array {
    $query = $this->normalize($query);
    unset($query[$key]);

    return $query;
  }

  /**
   * Gets normalized IDs for one term query argument.
   *
   * @return int[]
   *   Positive term IDs.
   */
  public function normalizeTermIds(mixed $value, int $limit): array {
    if ($value === NULL || $value === '' || $limit <= 0) {
      return [];
    }

    $raw_values = is_array($value) ? $value : [$value];
    $ids = [];

    foreach ($raw_values as $raw_value) {
      if (is_array($raw_value)) {
        $raw_value = implode(self::SEPARATOR, $raw_value);
      }
      if (!is_scalar($raw_value)) {
        continue;
      }

      foreach (preg_split('/[~,]/', (string) $raw_value) ?: [] as $part) {
        $part = trim($part);
        if ($part !== '' && ctype_digit($part)) {
          $ids[] = (int) $part;
        }
      }
    }

    $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    sort($ids, SORT_NUMERIC);

    return array_slice($ids, 0, $limit);
  }

  /**
   * Normalizes a Date period navigation query value.
   */
  public function normalizeMonth(mixed $value): ?string {
    if (is_array($value)) {
      $value = reset($value);
    }
    if (!is_string($value) && !is_numeric($value)) {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '6month') {
      $value = self::SIX_MONTHS_VALUE;
    }

    return in_array($value, array_keys($this->getAllowedMonthValues()), TRUE) ? $value : NULL;
  }

  /**
   * Returns current valid Date period query values.
   *
   * @return array<string, true>
   *   Valid month query values keyed by value.
   */
  public function getAllowedMonthValues(): array {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $start = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone($timezone)
      ->modify('first day of this month')
      ->setTime(0, 0);

    $values = [];
    for ($i = 0; $i < 7; $i++) {
      $values[$start->modify('+' . $i . ' months')->format('Y-n')] = TRUE;
    }
    $values[self::SIX_MONTHS_VALUE] = TRUE;

    return $values;
  }

  /**
   * Normalizes sort to configured sort key values.
   */
  private function normalizeSort(mixed $value): ?string {
    if (is_array($value)) {
      $value = reset($value);
    }
    if (!is_string($value) && !is_numeric($value)) {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    $allowed_values = array_map('strval', $this->normalizeTermIds(
      $this->configFactory->get('nomads_navigation.settings')->get('sort_key_tids') ?? [],
      2,
    ));

    return in_array($value, $allowed_values, TRUE) ? $value : NULL;
  }

}
