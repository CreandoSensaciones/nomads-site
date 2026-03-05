<?php

namespace Drupal\nomads_geo_widget\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;

/**
 * Selection handler for country terms only.
 */
#[EntityReferenceSelection(
  id: 'nomads_geo_widget:country_terms',
  label: new TranslatableMarkup('Nomads geo country term selection'),
  entity_types: ['taxonomy_term'],
  group: 'default',
  weight: 10,
)]
class CountryTermSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $configuration = $this->getConfiguration();

    $type_field = (string) ($configuration['type_field'] ?? '');
    $country_value = (string) ($configuration['country_value'] ?? '');
    $region_values = $configuration['region_values'] ?? [];
    $region_values = is_array($region_values)
      ? array_values(array_filter(array_map('strval', $region_values), static fn(string $value): bool => trim($value) !== ''))
      : [];

    $allowed_type_values = $region_values;
    if ($country_value !== '') {
      $allowed_type_values[] = $country_value;
    }
    $allowed_type_values = array_values(array_unique($allowed_type_values));

    if ($type_field !== '' && $allowed_type_values !== []) {
      if (count($allowed_type_values) === 1) {
        $query->condition($type_field, reset($allowed_type_values));
      }
      else {
        $query->condition($type_field, $allowed_type_values, 'IN');
      }
    }

    return $query;
  }

}
