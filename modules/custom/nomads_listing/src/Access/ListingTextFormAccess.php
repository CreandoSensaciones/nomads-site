<?php

declare(strict_types=1);

namespace Drupal\nomads_listing\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

final class ListingTextFormAccess {

  public static function access(NodeInterface $node, AccountInterface $account): AccessResult {
    $bundle_access = AccessResult::allowedIf($node->bundle() === 'listing');

    return $bundle_access
      ->andIf($node->access('update', $account, TRUE))
      ->addCacheableDependency($node);
  }

}
