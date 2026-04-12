<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class NomadsParentTeaserController extends ControllerBase {

  public function __construct(
    private readonly RendererInterface $renderer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('renderer'),
    );
  }

  public function teaser(NodeInterface $node): Response {
    if (!$node->access('view')) {
      throw new AccessDeniedHttpException();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-parent-teaser-response'],
        'data-parent-nid' => (string) $node->id(),
        'data-view-mode' => 'teaser',
      ],
      'content' => $this->entityTypeManager()
        ->getViewBuilder('node')
        ->view($node, 'teaser'),
    ];

    $html = (string) $this->renderer->renderRoot($build);
    $attachments = BubbleableMetadata::createFromRenderArray($build)->getAttachments();
    $settings = $attachments['drupalSettings'] ?? [];
    if ($settings !== []) {
      $html .= '<script type="application/json" class="js-nomads-parent-teaser-settings">' . str_replace('</', '<\/', Json::encode($settings)) . '</script>';
    }

    return new Response($html);
  }

}
