<?php

declare(strict_types=1);

namespace Drupal\nomads_listing\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ListingFormModeEditController implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
    );
  }

  public function editText(NodeInterface $node): array {
    return $this->buildForm($node, 'text');
  }

  public function editImages(NodeInterface $node): array {
    return $this->buildForm($node, 'images');
  }

  public function editOffers(NodeInterface $node): array {
    return $this->buildForm($node, 'offers');
  }

  private function buildForm(NodeInterface $node, string $operation): array {
    if ($node->bundle() !== 'listing') {
      throw new NotFoundHttpException();
    }

    // Use the core node edit form handler, then switch its operation so
    // ContentEntityForm resolves the configured form display for this mode.
    $form_object = $this->entityTypeManager
      ->getFormObject('node', 'edit')
      ->setOperation($operation);
    $form_object->setEntity($node);

    return $this->formBuilder->getForm($form_object);
  }

}
