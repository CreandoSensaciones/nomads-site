<?php

namespace Drupal\listing_details_handling\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles listing details tab actions.
 */
class ListingDetailsHandlingController {

  /**
   * Redirect to the details edit form, creating details if missing.
   */
  public function detailsAction(NodeInterface $node): RedirectResponse {
    if ($node->bundle() !== 'listing' || !$node->hasField('field_listing_ref')) {
      throw new NotFoundHttpException();
    }

    if ($node->hasField('field_listing_ref') && !$node->get('field_listing_ref')->isEmpty()) {
      $details_id = (int) $node->get('field_listing_ref')->target_id;
    }
    else {
      $details_id = listing_details_handling_get_details_id((int) $node->id(), $node);
    }

    if (!$details_id) {
      return new RedirectResponse(Url::fromRoute('entity.node.edit_form', ['node' => $node->id()])->toString());
    }

    $url = Url::fromRoute('entity.node.edit_form', ['node' => $details_id]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Access check for listing details action.
   */
  public function accessDetailsAction(NodeInterface $node, AccountInterface $account): AccessResult {
    if ($node->bundle() !== 'listing' || !$node->hasField('field_listing_ref')) {
      return AccessResult::forbidden();
    }

    $listing_access = $node->access('update', $account, TRUE);
    if (!$listing_access->isAllowed()) {
      return $listing_access;
    }

    $details_access = $node->hasField('field_listing_ref') && !$node->get('field_listing_ref')->isEmpty()
      ? $node->get('field_listing_ref')->entity->access('update', $account, TRUE)
      : $node->getEntityType()->getAccessControlHandler()->createAccess('details', $account, [], TRUE);

    return $listing_access->andIf($details_access)->addCacheableDependency($node);
  }

}
