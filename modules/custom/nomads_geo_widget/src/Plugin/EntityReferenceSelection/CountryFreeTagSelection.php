<?php

namespace Drupal\nomads_geo_widget\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;

/**
 * Selection handler for free-tag places under a selected country.
 */
#[EntityReferenceSelection(
  id: 'nomads_geo_widget:country_free_tags',
  label: new TranslatableMarkup('Nomads geo free-tag term selection'),
  entity_types: ['taxonomy_term'],
  group: 'default',
  weight: 10,
)]
class CountryFreeTagSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $configuration = $this->getConfiguration();

    $country_tid = (int) ($configuration['country_tid'] ?? 0);
    if ($country_tid > 0) {
      $query->condition('parent', $country_tid);
    }

    $type_field = (string) ($configuration['type_field'] ?? '');
    $freetag_value = (string) ($configuration['freetag_value'] ?? '');
    if ($type_field !== '' && $freetag_value !== '') {
      $query->condition($type_field, $freetag_value);
    }

    return $query;
  }

}
