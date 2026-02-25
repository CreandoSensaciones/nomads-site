<?php

namespace Drupal\nomads_listing_wizard\EntityReferenceSelection;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManager;

/**
 * Prevents fatal errors when handler groups are malformed or unavailable.
 */
class SafeSelectionPluginManager extends SelectionPluginManager {

  /**
   * {@inheritdoc}
   */
  public function getPluginId($target_type, $base_plugin_id) {
    $selection_handler_groups = $this->getSelectionGroups($target_type);

    $candidates = $selection_handler_groups[$base_plugin_id] ?? NULL;
    if (!is_array($candidates) || $candidates === []) {
      $fallback_candidates = $selection_handler_groups['default'] ?? NULL;
      if (is_array($fallback_candidates) && $fallback_candidates !== []) {
        uasort($fallback_candidates, [SortArray::class, 'sortByWeightElement']);
        return (string) array_key_last($fallback_candidates);
      }
      return $this->getFallbackPluginId((string) $base_plugin_id, [
        'target_type' => $target_type,
      ]);
    }

    uasort($candidates, [SortArray::class, 'sortByWeightElement']);
    return (string) array_key_last($candidates);
  }

}

