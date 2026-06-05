<?php

declare(strict_types=1);

namespace Drupal\nomads_dashboards\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\link\LinkItemInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines virtual dashboard structures derived from existing entities.
 */
final class DashboardBuilder {

  /**
   * Supported body field names for dashboard help nodes.
   */
  private const HELP_BODY_FIELDS = [
    'field_body',
    'body',
  ];

  /**
   * Supported CTA link field names for dashboard help nodes.
   */
  private const HELP_CTA_FIELDS = [
    'field_dashbaord_cta',
    'field_dashboard_cta',
  ];

  /**
   * Constructs a DashboardBuilder object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Builds user dashboard tiles.
   */
  public function buildUserDashboard(UserInterface $user): array {
    return [
      $this->tile('user', '1'),
      $this->tile('user', '2'),
      $this->tile('user', '3'),
      $this->tile('user', '4'),
      $this->tile('user', '5'),
      $this->tile('user', '6'),
    ];
  }

  /**
   * Builds team dashboard tiles.
   */
  public function buildTeamDashboard(UserInterface $user): array {
    return [
      $this->tile('team', '1'),
      $this->tile('team', '2'),
      $this->tile('team', '3'),
      $this->tile('team', '4'),
      $this->tile('team', '5'),
      $this->tile('team', '6'),
    ];
  }

  /**
   * Builds listing dashboard tiles.
   */
  public function buildListingDashboard(NodeInterface $node): array {
    return [
      $this->tile('listing', '1'),
      $this->tile('listing', '2'),
      $this->tile('listing', '3'),
      $this->tile('listing', '4'),
      $this->tile('listing', '5'),
      $this->tile('listing', '6'),
    ];
  }

  /**
   * Builds organizer dashboard tiles.
   */
  public function buildOrganizerDashboard(NodeInterface $node): array {
    return [
      $this->tile('organizer', '1'),
      $this->tile('organizer', '2'),
      $this->tile('organizer', '3'),
      $this->tile('organizer', '4'),
      $this->tile('organizer', '5'),
      $this->tile('organizer', '6'),
    ];
  }

  /**
   * Creates a normalized render-ready dashboard tile.
   *
   * @param array<string, mixed>|null $help
   *   Optional help metadata reserved for a later help content loader.
   * @param array<string, mixed> $attributes
   *   Optional tile attributes.
   */
  private function tile(
    string $dashboard_id,
    string $id,
    string $variant = 'standard',
    ?array $help = NULL,
    array $attributes = [],
  ): array {
    $configured_tile = $this->buildConfiguredTileData($dashboard_id, $id);

    $tile = [
      '#theme' => 'nomads_dashboard_tile',
      '#tile' => [
        'id' => $id,
        'variant' => $variant,
        'title' => $configured_tile['title'] ?: $id,
        'text' => $configured_tile['summary'],
        'view' => $configured_tile['view'],
        'views' => [],
        'actions' => $configured_tile['actions'],
        'help' => $help,
        'attributes' => $attributes,
      ],
      '#cache' => [
        'tags' => ['config:nomads_dashboards.settings'],
      ],
    ];

    if ($configured_tile['help_node'] instanceof NodeInterface) {
      $tile['#cache']['tags'] = array_values(array_unique([
        ...$tile['#cache']['tags'],
        ...$configured_tile['help_node']->getCacheTags(),
      ]));
    }

    return $tile;
  }

  /**
   * Builds configured title, help text, view, and CTA data for one tile.
   *
   * @return array{title: ?string, summary: mixed, view: ?array, actions: array<int, array{title: string, url: \Drupal\Core\Url}>, help_node: ?\Drupal\node\NodeInterface}
   *   Configured tile data.
   */
  private function buildConfiguredTileData(string $dashboard_id, string $tile_id): array {
    $settings = $this->configFactory->get('nomads_dashboards.settings');
    $help_node = $this->loadHelpNode($settings->get("help_nodes.$dashboard_id.$tile_id"));

    return [
      'title' => $settings->get("titles.$dashboard_id.$tile_id"),
      'summary' => $this->buildHelpSummary($help_node),
      'view' => $this->buildConfiguredView($settings->get("views.$dashboard_id.$tile_id")),
      'actions' => $this->buildHelpActions($help_node),
      'help_node' => $help_node,
    ];
  }

  /**
   * Loads the configured help node.
   */
  private function loadHelpNode(mixed $nid): ?NodeInterface {
    $nid = (int) $nid;
    if ($nid <= 0) {
      return NULL;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Builds the rendered field_body summary from a help node.
   */
  private function buildHelpSummary(?NodeInterface $node): ?array {
    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    foreach (self::HELP_BODY_FIELDS as $field_name) {
      if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
        continue;
      }

      $item = $node->get($field_name)->first();
      $summary = trim((string) ($item->summary ?? ''));
      if ($summary === '') {
        $summary = trim((string) ($item->value ?? ''));
      }

      if ($summary === '') {
        continue;
      }

      return [
        '#type' => 'processed_text',
        '#text' => $summary,
        '#format' => $item->format ?? NULL,
      ];
    }

    return NULL;
  }

  /**
   * Builds configured CTA links from a help node.
   *
   * @return array<int, array{title: string, url: \Drupal\Core\Url}>
   *   Tile action links.
   */
  private function buildHelpActions(?NodeInterface $node): array {
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $actions = [];

    foreach (self::HELP_CTA_FIELDS as $field_name) {
      if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($node->get($field_name) as $item) {
        if (!$item instanceof LinkItemInterface || $item->isEmpty()) {
          continue;
        }

        $url = $item->getUrl();
        $actions[] = [
          'title' => $item->title ?: $url->toString(),
          'url' => $url,
        ];
      }

      if ($actions !== []) {
        break;
      }
    }

    return $actions;
  }

  /**
   * Builds a render array for a configured view reference.
   */
  private function buildConfiguredView(mixed $view_reference): ?array {
    if (!is_array($view_reference) || empty($view_reference['view_id']) || empty($view_reference['display_id'])) {
      return NULL;
    }

    return [
      '#type' => 'view',
      '#name' => $view_reference['view_id'],
      '#display_id' => $view_reference['display_id'],
    ];
  }

}
