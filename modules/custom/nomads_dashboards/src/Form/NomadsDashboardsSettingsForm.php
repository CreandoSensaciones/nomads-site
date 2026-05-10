<?php

declare(strict_types=1);

namespace Drupal\nomads_dashboards\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures dashboard help content references.
 */
final class NomadsDashboardsSettingsForm extends ConfigFormBase {

  /**
   * Dashboard tile definitions used by the settings form.
   */
  private const DASHBOARD_TILES = [
    'user' => [
      'label' => 'User dashboard',
      'tiles' => ['1', '2', '3', '4', '5', '6'],
    ],
    'team' => [
      'label' => 'Team dashboard',
      'tiles' => ['1', '2', '3', '4', '5', '6'],
    ],
    'listing' => [
      'label' => 'Listing dashboard',
      'tiles' => ['1', '2', '3', '4', '5', '6'],
    ],
    'organizer' => [
      'label' => 'Organizer dashboard',
      'tiles' => ['1', '2', '3', '4', '5', '6'],
    ],
  ];

  /**
   * Constructs a NomadsDashboardsSettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nomads_dashboards_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['nomads_dashboards.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('nomads_dashboards.settings');
    $view_options = $this->buildViewOptions();

    $form['help_nodes'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Dashboard tiles'),
    ];

    foreach (self::DASHBOARD_TILES as $dashboard_id => $dashboard) {
      $form[$dashboard_id] = [
        '#type' => 'details',
        '#title' => $this->t($dashboard['label']),
        '#group' => 'help_nodes',
        '#tree' => TRUE,
      ];

      foreach ($dashboard['tiles'] as $tile_id) {
        $configured_nid = $settings->get("help_nodes.$dashboard_id.$tile_id");
        $configured_view = $settings->get("views.$dashboard_id.$tile_id");

        $form[$dashboard_id][$tile_id] = [
          '#type' => 'details',
          '#title' => $this->t('Tile @number', ['@number' => $tile_id]),
          '#open' => $tile_id === '1',
        ];

        $form[$dashboard_id][$tile_id]['title'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Title'),
          '#default_value' => $settings->get("titles.$dashboard_id.$tile_id") ?? $tile_id,
          '#maxlength' => 255,
        ];

        $form[$dashboard_id][$tile_id]['help_node'] = [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Help node'),
          '#description' => $this->t('Select the node that holds field_body summary text and field_dashbaord_cta links for this tile.'),
          '#target_type' => 'node',
          '#default_value' => $this->loadNode($configured_nid),
          '#selection_handler' => 'default',
          '#maxlength' => 255,
          '#placeholder' => $this->t('Start typing a node title'),
        ];

        $form[$dashboard_id][$tile_id]['view'] = [
          '#type' => 'select',
          '#title' => $this->t('View'),
          '#description' => $this->t('Select the view display rendered below the help text.'),
          '#options' => $view_options,
          '#default_value' => $this->buildViewReferenceKey($configured_view),
          '#empty_option' => $this->t('- None -'),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('nomads_dashboards.settings');

    foreach (self::DASHBOARD_TILES as $dashboard_id => $dashboard) {
      foreach ($dashboard['tiles'] as $tile_id) {
        $values = $form_state->getValue([$dashboard_id, $tile_id]) ?? [];
        $config->set(
          "titles.$dashboard_id.$tile_id",
          trim((string) ($values['title'] ?? $tile_id)),
        );
        $config->set(
          "help_nodes.$dashboard_id.$tile_id",
          $this->extractNodeId($values['help_node'] ?? NULL),
        );
        $config->set(
          "views.$dashboard_id.$tile_id",
          $this->parseViewReference($values['view'] ?? ''),
        );
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Loads a configured node for the autocomplete default value.
   */
  private function loadNode(mixed $nid): ?NodeInterface {
    $nid = (int) $nid;
    if ($nid <= 0) {
      return NULL;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * Extracts a node ID from an entity autocomplete value.
   */
  private function extractNodeId(mixed $value): ?int {
    if (is_array($value) && isset($value['target_id'])) {
      $value = $value['target_id'];
    }

    $nid = (int) $value;
    return $nid > 0 ? $nid : NULL;
  }

  /**
   * Builds select options for available view displays.
   *
   * @return array<string, string>
   *   View display options keyed by "view_id:display_id".
   */
  private function buildViewOptions(): array {
    $options = [];
    $views = $this->entityTypeManager->getStorage('view')->loadMultiple();

    foreach ($views as $view) {
      $view_id = $view->id();
      foreach ($view->get('display') ?? [] as $display_id => $display) {
        $display_title = $display['display_title'] ?? $display_id;
        $options[$view_id . ':' . $display_id] = sprintf('%s: %s', $view->label(), $display_title);
      }
    }

    natcasesort($options);
    return $options;
  }

  /**
   * Builds the select key for a configured view reference.
   */
  private function buildViewReferenceKey(mixed $view_reference): string {
    if (!is_array($view_reference) || empty($view_reference['view_id']) || empty($view_reference['display_id'])) {
      return '';
    }

    return $view_reference['view_id'] . ':' . $view_reference['display_id'];
  }

  /**
   * Parses a configured view select value.
   *
   * @return array{view_id: string, display_id: string}|null
   *   A view reference, or NULL when no view is selected.
   */
  private function parseViewReference(mixed $value): ?array {
    if (!is_string($value) || !str_contains($value, ':')) {
      return NULL;
    }

    [$view_id, $display_id] = explode(':', $value, 2);
    $view_id = trim($view_id);
    $display_id = trim($display_id);

    if ($view_id === '' || $display_id === '') {
      return NULL;
    }

    return [
      'view_id' => $view_id,
      'display_id' => $display_id,
    ];
  }

}
