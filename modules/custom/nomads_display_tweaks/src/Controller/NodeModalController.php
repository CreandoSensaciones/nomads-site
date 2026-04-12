<?php

namespace Drupal\nomads_display_tweaks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns node content in modal view mode.
 */
final class NodeModalController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Builds the modal view of a node.
   */
  public function view(NodeInterface $node): array {
    $view_builder = $this->etm->getViewBuilder('node');
    $build = $view_builder->view($node, 'modal');
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $build;
  }

  /**
   * Provides the modal page title.
   */
  public function title(NodeInterface $node): string {
    return $node->label();
  }

}
