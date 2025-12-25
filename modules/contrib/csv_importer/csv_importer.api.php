<?php

/**
 * @file
 * API hooks for csv_importer.
 */

/**
 * Update CSV data before the import process starts.
 *
 * @param array $data
 *   The import data.
 *
 * @see \Drupal\csv_importer\Plugin\ImporterBase
 */
function hook_csv_importer_pre_import(array &$data) {
  foreach ($data as &$content) {
    foreach ($content as &$item) {
      $item['title'] = ltrim($item['title'], '/');
    }
  }
}
