<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\domain_config\DomainConfigOverrider;
use Drupal\nomads_navigation\FilterQueryNormalizer;
use Drupal\nomads_navigation\TermLabelOverrideManager;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides configurable child-term pills for specific taxonomy navigation.
 */
#[Block(
  id: 'nomads_navigation_specific_navigation',
  admin_label: new TranslatableMarkup('Specific navigation'),
  category: new TranslatableMarkup('Nomads')
)]
final class SpecificNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const QUERY_KEY = 't';
  private const QUERY_SEPARATOR = '~';

  /**
   * Constructs a SpecificNavigationBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StorageInterface $configStorage,
    private readonly TermStorageInterface $termStorage,
    private readonly RequestStack $requestStack,
    private readonly TermLabelOverrideManager $labelOverrideManager,
    private readonly FilterQueryNormalizer $filterQueryNormalizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('request_stack'),
      $container->get('nomads_navigation.term_label_override_manager'),
      $container->get('nomads_navigation.filter_query_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $configured_terms = $this->getConfiguredTerms();
    if ($configured_terms === []) {
      return [];
    }

    $configured_ids = array_values(array_unique(array_map(
      static fn (array $configured_term): int => (int) $configured_term['id'],
      $configured_terms,
    )));
    $loaded_terms = $this->termStorage->loadMultiple($configured_ids);
    if ($loaded_terms === []) {
      return [];
    }

    $selected_ids = $this->getSelectedTermIds();
    $items = [];
    $render_groups = [];
    $cache_tags = Cache::mergeTags($this->getSpecificNavigationConfigCacheTags(), $this->labelOverrideManager->getCacheTags());

    foreach ($configured_terms as $configured_term) {
      $parent_id = (int) $configured_term['id'];
      $parent = $loaded_terms[$parent_id] ?? NULL;
      if (!$parent instanceof TermInterface) {
        continue;
      }

      $cache_tags = Cache::mergeTags($cache_tags, $parent->getCacheTags());

      if (!$configured_term['branch']) {
        $render_groups[] = [
          'type' => 'pill',
          'item' => $this->buildPill($parent, $selected_ids),
        ];
        continue;
      }

      $branch_items = [];
      foreach ($this->loadPublishedChildren($parent_id) as $child) {
        $cache_tags = Cache::mergeTags($cache_tags, $child->getCacheTags());
        $branch_items[] = $this->buildPill($child, $selected_ids);
      }

      if ($branch_items !== []) {
        $render_groups[] = [
          'type' => 'branch',
          'parent' => $parent,
          'parent_id' => $parent_id,
          'items' => $branch_items,
        ];
      }
    }

    if ($render_groups === []) {
      return [];
    }

    $show_branch_labels = count($render_groups) > 1;
    $branch_index = 0;
    foreach ($render_groups as $render_group) {
      if ($render_group['type'] === 'pill') {
        $items[] = $render_group['item'];
        continue;
      }

      if (!$show_branch_labels) {
        foreach ($render_group['items'] as $branch_item) {
          $items[] = $branch_item;
        }
        continue;
      }

      $branch_index++;
      $branch_container = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'branch' . $branch_index,
            'branch-' . $render_group['parent_id'],
          ],
        ],
      ];

      if ($show_branch_labels && $render_group['parent'] instanceof TermInterface) {
        $branch_container['label'] = [
          '#markup' => '<span class="nomads-specific-navigation__branch-label">' . Html::escape($this->labelOverrideManager->getLabel($render_group['parent'])) . '</span>',
          '#weight' => -10,
        ];
      }

      foreach ($render_group['items'] as $delta => $branch_item) {
        $branch_container[$delta] = $branch_item;
      }

      $items[] = $branch_container;
    }

    if ($items === []) {
      return [];
    }

    if ($selected_ids !== []) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('Clear'),
        '#url' => $this->buildClearUrl(),
        '#attributes' => [
          'class' => ['nomads-specific-navigation__clear'],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-specific-navigation'],
      ],
      'items' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['specific-wrapper'],
        ],
      ] + $items,
      '#attached' => [
        'library' => ['nomads_navigation/specific_navigation'],
      ],
      '#cache' => [
        'contexts' => [
          'route',
          'url.path',
          ...FilterQueryNormalizer::CACHE_CONTEXTS,
          'url.site',
          'domain',
        ],
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Gets configured terms with per-term render mode.
   *
   * @return array<int, array{id: int, branch: bool}>
   *   Configured term render definitions.
   */
  private function getConfiguredTerms(): array {
    $raw_terms = $this->getRawSpecificNavigationTerms();
    $configured_terms = [];
    $seen = [];

    foreach ($raw_terms as $raw_term) {
      $raw_term = trim((string) $raw_term);
      if ($raw_term === '') {
        continue;
      }
      if (!preg_match('/^\d+>?$/', $raw_term)) {
        continue;
      }

      $branch = str_ends_with($raw_term, '>');
      $id = (int) rtrim($raw_term, '>');
      if ($id <= 0) {
        continue;
      }

      $key = $id . ($branch ? '>' : '');
      if (isset($seen[$key])) {
        continue;
      }

      $seen[$key] = TRUE;
      $configured_terms[] = [
        'id' => $id,
        'branch' => $branch,
      ];
    }

    return $configured_terms;
  }

  /**
   * Gets raw, non-merged Specific navigation terms for the current domain.
   *
   * Domain Config recursively merges arrays, which can make old numeric term
   * IDs reappear when this setting is intentionally empty. Reading the raw
   * config objects in override order makes an explicit empty list authoritative.
   *
   * @return array<int, int|string>
   *   Configured term tokens.
   */
  private function getRawSpecificNavigationTerms(): array {
    foreach ($this->getSpecificNavigationConfigNames() as $config_name) {
      $data = $this->configStorage->read($config_name);
      if (is_array($data) && array_key_exists('specific_navigation_parent_tids', $data)) {
        $value = $data['specific_navigation_parent_tids'];
        return is_array($value) ? $value : [];
      }
    }

    return [];
  }

  /**
   * Gets config names in the same precedence as Domain Config overrides.
   *
   * @return string[]
   *   Config object names.
   */
  private function getSpecificNavigationConfigNames(): array {
    $config_names = [];

    if (\Drupal::hasService('domain.negotiator')) {
      $domain = \Drupal::service('domain.negotiator')->getActiveDomain();
      if ($domain && method_exists($domain, 'id')) {
        $domain_id = (string) $domain->id();
        $config_names[] = DomainConfigOverrider::getConfigNameByDomain('nomads_navigation.settings', $domain_id);
      }
    }

    $config_names[] = 'nomads_navigation.settings';

    return array_values(array_unique($config_names));
  }

  /**
   * Gets cache tags for config objects that can affect this block.
   *
   * @return string[]
   *   Config cache tags.
   */
  private function getSpecificNavigationConfigCacheTags(): array {
    return array_map(
      static fn (string $config_name): string => 'config:' . $config_name,
      $this->getSpecificNavigationConfigNames(),
    );
  }

  /**
   * Loads direct published children of a parent term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Published child terms.
   */
  private function loadPublishedChildren(int $parent_id): array {
    static $cache = [];
    if (isset($cache[$parent_id])) {
      return $cache[$parent_id];
    }

    $children = array_values(array_filter(
      $this->termStorage->loadChildren($parent_id),
      static fn ($term): bool => $term instanceof TermInterface && $term->isPublished(),
    ));

    usort($children, static function (TermInterface $a, TermInterface $b): int {
      $weight = $a->getWeight() <=> $b->getWeight();
      return $weight !== 0 ? $weight : (int) $a->id() <=> (int) $b->id();
    });

    $cache[$parent_id] = $children;
    return $cache[$parent_id];
  }

  /**
   * Builds one toggle pill.
   *
   * @param int[] $selected_ids
   *   Currently selected term IDs.
   */
  private function buildPill(TermInterface $term, array $selected_ids): array {
    $term_id = (int) $term->id();

    return [
      '#type' => 'link',
      '#title' => $this->buildPillTitle($term),
      '#url' => $this->buildToggleUrl($term_id, $selected_ids),
      '#attributes' => [
        'class' => array_filter([
          'nomads-specific-navigation__pill',
          in_array($term_id, $selected_ids, TRUE) ? 'is-selected' : NULL,
        ]),
      ],
    ];
  }

  /**
   * Builds safe markup for one pill title.
   */
  private function buildPillTitle(TermInterface $term): Markup {
    return Markup::create('<span class="nomads-specific-navigation__term-label">' . Html::escape($this->labelOverrideManager->getLabel($term)) . '</span>');
  }

  /**
   * Builds a URL that toggles one specific navigation term.
   *
   * @param int[] $selected_ids
   *   Currently selected term IDs.
   */
  private function buildToggleUrl(int $term_id, array $selected_ids): Url {
    $query = $this->getCurrentQuery();

    if (in_array($term_id, $selected_ids, TRUE)) {
      $selected_ids = array_values(array_diff($selected_ids, [$term_id]));
    }
    else {
      $selected_ids[] = $term_id;
    }

    if ($selected_ids === []) {
      unset($query[self::QUERY_KEY]);
    }
    else {
      $query[self::QUERY_KEY] = implode(self::QUERY_SEPARATOR, $this->filterQueryNormalizer->normalizeTermIds($selected_ids, 12));
    }

    return Url::fromRoute('<current>', [], ['query' => $this->filterQueryNormalizer->normalize($query)]);
  }

  /**
   * Builds a URL that clears all specific navigation selections.
   */
  private function buildClearUrl(): Url {
    $query = $this->getCurrentQuery();
    unset($query[self::QUERY_KEY]);

    return Url::fromRoute('<current>', [], ['query' => $this->filterQueryNormalizer->normalize($query)]);
  }

  /**
   * Reads selected term IDs from t=1~2 and t[]=1 style query values.
   *
   * @return int[]
   *   Selected term IDs.
   */
  private function getSelectedTermIds(): array {
    $request = $this->requestStack->getCurrentRequest();
    $value = $request?->query->all()[self::QUERY_KEY] ?? NULL;

    return $this->filterQueryNormalizer->normalizeTermIds($value, 12);
  }

  /**
   * Gets current query arguments.
   *
   * @return array<string, mixed>
   *   Query arguments.
   */
  private function getCurrentQuery(): array {
    $request = $this->requestStack->getCurrentRequest();

    return $request ? $this->filterQueryNormalizer->normalize($request->query->all()) : [];
  }

}
