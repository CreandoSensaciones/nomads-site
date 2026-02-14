<?php

namespace Drupal\paragraph_relevance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns paragraph content in modal view mode.
 */
final class ParagraphModalController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Builds the modal view of a paragraph.
   */
  public function view(ParagraphInterface $paragraph): array {
    $view_builder = $this->etm->getViewBuilder('paragraph');
    $build = $view_builder->view($paragraph, 'modal');
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $build;
  }

}
