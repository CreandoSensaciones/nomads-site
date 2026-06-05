<?php

namespace Drupal\nomads_moderation\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\nomads_moderation\ListingState;

/**
 * Base class for Advanced listing state bulk actions.
 */
abstract class ListingAdvancedStateActionBase extends ActionBase {

  /**
   * The Advanced listing state to set.
   */
  abstract protected function getAdvancedListingValue(): int;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if (!$entity instanceof NodeInterface || !$this->appliesTo($entity)) {
      return;
    }

    $entity->set(ListingState::ADVANCED_FIELD, $this->getAdvancedListingValue());
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object instanceof NodeInterface || !$this->appliesTo($object)) {
      $result = AccessResult::forbidden();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = $object
      ->access('update', $account, TRUE)
      ->andIf($object->get(ListingState::ADVANCED_FIELD)->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * Determines whether this action applies to the given node.
   */
  protected function appliesTo(NodeInterface $node): bool {
    return $node->bundle() === 'listing' && $node->hasField(ListingState::ADVANCED_FIELD);
  }

}
