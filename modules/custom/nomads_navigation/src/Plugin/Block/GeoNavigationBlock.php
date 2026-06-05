<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
 * Provides a hierarchical geo taxonomy navigation block for Views pages.
 */
#[Block(
  id: 'nomads_navigation_geo_navigation',
  admin_label: new TranslatableMarkup('Geo navigation'),
  category: new TranslatableMarkup('Nomads')
)]
final class GeoNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const VOCABULARY = 'cit_countries_information';
  private const VOCABULARY_LABEL = 'Geo location';
  private const VOCABULARY_FALLBACKS = [
    'geo_location',
    'geolocation',
    'geo',
  ];
  private const EXCLUDED_PARENT_IDS = [
    1278,
    1099,
  ];
  private const HIDE_CHILDREN_FOR_TERM_IDS = [
    1098,
  ];
  private const QUERY_KEY = 'geo';

  /**
   * Constructs a GeoNavigationBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
    $entity_type_manager = $container->get('entity_type.manager');

    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_type_manager->getStorage('taxonomy_term'),
      $container->get('request_stack'),
      $container->get('nomads_navigation.term_label_override_manager'),
      $container->get('nomads_navigation.filter_query_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'label' => $this->t('Geo navigation'),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $vid = $this->resolveVocabularyId();
    if ($vid === NULL) {
      return [];
    }

    $tree = $this->buildTree($vid);
    if ($tree === []) {
      return [];
    }

    $selected_id = $this->getSelectedGeoId();
    $selected_term = $selected_id ? $this->termStorage->load($selected_id) : NULL;
    $has_selected_term = $selected_term instanceof TermInterface && $selected_term->bundle() === $vid && $selected_term->isPublished();
    $label = $has_selected_term
      ? $this->labelOverrideManager->getLabel($selected_term)
      : $this->t('Region / Country');

    $cache_tags = ['taxonomy_term_list:' . $vid];
    foreach ($this->flattenTree($tree) as $term) {
      $cache_tags = Cache::mergeTags($cache_tags, $term['term']->getCacheTags());
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-geo-navigation'],
      ],
      'label' => $has_selected_term ? [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Region / Country'),
        '#attributes' => [
          'class' => ['nomads-geo-navigation__parent-label'],
        ],
      ] : [],
      'dropdown' => [
        '#type' => 'html_tag',
        '#tag' => 'details',
        '#attributes' => [
          'class' => array_filter([
            'nomads-geo-navigation__dropdown',
            $has_selected_term ? 'is-selected' : NULL,
          ]),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'summary',
          '#value' => Html::escape((string) $label),
          '#attributes' => [
            'class' => ['nomads-geo-navigation__pill'],
          ],
        ],
        'menu' => $this->buildLevelOneMenu($tree, $has_selected_term),
      ],
      '#attached' => [
        'library' => ['nomads_navigation/geo_navigation'],
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
  }

  /**
   * Builds first-level menu items.
   *
   * @param array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}>
   *   Hierarchical term tree.
   */
  private function buildLevelOneMenu(array $tree, bool $has_selected_term): array {
    $items = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-geo-navigation__menu', 'nomads-geo-navigation__menu--level-1'],
      ],
    ];

    foreach ($tree as $id => $item) {
      $items['term_' . $id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'nomads-geo-navigation__item',
            $item['children'] !== [] ? 'has-children' : '',
          ],
        ],
        'link' => $this->buildTermLink($item['term'], 'nomads-geo-navigation__link'),
      ];

      if ($item['children'] !== []) {
        $items['term_' . $id]['children'] = $this->buildLevelTwoMenu($item['children']);
      }
    }

    if ($has_selected_term) {
      $items['clear'] = [
        '#type' => 'link',
        '#title' => $this->t('-clear-'),
        '#url' => $this->buildClearUrl(),
        '#attributes' => [
          'class' => ['nomads-geo-navigation__link', 'nomads-geo-navigation__link--clear'],
        ],
      ];
    }

    return $items;
  }

  /**
   * Builds the horizontal second-level menu shown beside level one.
   *
   * @param array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}>
   *   Second-level term tree.
   */
  private function buildLevelTwoMenu(array $tree): array {
    $items = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-geo-navigation__menu', 'nomads-geo-navigation__menu--level-2'],
      ],
    ];

    foreach ($tree as $id => $item) {
      $items['term_' . $id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'nomads-geo-navigation__item',
            $item['children'] !== [] ? 'has-children' : '',
          ],
        ],
        'link' => $this->buildTermLink($item['term'], 'nomads-geo-navigation__link'),
      ];

      if ($item['children'] !== []) {
        $items['term_' . $id]['children'] = $this->buildLevelThreeMenu($item['children']);
      }
    }

    return $items;
  }

  /**
   * Builds the vertical third-level dropdown. Deeper terms are intentionally ignored.
   *
   * @param array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}>
   *   Third-level term tree.
   */
  private function buildLevelThreeMenu(array $tree): array {
    $items = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-geo-navigation__menu', 'nomads-geo-navigation__menu--level-3'],
      ],
    ];

    foreach ($tree as $id => $item) {
      $items['term_' . $id] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-geo-navigation__item'],
        ],
        'link' => $this->buildTermLink($item['term'], 'nomads-geo-navigation__link'),
      ];
    }

    return $items;
  }

  /**
   * Builds a term selection link that preserves existing query data.
   */
  private function buildTermLink(TermInterface $term, string $class): array {
    $query = $this->getCurrentQuery();
    $query = $this->filterQueryNormalizer->withValue($query, self::QUERY_KEY, (string) $term->id());

    return [
      '#type' => 'link',
      '#title' => $this->labelOverrideManager->getLabel($term),
      '#url' => Url::fromRoute('<current>', [], ['query' => $query]),
      '#attributes' => [
        'class' => [$class],
      ],
    ];
  }

  /**
   * Builds a URL that clears the selected geo term.
   */
  private function buildClearUrl(): Url {
    $query = $this->getCurrentQuery();
    $query = $this->filterQueryNormalizer->withoutValue($query, self::QUERY_KEY);

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Builds a published 3-level tree from the configured vocabulary.
   *
   * @return array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}>
   *   Terms keyed by term ID.
   */
  private function buildTree(string $vid): array {
    static $cache = [];
    if (isset($cache[$vid])) {
      return $cache[$vid];
    }

    $terms = $this->termStorage->loadTree($vid, 0, 3, TRUE);
    $tree = [];

    foreach ($terms as $term) {
      if (!$term instanceof TermInterface || !$term->isPublished()) {
        continue;
      }

      $parents = array_map('intval', $term->parents ?? []);
      $parent_id = $parents[0] ?? 0;
      $id = (int) $term->id();

      if ($parent_id === 0) {
        if (in_array($id, self::EXCLUDED_PARENT_IDS, TRUE)) {
          continue;
        }

        $tree[$id] = [
          'term' => $term,
          'children' => [],
        ];
        continue;
      }

      if (isset($tree[$parent_id])) {
        if (in_array($parent_id, self::HIDE_CHILDREN_FOR_TERM_IDS, TRUE)) {
          continue;
        }

        $tree[$parent_id]['children'][$id] = [
          'term' => $term,
          'children' => [],
        ];
        continue;
      }

      foreach ($tree as &$level_one) {
        if (isset($level_one['children'][$parent_id])) {
          if (in_array($parent_id, self::HIDE_CHILDREN_FOR_TERM_IDS, TRUE)) {
            break;
          }

          $level_one['children'][$parent_id]['children'][$id] = [
            'term' => $term,
            'children' => [],
          ];
          break;
        }
      }
      unset($level_one);
    }

    $cache[$vid] = $tree;
    return $cache[$vid];
  }

  /**
   * Resolves the Geo location vocabulary from active vocabulary entities.
   */
  private function resolveVocabularyId(): ?string {
    static $resolved_vid;
    if ($resolved_vid !== NULL) {
      return $resolved_vid ?: NULL;
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    if ($storage->load(self::VOCABULARY) !== NULL) {
      $resolved_vid = self::VOCABULARY;
      return $resolved_vid;
    }

    foreach ($storage->loadMultiple() as $vocabulary) {
      if ((string) $vocabulary->label() === self::VOCABULARY_LABEL) {
        $resolved_vid = (string) $vocabulary->id();
        return $resolved_vid;
      }
    }

    foreach (self::VOCABULARY_FALLBACKS as $vid) {
      if ($storage->load($vid) !== NULL) {
        $resolved_vid = $vid;
        return $resolved_vid;
      }
    }

    $resolved_vid = FALSE;
    return NULL;
  }

  /**
   * Reads the single selected geo term ID.
   */
  private function getSelectedGeoId(): ?int {
    $request = $this->requestStack->getCurrentRequest();
    $value = $request?->query->get(self::QUERY_KEY);
    $ids = $this->filterQueryNormalizer->normalizeTermIds($value, 1);

    return $ids[0] ?? NULL;
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
   * Flattens tree data for cache tag collection.
   *
   * @param array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}> $tree
   *   Tree data.
   *
   * @return array<int, array{term: \Drupal\taxonomy\TermInterface, children: array}>
   *   Flattened tree items.
   */
  private function flattenTree(array $tree): array {
    $items = [];
    foreach ($tree as $item) {
      $items[] = $item;
      if ($item['children'] !== []) {
        $items = array_merge($items, $this->flattenTree($item['children']));
      }
    }

    return $items;
  }

}
