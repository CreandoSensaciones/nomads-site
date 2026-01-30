<?php

namespace Drupal\nomads_fitted_pills\Packer;

/**
 * Packs items into rows using a character-count heuristic.
 */
class FittedPillsPacker {

  public const MAX_CHARS_PER_ROW = 40;
  public const PILL_OVERHEAD = 3;
  public const TRIGGER_GAP = 8;
  public const MIN_IMPROVEMENT = 2;
  public const SHORT_POOL_MAX_LABEL_LEN = 10;

  /**
   * Packs items into rows.
   *
   * @param array $items
   *   Items with at least a 'label' key.
   * @param array $settings
   *   Settings array: max_chars_per_row, pill_overhead, trigger_gap,
   *   min_improvement.
   *
   * @return array
   *   Rows of items.
   */
  public function pack(array $items, array $settings = []): array {
    $remaining = array_values($items);
    if ($remaining === []) {
      return [];
    }

    $max = max(1, (int) ($settings['max_chars_per_row'] ?? self::MAX_CHARS_PER_ROW));
    $overhead = max(0, (int) ($settings['pill_overhead'] ?? self::PILL_OVERHEAD));
    $trigger_gap = max(0, (int) ($settings['trigger_gap'] ?? self::TRIGGER_GAP));
    $min_improvement = max(0, (int) ($settings['min_improvement'] ?? self::MIN_IMPROVEMENT));

    $rows = [];

    while ($remaining !== []) {
      $row_items = [];
      $row_chars = 0;
      $index = 0;
      $count = count($remaining);

      while ($index < $count) {
        $item = $remaining[$index];
        $size = $this->getItemSize($item, $overhead);

        if ($row_chars + $size <= $max) {
          $row_items[] = $item;
          $row_chars += $size;
          ++$index;
          continue;
        }

        break;
      }

      if ($row_items === []) {
        $row_items[] = array_shift($remaining);
        $row_chars = $this->getItemSize($row_items[0], $overhead);
      }
      else {
        $remaining = array_slice($remaining, $index);
      }

      $unused = $max - $row_chars;
      if ($unused >= $trigger_gap && $remaining !== []) {
        $best_index = NULL;
        $best_unused = $unused;

        foreach ($remaining as $candidate_index => $candidate) {
          $candidate_size = $this->getItemSize($candidate, $overhead);
          $unused_new = $unused - $candidate_size;
          if ($unused_new >= 0 && $unused_new < $best_unused) {
            $best_unused = $unused_new;
            $best_index = $candidate_index;
          }
        }

        if ($best_index !== NULL && ($unused - $best_unused) >= $min_improvement) {
          $row_items[] = $remaining[$best_index];
          unset($remaining[$best_index]);
          $remaining = array_values($remaining);
        }
      }

      $rows[] = $row_items;
    }

    return $rows;
  }

  /**
   * Packs items into rows and fills gaps with short items.
   *
   * @param array $items
   *   Primary items to keep in order.
   * @param array $short_items
   *   Short items used to fill gaps.
   * @param array $settings
   *   Settings array: max_chars_per_row, pill_overhead.
   *
   * @return array
   *   Rows of items.
   */
  public function packWithShortPool(array $items, array $short_items, array $settings = []): array {
    $short_pool = array_values($short_items);

    $max = max(1, (int) ($settings['max_chars_per_row'] ?? self::MAX_CHARS_PER_ROW));
    $overhead = max(0, (int) ($settings['pill_overhead'] ?? self::PILL_OVERHEAD));

    $rows = $this->pack($items, $settings);

    for ($row_index = count($rows) - 1; $row_index >= 0; --$row_index) {
      if ($short_pool === []) {
        break;
      }
      $row_items = $rows[$row_index];
      $row_chars = $this->getRowSize($row_items, $overhead);
      $unused = $max - $row_chars;
      if ($unused > 0) {
        $this->fillWithShortPool($row_items, $short_pool, $unused, $overhead);
        $rows[$row_index] = $row_items;
      }
    }

    if ($short_pool !== []) {
      $rows = array_merge($rows, $this->pack($short_pool, $settings));
    }

    return $rows;
  }

  /**
   * Computes the size of a pill based on label length and overhead.
   */
  protected function getItemSize(array $item, int $overhead): int {
    $label = (string) ($item['label'] ?? '');
    return strlen($label) + $overhead;
  }

  /**
   * Computes the size of a row.
   *
   * @param array $row_items
   *   Row items.
   * @param int $overhead
   *   Overhead per pill.
   *
   * @return int
   *   Size in characters.
   */
  protected function getRowSize(array $row_items, int $overhead): int {
    $size = 0;
    foreach ($row_items as $item) {
      $size += $this->getItemSize($item, $overhead);
    }
    return $size;
  }

  /**
   * Fills a row with short items to maximize used characters.
   *
   * @param array $row_items
   *   Row items to append to.
   * @param array $short_pool
   *   Short items pool, passed by reference.
   * @param int $unused
   *   Remaining character budget, passed by reference.
   * @param int $overhead
   *   Overhead per pill.
   */
  protected function fillWithShortPool(array &$row_items, array &$short_pool, int &$unused, int $overhead): void {
    while ($unused > 0 && $short_pool !== []) {
      $best_index = NULL;
      $best_size = 0;

      foreach ($short_pool as $index => $candidate) {
        $size = $this->getItemSize($candidate, $overhead);
        if ($size <= $unused && $size > $best_size) {
          $best_size = $size;
          $best_index = $index;
        }
      }

      if ($best_index === NULL) {
        break;
      }

      $row_items[] = $short_pool[$best_index];
      unset($short_pool[$best_index]);
      $short_pool = array_values($short_pool);
      $unused -= $best_size;
    }
  }

}
