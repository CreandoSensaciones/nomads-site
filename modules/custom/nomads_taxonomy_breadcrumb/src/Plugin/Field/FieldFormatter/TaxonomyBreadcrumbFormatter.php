<?php

namespace Drupal\nomads_taxonomy_breadcrumb\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter for taxonomy term references rendered as breadcrumbs.
 */
#[FieldFormatter(
  id: 'nomads_taxonomy_breadcrumb',
  label: new TranslatableMarkup('Taxonomy breadcrumb'),
  field_types: [
    'entity_reference',
  ],
)]
class TaxonomyBreadcrumbFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a formatter instance.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'link_term' => FALSE,
      'link_parents' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['link_term'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link term to term page'),
      '#default_value' => (bool) $this->getSetting('link_term'),
    ];

    $element['link_parents'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link parents to term page'),
      '#default_value' => (bool) $this->getSetting('link_parents'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Link term to term page: @value', [
        '@value' => $this->getSetting('link_term') ? $this->t('Yes') : $this->t('No'),
      ]),
      $this->t('Link parents to term page: @value', [
        '@value' => $this->getSetting('link_parents') ? $this->t('Yes') : $this->t('No'),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }

    $link_term = (bool) $this->getSetting('link_term');
    $link_parents = (bool) $this->getSetting('link_parents');

    $elements = [];
    foreach ($items as $delta => $item) {
      $term = $this->resolveTerm($item);
      if (!$term) {
        continue;
      }

      $parents = $this->getEligibleParents($term);
      $breadcrumb_terms = array_merge($parents, [$term]);
      $elements[$delta] = $this->buildBreadcrumbElement($breadcrumb_terms, $link_term, $link_parents);
    }

    return $elements;
  }

  /**
   * Resolve taxonomy term from an item.
   */
  protected function resolveTerm(mixed $item): ?TermInterface {
    if (!empty($item->entity) && $item->entity instanceof TermInterface) {
      return $item->entity;
    }

    $target_id = (int) ($item->target_id ?? 0);
    if ($target_id <= 0) {
      return NULL;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($target_id);
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Build a breadcrumb render array.
   */
  protected function buildBreadcrumbElement(array $breadcrumb_terms, bool $link_term, bool $link_parents): array {
    $element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-taxonomy-breadcrumb'],
      ],
    ];

    $cache_tags = [];
    $last_index = count($breadcrumb_terms) - 1;
    foreach ($breadcrumb_terms as $index => $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $cache_tags = array_merge($cache_tags, $term->getCacheTags());
      $is_last = $index === $last_index;
      $should_link = $is_last ? $link_term : $link_parents;

      $crumb_key = 'crumb_' . $index;
      if ($should_link) {
        $element[$crumb_key] = [
          '#type' => 'link',
          '#title' => $term->label(),
          '#url' => $term->toUrl(),
          '#attributes' => [
            'class' => ['nomads-taxonomy-breadcrumb__link'],
          ],
        ];
      }
      else {
        $element[$crumb_key] = [
          '#plain_text' => $term->label(),
        ];
      }

      if (!$is_last) {
        $element['separator_' . $index] = [
          '#plain_text' => ' > ',
        ];
      }
    }

    if ($cache_tags !== []) {
      $element['#cache']['tags'] = array_values(array_unique($cache_tags));
    }

    return $element;
  }

  /**
   * Get parent terms marked with tax_breadcrumb in hierarchy order.
   */
  protected function getEligibleParents(TermInterface $term): array {
    $parents = [];
    $visited = [(int) $term->id() => TRUE];
    $current = $term;

    while ($current->hasField('parent') && !$current->get('parent')->isEmpty()) {
      $referenced = $current->get('parent')->referencedEntities();
      $parent = reset($referenced);
      if (!$parent instanceof TermInterface) {
        break;
      }

      $parent_id = (int) $parent->id();
      if (isset($visited[$parent_id])) {
        break;
      }
      $visited[$parent_id] = TRUE;

      $parents[] = $parent;
      $current = $parent;
    }

    $parents = array_reverse($parents);
    $eligible = [];
    foreach ($parents as $parent) {
      if ($this->hasTaxBreadcrumbSetting($parent)) {
        $eligible[] = $parent;
      }
    }

    return $eligible;
  }

  /**
   * Determine whether a term has the "tax_breadcrumb" setting.
   */
  protected function hasTaxBreadcrumbSetting(TermInterface $term): bool {
    foreach (['field_setting', 'field_settings'] as $field_name) {
      if (!$term->hasField($field_name) || $term->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($term->get($field_name)->getValue() as $item) {
        if (!isset($item['value'])) {
          continue;
        }

        if ($this->normalizeSettingMachineName((string) $item['value']) === 'tax_breadcrumb') {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Normalize setting machine name for stable comparisons.
   */
  protected function normalizeSettingMachineName(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace([' ', '-'], '_', $value);
    return preg_replace('/_+/', '_', $value) ?? '';
  }

}
