<?php

namespace Drupal\nomads_listing_wizard\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the listing onboarding form for the wizard route.
 */
class ListingWizardController implements ContainerInjectionInterface {

  /**
   * The entity form builder service.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   */
  public function __construct(EntityFormBuilderInterface $entity_form_builder) {
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity.form_builder')
    );
  }

  /**
   * Builds the onboarding form for a new listing node.
   *
   * @return array
   *   A render array for the onboarding form.
   */
  public function wizard(): AjaxResponse|array {
    $node = Node::create(['type' => 'listing']);
    $form = $this->entityFormBuilder->getForm($node, 'onboarding');
    $form = [
      '#type' => 'container',
      '#attributes' => ['class' => ['listing-wizard']],
      'form' => $form,
    ];

    if (\Drupal::request()->query->get('_wrapper_format') === 'drupal_ajax') {
      $form['#attributes']['class'][] = 'listing-wizard-modal';
      $form['form']['#attributes']['class'][] = 'listing-wizard-modal';
      $response = new AjaxResponse();
      $response->addCommand(new OpenModalDialogCommand('Create listing', $form, ['width' => 1100]));
      return $response;
    }

    return $form;
  }

}
