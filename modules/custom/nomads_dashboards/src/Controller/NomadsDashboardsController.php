<?php

declare(strict_types=1);

namespace Drupal\nomads_dashboards\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\nomads_dashboards\Service\DashboardBuilder;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds virtual Nomads dashboard pages.
 */
final class NomadsDashboardsController implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\nomads_dashboards\Service\DashboardBuilder $dashboardBuilder
   *   The dashboard builder service.
   */
  public function __construct(
    private readonly DashboardBuilder $dashboardBuilder,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nomads_dashboards.builder'),
    );
  }

  /**
   * Builds a user dashboard.
   */
  public function userDashboard(UserInterface $user): array {
    if (!$this->canAccessUserDashboard($user)) {
      throw new AccessDeniedHttpException();
    }

    return $this->buildDashboard('user', $user, $this->dashboardBuilder->buildUserDashboard($user));
  }

  /**
   * Builds a team dashboard.
   */
  public function teamDashboard(UserInterface $user): array {
    if (!$this->canAccessTeamDashboard($user)) {
      throw new AccessDeniedHttpException();
    }

    return $this->buildDashboard('team', $user, $this->dashboardBuilder->buildTeamDashboard($user));
  }

  /**
   * Builds the user dashboard page title.
   */
  public function userDashboardTitle(UserInterface $user): string {
    return sprintf("%s's Dashboard", $user->label());
  }

  /**
   * Builds a supported node dashboard.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the node bundle has no dashboard.
   */
  public function nodeDashboard(NodeInterface $node): array {
    if (!$this->canAccessNodeDashboard($node)) {
      throw new AccessDeniedHttpException();
    }

    return match ($node->bundle()) {
      'listing' => $this->buildDashboard('listing', $node, $this->dashboardBuilder->buildListingDashboard($node)),
      'organizer' => $this->buildDashboard('organizer', $node, $this->dashboardBuilder->buildOrganizerDashboard($node)),
      default => throw new NotFoundHttpException(),
    };
  }

  /**
   * Builds the node dashboard page title.
   */
  public function nodeDashboardTitle(NodeInterface $node): MarkupInterface {
    return Markup::create(sprintf(
      '<span class="title">%s</span> Dashboard',
      Html::escape($node->label()),
    ));
  }

  /**
   * Checks access to a user dashboard.
   */
  public function accessUserDashboard(UserInterface $user, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($this->canAccessUserDashboard($user, $account))
      ->cachePerUser()
      ->addCacheableDependency($user);
  }

  /**
   * Checks access to a team dashboard.
   */
  public function accessTeamDashboard(UserInterface $user, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($this->canAccessTeamDashboard($user, $account))
      ->cachePerUser()
      ->addCacheableDependency($user);
  }

  /**
   * Checks access to supported node dashboards.
   */
  public function accessNodeDashboard(NodeInterface $node, AccountInterface $account): AccessResult {
    return AccessResult::allowedIf($this->canAccessNodeDashboard($node, $account))
      ->cachePerUser()
      ->addCacheableDependency($node);
  }

  /**
   * Determines whether an account can view a user dashboard.
   */
  private function canAccessUserDashboard(UserInterface $user, ?AccountInterface $account = NULL): bool {
    $account ??= \Drupal::currentUser();

    return !$account->isAnonymous()
      && (
        (string) $user->id() === (string) $account->id()
        || $account->hasRole('administrator')
      );
  }

  /**
   * Determines whether an account can view a team dashboard.
   */
  private function canAccessTeamDashboard(UserInterface $user, ?AccountInterface $account = NULL): bool {
    $account ??= \Drupal::currentUser();

    return !$account->isAnonymous()
      && $account->hasRole('team')
      && (string) $user->id() === (string) $account->id();
  }

  /**
   * Determines whether an account can view a listing or organizer dashboard.
   */
  private function canAccessNodeDashboard(NodeInterface $node, ?AccountInterface $account = NULL): bool {
    $account ??= \Drupal::currentUser();

    return !$account->isAnonymous()
      && in_array($node->bundle(), ['listing', 'organizer'], TRUE)
      && (
        (string) $node->getOwnerId() === (string) $account->id()
        || $node->access('update', $account)
        || $account->hasRole('administrator')
      );
  }

  /**
   * Wraps dashboard tiles in the shared grid theme hook.
   */
  private function buildDashboard(string $dashboard_type, UserInterface|NodeInterface $entity, array $tiles): array {
    return [
      '#theme' => 'nomads_dashboard_grid',
      '#dashboard_type' => $dashboard_type,
      '#entity' => $entity,
      '#tiles' => $tiles,
      '#attributes' => [
        'class' => [
          'nomads-dashboard',
          'nomads-dashboard--' . $dashboard_type,
        ],
      ],
      '#attached' => [
        'library' => [
          'nomads_dashboards/dashboard',
        ],
      ],
    ];
  }

}
