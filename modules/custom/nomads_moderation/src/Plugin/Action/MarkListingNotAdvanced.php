<?php

namespace Drupal\nomads_moderation\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Marks selected listings as not advanced.
 */
#[Action(
  id: 'nomads_moderation_mark_listing_not_advanced',
  label: new TranslatableMarkup('Mark selected listings as not advanced'),
  type: 'node'
)]
class MarkListingNotAdvanced extends ListingAdvancedStateActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getAdvancedListingValue(): int {
    return 0;
  }

}
