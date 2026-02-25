<?php

namespace Drupal\nomads_easy_tagging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\nomads_easy_tagging\Service\NomadsEasyTaggingConstraintResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for easy tagging endpoints.
 */
class NomadsEasyTaggingController extends ControllerBase {

  /**
   * The constraint resolver.
   */
  protected NomadsEasyTaggingConstraintResolver $resolver;

  /**
   * Constructs the controller.
   */
  public function __construct(NomadsEasyTaggingConstraintResolver $resolver) {
    $this->resolver = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('nomads_easy_tagging.constraint_resolver'),
    );
  }

  /**
   * Access check for easy-tagging read/write endpoints.
   */
  public function endpointAccess(AccountInterface $account): AccessResult {
    $listing_create_access = \Drupal::entityTypeManager()
      ->getAccessControlHandler('node')
      ->createAccess('listing', $account, [], TRUE);

    return AccessResult::allowedIfHasPermission($account, 'administer taxonomy')
      ->orIf($listing_create_access);
  }

  /**
   * Return children for a given parent term.
   */
  public function children(int $parent_tid): JsonResponse {
    $data = $this->resolver->getChildren($parent_tid);

    return new JsonResponse($data);
  }

  /**
   * Return constraint data for selected terms.
   */
  public function constraints(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      $payload = [];
    }

    $selected_unified = $this->sanitizeTidList($payload['selected_unified'] ?? []);
    $selected_types = $this->sanitizeTidList($payload['selected_types'] ?? []);

    $data = $this->resolver->computeBlocked($selected_unified, $selected_types);

    $response = new JsonResponse($data);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

    return $response;
  }

  /**
   * Sanitize a list of term ids.
   */
  protected function sanitizeTidList(array $values): array {
    $clean = [];
    foreach ($values as $value) {
      if (is_numeric($value)) {
        $clean[] = (int) $value;
      }
    }
    return array_values(array_unique($clean));
  }

}
