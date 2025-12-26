<?php

namespace Drupal\special_category_select\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
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

    $element['ui'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['special-category-select'],
        'data-max-selection' => $max_selection,
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
    $element['ui']['tree']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Categories'),
      '#attributes' => ['class' => ['special-category-select__tree-title']],
    ];

    $vocabulary_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
    $vocabulary_ids = $this->getTargetVocabularyIds();
    $vocabularies = $vocabulary_storage->loadMultiple($vocabulary_ids);

    foreach ($vocabulary_ids as $vid) {
      if (isset($vocabularies[$vid])) {
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
    $element['ui']['selected']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Selected order'),
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

    return $element;
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
        $parent_attr = $parent_label !== '' ? ' data-parent-label="' . Html::escape($parent_label) . '"' : '';
        $tooltip_attr = $tooltip !== '' ? ' data-tooltip="' . Html::escape($tooltip) . '"' : '';
        $markup .= '<a href="#" class="special-category-select__tree-link" data-term-id="' . $term_id . '"' . $parent_attr . $tooltip_attr . '>' . $label . '</a>';
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

    foreach ($selected_terms as $index => $term) {
      $term_id = (int) $term->id();
      $label = Html::escape($term->label());
      $tooltip = '';
      if ($term->hasField('field_tooltip')) {
        $tooltip = trim((string) $term->get('field_tooltip')->value);
      }
      $parent_label = '';
      foreach ($term->get('parent')->getValue() as $parent) {
        $parent_id = (int) $parent['target_id'];
        if ($parent_id) {
          $parent_entity = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($parent_id);
          if ($parent_entity) {
            $parent_label = Html::escape($parent_entity->label());
          }
          break;
        }
      }

      $markup .= '<li class="special-category-select__selected-item" data-term-id="' . $term_id . '">';
      $markup .= '<span class="special-category-select__handle"></span>';
      $tooltip_attr = $tooltip !== '' ? ' data-tooltip="' . Html::escape($tooltip) . '"' : '';
      $markup .= '<span class="special-category-select__selected-label"' . $tooltip_attr . '>' . $label . '</span>';
      if ($parent_label !== '') {
        $markup .= '<span class="special-category-select__selected-parent">(' . $parent_label . ')</span>';
      }
      $markup .= '<button type="button" class="special-category-select__remove" aria-label="' . $this->t('Remove term')->render() . '"></button>';
      $markup .= '</li>';
    }

    $markup .= '</ol>';
    return Markup::create($markup);
  }

}
