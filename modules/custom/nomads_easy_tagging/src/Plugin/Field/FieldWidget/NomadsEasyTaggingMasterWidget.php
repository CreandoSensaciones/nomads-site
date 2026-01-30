<?php

namespace Drupal\nomads_easy_tagging\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\nomads_easy_tagging\Service\NomadsEasyTaggingConstraintResolver;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Plugin implementation of the easy tagging widget.
 */
#[FieldWidget(
  id: 'nomads_easy_tagging_master',
  label: new TranslatableMarkup('Easy Tagging'),
  description: new TranslatableMarkup('Grid-based taxonomy selector with constraints.'),
  field_types: ['entity_reference'],
)]
class NomadsEasyTaggingMasterWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The constraint resolver service.
   */
  protected NomadsEasyTaggingConstraintResolver $resolver;

  /**
   * Constructs the widget.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    $field_definition,
    array $settings,
    array $third_party_settings,
    NomadsEasyTaggingConstraintResolver $resolver,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->resolver = $resolver;
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
      $configuration['third_party_settings'],
      $container->get('nomads_easy_tagging.constraint_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function handlesMultipleValues(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $target_type = $this->getFieldSetting('target_type');
    if ($target_type !== 'taxonomy_term') {
      $element['message'] = [
        '#type' => 'item',
        '#markup' => $this->t('Easy Tagging supports taxonomy term references only.'),
      ];
      return $element;
    }

    $selected_ids = [];
    foreach ($items as $item) {
      if (!empty($item->target_id)) {
        $selected_ids[] = (int) $item->target_id;
      }
    }

    $parents = array_merge($form['#parents'], [$this->fieldDefinition->getName()]);
    $name_prefix = $this->buildNamePrefix($parents);

    $element['#attributes']['data-name-prefix'] = $name_prefix;

    $vocabulary_ids = $this->getTargetVocabularyIds();
    $constraints_url = $this->buildConstraintsUrl();
    $children_url = $this->buildChildrenUrl();

    $type_field_info = $this->getTypeFieldInfo($items);

    $element['ui'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-easy-tagging'],
        'data-constraints-url' => $constraints_url,
        'data-children-url' => $children_url,
        'data-unified-vids' => implode(',', $vocabulary_ids),
        'data-type-field-name' => $type_field_info['field_name'],
        'data-type-vids' => implode(',', $type_field_info['vids']),
      ],
      '#attached' => [
        'library' => ['nomads_easy_tagging/widget'],
      ],
    ];

    $element['ui']['sections'] = $this->buildSections($vocabulary_ids, $selected_ids);

    $element['ui']['selected_values'] = [
      '#type' => 'hidden',
      '#value' => implode("\n", $selected_ids),
      '#parents' => array_merge($parents, ['_net_values']),
      '#attributes' => [
        'data-selected-values' => '1',
      ],
    ];

    $element['#element_validate'][] = [static::class, 'validateEasyTagging'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getUserInput(), $path, $key_exists);

    if ($key_exists) {
      $values = self::normalizeInputValues($values);
      $values = $this->massageFormValues($values, $form, $form_state);
      $items->setValue($values);
      $items->filterEmptyItems();

      $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
      foreach ($items as $delta => $item) {
        $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
        unset($item->_original_delta, $item->_weight, $item->_actions);
      }
      static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
    }
  }

  /**
   * Element validation handler for raw user input.
   */
  public static function validateEasyTagging(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getUserInput(), $element['#parents'], $key_exists);
    if (!$key_exists) {
      return;
    }

    $values = self::normalizeInputValues($values);
    $form_state->setValueForElement($element, $values);
  }

  /**
   * Normalize raw user input into ordered field values.
   */
  protected static function normalizeInputValues(array $values): array {
    $selected = [];
    if (isset($values['_net_values']) && is_string($values['_net_values'])) {
      $selected = array_values(array_filter(array_map('trim', explode("\n", $values['_net_values']))));
    }

    $normalized = [];
    foreach ($selected as $term_id) {
      if (is_numeric($term_id)) {
        $normalized[] = ['target_id' => (int) $term_id];
      }
    }

    return $normalized;
  }

  /**
   * Build sections for each top-level branch.
   */
  protected function buildSections(array $vocabulary_ids, array $selected_ids): array {
    $elements = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-easy-tagging__sections'],
      ],
    ];

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    foreach ($vocabulary_ids as $vid) {
      $top_level_terms = $term_storage->loadTree($vid, 0, 1, TRUE);
      foreach ($top_level_terms as $branch_term) {
        if (!$branch_term instanceof TermInterface) {
          continue;
        }

        $branch_label = (string) $branch_term->label();
        $is_category = $this->isCategoryBranch($branch_label);

        $children_payload = $this->resolver->getChildren((int) $branch_term->id());
        $cards_elements = $this->buildCardsElements(
          $children_payload['children'],
          $selected_ids,
          $is_category,
          (int) $branch_term->id()
        );

        $section_key = 'branch_' . $branch_term->id();
        $elements[$section_key] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-easy-tagging__section'],
            'data-branch-tid' => (string) $branch_term->id(),
            'data-branch-type' => $is_category ? 'category' : 'default',
          ],
        ];

        $elements[$section_key]['heading'] = [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => Html::escape($branch_label),
          '#attributes' => [
            'class' => ['nomads-easy-tagging__heading'],
          ],
        ];

        $elements[$section_key]['back'] = [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Back'),
          '#attributes' => [
            'class' => ['nomads-easy-tagging__back'],
            'type' => 'button',
            'data-back' => '1',
            'style' => 'display: none;',
          ],
        ];

        $elements[$section_key]['cards'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-easy-tagging__cards'],
            'data-parent-tid' => (string) $branch_term->id(),
            'data-parent-limit' => (string) ($children_payload['parent_children_limit'] ?? ''),
            'data-root-items' => Json::encode($children_payload['children']),
            'data-view' => 'root',
          ],
        ] + $cards_elements;
      }
    }

    return $elements;
  }

  /**
   * Build markup for cards.
   */
  protected function buildCardsElements(array $children, array $selected_ids, bool $is_category_root, int $parent_tid): array {
    $selected_lookup = array_flip($selected_ids);
    $elements = [];

    foreach ($children as $child) {
      $term_id = (int) ($child['tid'] ?? 0);
      if (!$term_id) {
        continue;
      }

      $classes = ['nomads-easy-tagging__card'];
      if (isset($selected_lookup[$term_id])) {
        $classes[] = 'is-selected';
      }
      if ($is_category_root) {
        $classes[] = 'is-category-root-card';
      }

      $label = (string) ($child['label'] ?? '');
      $explainer_html = (string) ($child['ui_explainer_html'] ?? '');
      if ($explainer_html === '') {
        $explainer_raw = (string) ($child['ui_explainer'] ?? '');
        $explainer_html = $explainer_raw !== '' ? Xss::filter($explainer_raw, ['p', 'br', 'strong', 'em', 'a']) : '';
      }

      $card = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => [
          'class' => $classes,
          'type' => 'button',
          'data-tid' => (string) $term_id,
          'data-parent-tid' => (string) $parent_tid,
          'data-has-children' => !empty($child['has_children']) ? '1' : '0',
          'data-limit' => isset($child['children_limit']) && $child['children_limit'] !== NULL ? (string) $child['children_limit'] : '',
          'data-category-root' => $is_category_root ? '1' : '0',
        ],
      ];

      $card['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $label,
        '#attributes' => [
          'class' => ['nomads-easy-tagging__card-label'],
        ],
      ];

      if ($explainer_html !== '') {
        $card['explainer'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => Markup::create($explainer_html),
          '#attributes' => [
            'class' => ['nomads-easy-tagging__card-explainer'],
          ],
        ];
      }

      $elements[] = $card;
    }

    return $elements;
  }

  /**
   * Identify the category branch by label.
   */
  protected function isCategoryBranch(string $label): bool {
    return strtolower(trim($label)) === 'category';
  }

  /**
   * Build the constraints endpoint URL.
   */
  protected function buildConstraintsUrl(): string {
    return Url::fromRoute('nomads_easy_tagging.constraints')->toString();
  }

  /**
   * Build the children endpoint URL.
   */
  protected function buildChildrenUrl(): string {
    return Url::fromRoute('nomads_easy_tagging.children', ['parent_tid' => 0])->toString();
  }

  /**
   * Get target vocabulary IDs.
   */
  protected function getTargetVocabularyIds(): array {
    $handler_settings = $this->getFieldSetting('handler_settings') ?? [];
    $target_bundles = $handler_settings['target_bundles'] ?? [];

    if (!empty($target_bundles)) {
      return array_values($target_bundles);
    }

    $vocabulary_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    return array_keys($vocabulary_storage->loadMultiple());
  }

  /**
   * Get info about the type field on the same entity.
   */
  protected function getTypeFieldInfo(FieldItemListInterface $items): array {
    $entity = $items->getEntity();
    $field_name = '';
    $vids = [];

    if ($entity && $entity->hasField('field_type')) {
      $field_name = 'field_type';
      $definition = $entity->getFieldDefinition('field_type');
      if ($definition) {
        $handler_settings = $definition->getSetting('handler_settings') ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];
        $vids = array_values($target_bundles);
      }
    }

    return [
      'field_name' => $field_name,
      'vids' => $vids,
    ];
  }

  /**
   * Build the name prefix for generated inputs.
   */
  protected function buildNamePrefix(array $parents): string {
    $name = array_shift($parents) ?? '';
    foreach ($parents as $parent) {
      $name .= '[' . $parent . ']';
    }
    return $name;
  }

}
