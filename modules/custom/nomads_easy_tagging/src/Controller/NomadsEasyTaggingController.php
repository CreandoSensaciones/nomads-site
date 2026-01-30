<?php

namespace Drupal\nomads_easy_tagging\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
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
   * The language manager.
   */
  protected LanguageManagerInterface $langManager;

  /**
   * Constructs the controller.
   */
  public function __construct(NomadsEasyTaggingConstraintResolver $resolver, LanguageManagerInterface $languageManager) {
    $this->resolver = $resolver;
    $this->langManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('nomads_easy_tagging.constraint_resolver'),
      $container->get('language_manager'),
    );
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
