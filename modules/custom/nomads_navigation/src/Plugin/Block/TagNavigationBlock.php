<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\nomads_navigation\FilterQueryNormalizer;
use Drupal\nomads_navigation\TermLabelOverrideManager;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a tag branch navigation block for Views pages.
 */
#[Block(
  id: 'nomads_navigation_tag_navigation',
  admin_label: new TranslatableMarkup('Tag navigation'),
  category: new TranslatableMarkup('Nomads')
)]
final class TagNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const VOCABULARY = 't';
  private const QUERY_KEY = 'tags';
  private const QUERY_SEPARATOR = '~';

  /**
   * Constructs a TagNavigationBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
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
    $branches = $this->loadBranches();
    if ($branches === []) {
      return [];
    }

    $selected_ids = $this->getSelectedTagIds();
    $items = [];
    $cache_tags = ['taxonomy_term_list:' . self::VOCABULARY];

    foreach ($branches as $parent_id => $branch) {
      $parent = $branch['parent'];
      $children = $branch['children'];
      $child_ids = array_keys($children);
      $selected_child_id = $this->findSelectedChildId($selected_ids, $child_ids);
      $selected_label = $selected_child_id !== NULL
        ? $this->labelOverrideManager->getLabel($children[$selected_child_id])
        : $this->labelOverrideManager->getLabel($parent);
      $pill_tooltip = $selected_child_id !== NULL
        ? $this->getTermTooltip($children[$selected_child_id])
        : $this->getTermTooltip($parent);

      $cache_tags = Cache::mergeTags($cache_tags, $parent->getCacheTags());
      foreach ($children as $child) {
        $cache_tags = Cache::mergeTags($cache_tags, $child->getCacheTags());
      }

      $links = [];
      foreach ($children as $child_id => $child) {
        $classes = ['nomads-tag-navigation__option'];
        if ($selected_child_id === (int) $child_id) {
          $classes[] = 'is-active';
        }

        $links[] = [
          '#type' => 'link',
          '#title' => $this->labelOverrideManager->getLabel($child),
          '#url' => $this->buildSelectionUrl((int) $child_id, $parent_id, $child_ids),
          '#attributes' => [
            'class' => $classes,
            'title' => $this->getTermTooltip($child),
          ],
        ];
      }

      if ($selected_child_id !== NULL) {
        $links[] = [
          '#type' => 'link',
          '#title' => $this->t('-clear-'),
          '#url' => $this->buildClearUrl($parent_id, $child_ids),
          '#attributes' => [
            'class' => ['nomads-tag-navigation__option', 'nomads-tag-navigation__option--clear'],
          ],
        ];
      }

      $item = [
        '#type' => 'container',
        '#attributes' => [
          'class' => array_filter([
            'nomads-tag-navigation__branch',
            $selected_child_id !== NULL ? 'is-selected' : NULL,
          ]),
          'data-tag-parent' => (string) $parent_id,
        ],
        'dropdown' => [
          '#type' => 'html_tag',
          '#tag' => 'details',
          '#attributes' => [
            'class' => ['nomads-tag-navigation__dropdown'],
          ],
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'summary',
            '#value' => Html::escape($selected_label),
            '#attributes' => [
              'class' => ['nomads-tag-navigation__pill'],
              'title' => $pill_tooltip,
            ],
          ],
          'options' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['nomads-tag-navigation__menu'],
            ],
            'links' => $links,
          ],
        ],
      ];

      if ($selected_child_id !== NULL) {
        $item['label'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => Html::escape($this->labelOverrideManager->getLabel($parent)),
          '#attributes' => [
            'class' => ['nomads-tag-navigation__parent-label'],
          ],
          '#weight' => -10,
        ];
      }

      $items[] = $item;
    }

    $clear_item = NULL;
    if ($selected_ids !== []) {
      $clear_item = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-tag-navigation__branch', 'nomads-tag-navigation__branch--clear-all'],
        ],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Clear'),
          '#url' => $this->buildClearAllUrl(),
          '#attributes' => [
            'class' => ['nomads-tag-navigation__clear-all'],
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-tag-navigation'],
      ],
      '#attached' => [
        'library' => ['nomads_navigation/tag_navigation'],
      ],
      '#cache' => [
        'contexts' => [
          'route',
          'url.path',
          ...FilterQueryNormalizer::CACHE_CONTEXTS,
          'url.site',
          'domain',
        ],
        'tags' => Cache::mergeTags($cache_tags, $this->labelOverrideManager->getCacheTags()),
      ],
    ];

    foreach (array_chunk($items, 6) as $set_delta => $set_items) {
      $set = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'nomads-tag-navigation__set',
            'set' . ($set_delta + 1),
          ],
        ],
      ];

      foreach ($set_items as $item_delta => $item) {
        $set[$item_delta] = $item;
      }

      $build['set_' . ($set_delta + 1)] = $set;
    }

    if ($clear_item !== NULL) {
      $build['clear'] = $clear_item;
    }

    return $build;
  }

  /**
   * Loads top-level tag terms and their direct children.
   *
   * @return array<int, array{parent: \Drupal\taxonomy\TermInterface, children: array<int, \Drupal\taxonomy\TermInterface>}>
   *   Branches keyed by top-level term ID.
   */
  private function loadBranches(): array {
    static $cache = [];
    if (isset($cache[self::VOCABULARY])) {
      return $cache[self::VOCABULARY];
    }

    $tree = $this->termStorage->loadTree(self::VOCABULARY, 0, 2, TRUE);
    $branches = [];

    foreach ($tree as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $parents = $this->termStorage->loadParents((int) $term->id());
      if ($parents === []) {
        if (!$term->isPublished()) {
          continue;
        }

        $branches[(int) $term->id()] = [
          'parent' => $term,
          'children' => [],
        ];
        continue;
      }

      $parent_id = (int) array_key_first($parents);
      if (isset($branches[$parent_id]) && $term->isPublished()) {
        $branches[$parent_id]['children'][(int) $term->id()] = $term;
      }
    }

    $cache[self::VOCABULARY] = array_filter($branches, static fn (array $branch): bool => $branch['children'] !== []);
    return $cache[self::VOCABULARY];
  }

  /**
   * Reads selected tag IDs from tags=1~2 and tags[]=1 style query values.
   *
   * @return int[]
   *   Selected term IDs.
   */
  private function getSelectedTagIds(): array {
    $request = $this->requestStack->getCurrentRequest();
    $value = $request?->query->all()[self::QUERY_KEY] ?? NULL;

    return $this->filterQueryNormalizer->normalizeTermIds($value, 6);
  }

  /**
   * Builds a URL that replaces any selected sibling in this branch.
   *
   * @param int $term_id
   *   Selected child term ID.
   * @param int $parent_id
   *   Top-level parent term ID.
   * @param int[] $branch_child_ids
   *   Direct child term IDs for this top-level branch.
   */
  private function buildSelectionUrl(int $term_id, int $parent_id, array $branch_child_ids): Url {
    $query = $this->getCurrentQuery();
    $selected_ids = $this->filterQueryNormalizer->normalizeTermIds($query[self::QUERY_KEY] ?? NULL, 6);
    $branch_ids = array_merge([$parent_id], $branch_child_ids);
    $selected_ids = array_values(array_diff($selected_ids, $branch_ids));
    $selected_ids[] = $term_id;
    $query[self::QUERY_KEY] = implode(self::QUERY_SEPARATOR, $this->filterQueryNormalizer->normalizeTermIds($selected_ids, 6));

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Builds a URL that clears any selected term from this branch.
   *
   * @param int $parent_id
   *   Top-level parent term ID.
   * @param int[] $branch_child_ids
   *   Direct child term IDs for this top-level branch.
   */
  private function buildClearUrl(int $parent_id, array $branch_child_ids): Url {
    $query = $this->getCurrentQuery();
    $selected_ids = $this->filterQueryNormalizer->normalizeTermIds($query[self::QUERY_KEY] ?? NULL, 6);
    $branch_ids = array_merge([$parent_id], $branch_child_ids);
    $selected_ids = array_values(array_diff($selected_ids, $branch_ids));

    if ($selected_ids === []) {
      unset($query[self::QUERY_KEY]);
    }
    else {
      $query[self::QUERY_KEY] = implode(self::QUERY_SEPARATOR, $this->filterQueryNormalizer->normalizeTermIds($selected_ids, 6));
    }

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Builds a URL that clears all tag navigation selections.
   */
  private function buildClearAllUrl(): Url {
    $query = $this->getCurrentQuery();
    unset($query[self::QUERY_KEY]);

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Gets the current query string while preserving unrelated parameters.
   *
   * @return array<string, mixed>
   *   Current query values.
   */
  private function getCurrentQuery(): array {
    $request = $this->requestStack->getCurrentRequest();

    return $request ? $this->filterQueryNormalizer->normalize($request->query->all()) : [];
  }

  /**
   * Finds the selected child term for a branch.
   *
   * @param int[] $selected_ids
   *   Current selected term IDs.
   * @param int[] $child_ids
   *   Direct child IDs in one top-level branch.
   */
  private function findSelectedChildId(array $selected_ids, array $child_ids): ?int {
    foreach ($selected_ids as $selected_id) {
      if (in_array($selected_id, $child_ids, TRUE)) {
        return $selected_id;
      }
    }

    return NULL;
  }

  /**
   * Gets optional tooltip text from a taxonomy term.
   */
  private function getTermTooltip(TermInterface $term): string {
    if (!$term->hasField('field_tooltip') || $term->get('field_tooltip')->isEmpty()) {
      return '';
    }

    return trim(strip_tags((string) $term->get('field_tooltip')->value));
  }

}
