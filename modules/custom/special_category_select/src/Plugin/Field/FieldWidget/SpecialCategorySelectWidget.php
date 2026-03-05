<?php

namespace Drupal\special_category_select\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'special_category_select' widget.
 */
#[FieldWidget(
  id: 'special_category_select',
  label: new TranslatableMarkup('Special Category select'),
  description: new TranslatableMarkup('Tree selector with ordered selection.'),
  field_types: ['entity_reference'],
)]
class SpecialCategorySelectWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'leaves_only' => FALSE,
      'sortable' => TRUE,
      'tree_column_label' => '',
      'selected_column_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = [];

    $elements['leaves_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Leaves only'),
      '#default_value' => $this->getSetting('leaves_only'),
      '#description' => $this->t('Allow selecting only terms without children.'),
    ];
    $elements['sortable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sortable selected list'),
      '#default_value' => $this->getSetting('sortable'),
      '#description' => $this->t('Allow drag-and-drop ordering in the selected column.'),
    ];
    $elements['tree_column_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tree column label'),
      '#default_value' => $this->getSetting('tree_column_label'),
      '#description' => $this->t('Overrides the label above the category tree column. Leave empty to use the vocabulary label.'),
    ];
    $elements['selected_column_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selected column label'),
      '#default_value' => $this->getSetting('selected_column_label'),
      '#description' => $this->t('Overrides the label above the selected list column.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('Leaves only: @value', [
      '@value' => $this->getSetting('leaves_only') ? $this->t('Yes') : $this->t('No'),
    ]);
    $summary[] = $this->t('Sortable: @value', [
      '@value' => $this->getSetting('sortable') ? $this->t('Yes') : $this->t('No'),
    ]);
    if ($this->getSetting('tree_column_label') !== '') {
      $summary[] = $this->t('Tree column label: @value', [
        '@value' => $this->getSetting('tree_column_label'),
      ]);
    }
    if ($this->getSetting('selected_column_label') !== '') {
      $summary[] = $this->t('Selected column label: @value', [
        '@value' => $this->getSetting('selected_column_label'),
      ]);
    }
    return $summary;
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
        '#markup' => $this->t('Special Category select supports taxonomy term references only.'),
      ];
      return $element;
    }

    $parents = array_merge($form['#parents'], [$this->fieldDefinition->getName()]);
    $name_prefix = $this->buildNamePrefix($parents);
    $element['#name'] = $name_prefix . '[_cf_values]';
    $element['#attributes']['name'] = $name_prefix . '[_cf_values]';
    $element['#attributes']['data-name-prefix'] = $name_prefix;

    $selected_ids = [];
    foreach ($items as $item) {
      if (!empty($item->target_id)) {
        $selected_ids[] = (int) $item->target_id;
      }
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $selected_terms = $selected_ids ? $term_storage->loadMultiple($selected_ids) : [];
    $ordered_selected_terms = [];
    foreach ($selected_ids as $selected_id) {
      if (isset($selected_terms[$selected_id])) {
        $ordered_selected_terms[] = $selected_terms[$selected_id];
      }
    }

    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $max_selection = $cardinality > 0 ? (string) $cardinality : '';

    $is_required = $this->fieldDefinition->isRequired();
    if ($is_required) {
      $element['#required'] = TRUE;
      $element['#attributes']['class'][] = 'form-required';
    }

    $ui_title = $element['#title'] ?? $this->fieldDefinition->getLabel() ?? $this->t('Categories');
    $element['ui_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $ui_title,
      '#attributes' => ['class' => ['special-category-select__title']],
    ];
    if ($is_required) {
      $element['ui_title']['#attributes']['class'][] = 'form-required';
    }

    $element['ui'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['special-category-select'],
        'data-max-selection' => $max_selection,
        'data-sortable' => $this->getSetting('sortable') ? '1' : '0',
      ],
      '#attached' => [
        'library' => ['special_category_select/widget'],
      ],
    ];

    $element['ui']['tree'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['special-category-select__tree'],
      ],
    ];
    $tree_column_label = $this->getSetting('tree_column_label');
    if ($tree_column_label !== '') {
      $element['ui']['tree']['column_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $tree_column_label,
        '#attributes' => ['class' => ['special-category-select__tree-title']],
      ];
    }
    $vocabulary_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    $vocabulary_ids = $this->getTargetVocabularyIds();
    $vocabularies = $vocabulary_storage->loadMultiple($vocabulary_ids);
    $show_vocab_labels = ($tree_column_label === '');

    foreach ($vocabulary_ids as $vid) {
      if ($show_vocab_labels && isset($vocabularies[$vid])) {
        $element['ui']['tree']['vocab_' . $vid] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $vocabularies[$vid]->label(),
          '#attributes' => ['class' => ['special-category-select__tree-title']],
        ];
      }

      $element['ui']['tree']['list_' . $vid] = [
        '#type' => 'markup',
        '#markup' => $this->buildTreeMarkup($vid, $selected_ids),
      ];
    }

    $element['ui']['selected'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['special-category-select__selected'],
      ],
    ];
    $selected_column_label = $this->getSetting('selected_column_label');
    if ($selected_column_label === '') {
      $selected_column_label = (string) $this->t('Selected Categories');
    }
    $element['ui']['selected']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $selected_column_label,
      '#attributes' => ['class' => ['special-category-select__selected-title']],
    ];
    $element['ui']['selected']['list'] = [
      '#type' => 'markup',
      '#markup' => $this->buildSelectedListMarkup($ordered_selected_terms),
    ];

    $element['ui']['term_inputs'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['special-category-select__inputs'],
        'data-inputs' => '1',
        'data-name-prefix' => $name_prefix,
      ],
    ];

    $element['ui']['term_inputs']['cf_values'] = [
      '#type' => 'hidden',
      '#value' => implode("\n", $selected_ids),
      '#parents' => array_merge($parents, ['_cf_values']),
      '#attributes' => [
        'data-cf-values' => '1',
      ],
    ];

    foreach ($selected_ids as $index => $term_id) {
      $element['ui']['term_inputs'][$index] = [
        '#type' => 'hidden',
        '#value' => $term_id,
        '#parents' => array_merge($parents, [$index, 'target_id']),
        '#attributes' => [
          'data-term-id' => (string) $term_id,
        ],
      ];
    }

    $element['#element_validate'][] = [static::class, 'validateSpecialCategorySelect'];

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

    if (!$key_exists) {
      $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);
    }
    if (!$key_exists) {
      return;
    }

    $values = is_array($values) ? $values : [];
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

  /**
   * Element validation handler for raw user input.
   */
  public static function validateSpecialCategorySelect(array &$element, FormStateInterface $form_state, array &$complete_form): void {
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
    $order = [];
    if (isset($values['_order']) && is_string($values['_order'])) {
      $order = array_values(array_filter(array_map('trim', explode(',', $values['_order']))));
    }

    $value_map = [];
    foreach ($values as $value) {
      if (is_array($value) && isset($value['target_id']) && $value['target_id'] !== '') {
        $target_id = (int) $value['target_id'];
        if ($target_id > 0) {
          $value_map[(string) $target_id] = ['target_id' => $target_id];
        }
      }
    }

    // Fallback for degraded JS submissions where target_id inputs are missing:
    // recover selected terms from the helper _cf_values payload.
    if (empty($value_map) && isset($values['_cf_values']) && is_string($values['_cf_values'])) {
      $raw_tokens = preg_split('/[\s,]+/', $values['_cf_values']) ?: [];
      foreach ($raw_tokens as $token) {
        $token = trim((string) $token);
        if ($token === '' || !ctype_digit($token)) {
          continue;
        }
        $target_id = (int) $token;
        if ($target_id <= 0) {
          continue;
        }
        $value_map[(string) $target_id] = ['target_id' => $target_id];
      }
    }

    if (!empty($order)) {
      $ordered_values = [];
      foreach ($order as $term_id) {
        if (isset($value_map[$term_id])) {
          $ordered_values[] = $value_map[$term_id];
          unset($value_map[$term_id]);
        }
      }
      foreach ($value_map as $remaining) {
        $ordered_values[] = $remaining;
      }
      return $ordered_values;
    }

    return array_values($value_map);
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
   * Build markup for the taxonomy tree.
   */
  protected function buildTreeMarkup(string $vid, array $selected_ids): string {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $term_storage->loadTree($vid, 0, NULL, TRUE);
    if (!$terms) {
      return '';
    }

    $children_map = [];
    $term_labels = [];
    foreach ($terms as $term) {
      $term_labels[(int) $term->id()] = (string) $term->label();
      foreach ($term->get('parent')->getValue() as $parent) {
        $parent_id = (int) $parent['target_id'];
        if ($parent_id) {
          $children_map[$parent_id] = TRUE;
        }
      }
    }

    $leaves_only = (bool) $this->getSetting('leaves_only');
    $selected_lookup = array_flip($selected_ids);

    $markup = '<ul class="special-category-select__tree-list term-reference-tree-level">';
    $prev_depth = 0;
    $first = TRUE;

    foreach ($terms as $term) {
      $depth = (int) $term->depth;

      while ($depth > $prev_depth) {
        $markup .= '<ul class="special-category-select__tree-children term-reference-tree-level" style="display: none;">';
        $prev_depth++;
      }
      while ($depth < $prev_depth) {
        $markup .= '</li></ul>';
        $prev_depth--;
      }
      if (!$first) {
        $markup .= '</li>';
      }
      $first = FALSE;

      $term_id = (int) $term->id();
      $label = Html::escape($term->label());
      $tooltip = '';
      if ($term->hasField('field_tooltip')) {
        $tooltip = trim((string) $term->get('field_tooltip')->value);
      }
      $has_children = !empty($children_map[$term_id]);
      $selectable = !$leaves_only || !$has_children;
      $parent_label = '';
      foreach ($term->get('parent')->getValue() as $parent) {
        $parent_id = (int) $parent['target_id'];
        if ($parent_id && isset($term_labels[$parent_id])) {
          $parent_label = $term_labels[$parent_id];
          break;
        }
      }

      $classes = ['special-category-select__tree-item'];
      if ($has_children) {
        $classes[] = 'has-children';
      }
      if (!$selectable) {
        $classes[] = 'is-disabled';
      }
      if (isset($selected_lookup[$term_id])) {
        $classes[] = 'is-selected';
      }

      $markup .= '<li class="' . implode(' ', $classes) . '">';
      if ($has_children) {
        $markup .= '<span class="term-reference-tree-button term-reference-tree-collapsed special-category-select__tree-toggle" role="button" tabindex="0" aria-expanded="false" aria-label="' . $this->t('Toggle children')->render() . '"></span>';
      }
      elseif (!$leaves_only) {
        $markup .= '<span class="no-term-reference-tree-button"></span>';
      }
      if ($selectable) {
        $tooltip_attr = $tooltip !== '' ? ' data-tooltip="' . Html::escape($tooltip) . '"' : '';
        $markup .= '<a href="#" class="special-category-select__tree-link" data-term-id="' . $term_id . '"' . $tooltip_attr . '>' . $label . '</a>';
      }
      else {
        $tooltip_attr = $tooltip !== '' ? ' data-tooltip="' . Html::escape($tooltip) . '"' : '';
        $markup .= '<span class="special-category-select__tree-label"' . $tooltip_attr . '>' . $label . '</span>';
      }
    }

    if (!$first) {
      $markup .= '</li>';
    }
    while ($prev_depth > 0) {
      $markup .= '</ul>';
      $prev_depth--;
    }
    $markup .= '</ul>';

    return Markup::create($markup);
  }

  /**
   * Build markup for the selected list.
   */
  protected function buildSelectedListMarkup(array $selected_terms): string {
    $markup = '<ol class="special-category-select__selected-list" data-selected-list="1">';
    $sortable = (bool) $this->getSetting('sortable');

    foreach ($selected_terms as $index => $term) {
      $term_id = (int) $term->id();
      $label = Html::escape($term->label());
      $tooltip = '';
      if ($term->hasField('field_tooltip')) {
        $tooltip = trim((string) $term->get('field_tooltip')->value);
      }

      $markup .= '<li class="special-category-select__selected-item" data-term-id="' . $term_id . '">';
      if ($sortable) {
        $markup .= '<span class="special-category-select__handle"></span>';
      }
      $markup .= '<span class="special-category-select__selected-label">' . $label . '</span>';
      if ($tooltip !== '') {
        $markup .= '<span class="special-category-select__selected-parent">' . Html::escape($tooltip) . '</span>';
      }
      $markup .= '<button type="button" class="special-category-select__remove" aria-label="' . $this->t('Remove term')->render() . '"><span class="special-category-select__remove-icon" aria-hidden="true"></span></button>';
      $markup .= '</li>';
    }

    $markup .= '</ol>';
    return Markup::create($markup);
  }

}
