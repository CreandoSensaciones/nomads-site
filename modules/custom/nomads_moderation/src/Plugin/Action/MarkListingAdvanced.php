<?php

namespace Drupal\nomads_moderation\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Marks selected listings as advanced.
 */
#[Action(
  id: 'nomads_moderation_mark_listing_advanced',
  label: new TranslatableMarkup('Mark selected listings as advanced'),
  type: 'node'
)]
class MarkListingAdvanced extends ListingAdvancedStateActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getAdvancedListingValue(): int {
    return 1;
  }

}
