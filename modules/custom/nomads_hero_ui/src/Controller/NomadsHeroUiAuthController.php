<?php

declare(strict_types=1);

namespace Drupal\nomads_hero_ui\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * AJAX controller for swapping anonymous auth forms on gated hero nodes.
 */
final class NomadsHeroUiAuthController extends ControllerBase {

  /**
   * Replaces the auth panel with the requested form.
   */
  public function switchForm(NodeInterface $node, string $form_type): AjaxResponse {
    $response = new AjaxResponse();

    if ($this->currentUser()->isAuthenticated() || $node->bundle() !== 'hero' || !nomads_hero_ui_node_requires_login($node)) {
      return $response;
    }

    $panel = nomads_hero_ui_build_content_slot($node, $form_type);
    $response->addCommand(new ReplaceCommand(
      '#' . nomads_hero_ui_get_content_wrapper_id($node),
      $panel,
    ));

    return $response;
  }

}
