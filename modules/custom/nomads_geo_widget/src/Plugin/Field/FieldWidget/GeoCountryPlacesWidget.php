<?php

namespace Drupal\nomads_geo_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;

/**
 * Country -> places widget for taxonomy term reference fields.
 *
 * Enable this widget from: Structure -> Content types -> (type) -> Manage form
 * display -> select "Geo Country + Places" for a taxonomy term reference field.
 */
#[FieldWidget(
  id: 'nomads_geo_country_places',
  label: new TranslatableMarkup('Geo Country + Places'),
  description: new TranslatableMarkup('Select a country, then check and/or tag direct child places.'),
  field_types: ['entity_reference'],
)]
class GeoCountryPlacesWidget extends WidgetBase {
  /**
   * Max number of candidate terms to inspect when recovering from bad data.
   */
  protected const COUNTRY_RECOVERY_SCAN_LIMIT = 300;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'vocabulary' => 'cit_countries_information',
      'country_field_name' => 'field_country',
      'type_field' => 'field_type',
      'country_value' => 'country',
      'region_values' => 'region,continent,continental_subregion,subregion',
      'place_value' => 'place',
      'freetag_value' => 'free',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = [];

    $elements['vocabulary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vocabulary machine name'),
      '#default_value' => $this->getSetting('vocabulary'),
      '#required' => TRUE,
    ];

    $elements['type_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type field machine name'),
      '#default_value' => $this->getSetting('type_field'),
      '#required' => TRUE,
      '#description' => $this->t('Taxonomy term field used to store country/place/freetag type values.'),
    ];

    $elements['country_field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host entity country field machine name'),
      '#default_value' => $this->getSetting('country_field_name'),
      '#required' => TRUE,
      '#description' => $this->t('If this field exists on the host entity (node/paragraph) and has a value, the widget will prefill Country from it on initial build.'),
    ];

    $elements['country_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country type value'),
      '#default_value' => $this->getSetting('country_value'),
      '#required' => TRUE,
    ];

    $elements['region_values'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Region-like type values'),
      '#default_value' => $this->getSetting('region_values'),
      '#required' => FALSE,
      '#description' => $this->t('Comma-separated values accepted in the first autocomplete besides country (e.g. region, continent, subregion).'),
    ];

    $elements['place_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Place type value'),
      '#default_value' => $this->getSetting('place_value'),
      '#required' => TRUE,
    ];

    $elements['freetag_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Free tag type value'),
      '#default_value' => $this->getSetting('freetag_value'),
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Vocabulary: @value', ['@value' => $this->getSetting('vocabulary')]),
      $this->t('Country field on host entity: @value', ['@value' => $this->getSetting('country_field_name')]),
      $this->t('Type field: @value', ['@value' => $this->getSetting('type_field')]),
      $this->t('Country value: @value', ['@value' => $this->getSetting('country_value')]),
      $this->t('Region values: @value', ['@value' => $this->getSetting('region_values')]),
      $this->t('Place value: @value', ['@value' => $this->getSetting('place_value')]),
      $this->t('Free tag value: @value', ['@value' => $this->getSetting('freetag_value')]),
    ];
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
    if ($this->getFieldSetting('target_type') !== 'taxonomy_term') {
      $element['message'] = [
        '#type' => 'item',
        '#markup' => $this->t('Geo Country + Places supports taxonomy term references only.'),
      ];
      return $element;
    }

    $parents = array_merge($form['#parents'], [$this->fieldDefinition->getName()]);
    $wrapper_id = 'nomads-geo-' . Html::getId(implode('-', array_merge($parents, ['places-wrapper'])));

    $root_tid = $this->resolveCurrentRootTid($items, $form_state, $form['#parents'], $parents);

    $current_tids = $this->getCurrentFieldSelectedIds($items);
    $submitted_selection = $this->getSubmittedSelectedIds($form_state, $parents);
    $selected_ids = $submitted_selection['has_input'] ? $submitted_selection['ids'] : $current_tids;

    $vocabulary = (string) $this->getSetting('vocabulary');
    $root_default = $root_tid ? $this->loadTerm($root_tid) : NULL;
    if ($root_default instanceof TermInterface && !$this->isSelectableRootTerm($root_default)) {
      $root_default = NULL;
    }

    $this->sanitizeCountryUserInput($form_state, $parents);

    $field_title = isset($element['#title']) ? (string) $element['#title'] : '';
    $field_description = $element['#description'] ?? '';
    if ($field_title !== '' || $field_description !== '') {
      $field_meta_markup = '';
      $subtitle = $this->getFieldSubtitle($this->fieldDefinition);
      if ($subtitle !== NULL) {
        $field_meta_markup = '<span class="form-item__prefix"><span class="field-subtitle-text">'
          . Html::escape($subtitle) . '</span></span>';
      }

      $element['field_meta'] = [
        '#type' => 'item',
        '#title' => $field_title,
        '#markup' => Markup::create($field_meta_markup),
        '#weight' => -100,
      ];
      if ($subtitle !== NULL) {
        $element['field_meta']['#wrapper_attributes']['class'][] = 'field-subtitle-enabled';
        $element['field_meta']['#attached']['library'][] = 'field_subtitle/field_subtitle';
      }
    }

    $element['country'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Country or region'),
      '#target_type' => 'taxonomy_term',
      '#tags' => FALSE,
      '#selection_handler' => 'nomads_geo_widget:country_terms',
      '#selection_settings' => [
        'target_bundles' => [$vocabulary],
        'type_field' => (string) $this->getSetting('type_field'),
        'country_value' => (string) $this->getSetting('country_value'),
        'region_values' => $this->getRegionTypeValues(),
      ],
      '#default_value' => $root_default,
      '#parents' => array_merge($parents, ['country']),
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefreshPlaces'],
        'event' => 'autocompleteclose',
        'wrapper' => $wrapper_id,
      ],
      '#element_validate' => [[$this, 'validateCountryElement']],
      '#description' => '',
    ];

    $element['places_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['nomads-geo-widget'],
      ],
    ];

    if ($field_description !== '') {
      $element['field_help'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['description', 'nomads-geo-widget__description'],
        ],
        'content' => [
          '#markup' => $field_description,
        ],
      ];
    }

    if (!$root_tid) {
      return $element;
    }

    $is_region_root = $root_default instanceof TermInterface && $this->isRegionTerm($root_default);
    $place_terms = $is_region_root
      ? $this->loadDescendantTermsByTypes($root_tid, [(string) $this->getSetting('country_value')])
      : $this->loadDirectChildTermsByTypes($root_tid, [(string) $this->getSetting('place_value')]);
    $allowed_tag_terms = $is_region_root
      ? []
      : $this->loadDirectChildTermsByTypes($root_tid, [(string) $this->getSetting('freetag_value')]);

    $place_options = $this->buildCheckboxOptions($place_terms);

    $place_option_ids = array_map('intval', array_keys($place_options));
    $tag_allowed_ids = array_map('intval', array_keys($allowed_tag_terms));

    $checkbox_defaults = array_map('strval', array_values(array_intersect($selected_ids, $place_option_ids)));
    $tag_default_ids = array_values(array_intersect($selected_ids, $tag_allowed_ids));

    $element['places_wrapper']['places_checkboxes_ui'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['field--widget-options-pretty', 'nomads-geo-widget__places'],
      ],
    ];

    $element['places_wrapper']['places_checkboxes_ui']['places_checkboxes'] = [
      '#type' => 'checkboxes',
      '#pretty_option' => TRUE,
      '#title' => $is_region_root ? $this->t('Select all relevant countries') : $this->t('Select all relevant places'),
      '#options' => $place_options,
      '#default_value' => $checkbox_defaults,
      '#parents' => array_merge($parents, ['places_checkboxes']),
    ];

    if (!$is_region_root) {
      $tag_default_entities = $tag_default_ids ? $this->loadTerms($tag_default_ids) : [];
      $ordered_defaults = [];
      foreach ($tag_default_ids as $tid) {
        if (isset($tag_default_entities[$tid])) {
          $ordered_defaults[] = $tag_default_entities[$tid];
        }
      }

      $element['places_wrapper']['places_tags'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Add or select places'),
        '#target_type' => 'taxonomy_term',
        '#selection_handler' => 'nomads_geo_widget:country_free_tags',
        '#selection_settings' => [
          'target_bundles' => [$vocabulary],
          'country_tid' => $root_tid,
          'type_field' => (string) $this->getSetting('type_field'),
          'freetag_value' => (string) $this->getSetting('freetag_value'),
        ],
        '#tags' => TRUE,
        '#validate_reference' => FALSE,
        '#element_validate' => [[static::class, 'validatePlacesTagsElement']],
        '#default_value' => $ordered_defaults,
        '#parents' => array_merge($parents, ['places_tags']),
        '#description' => $this->t('Suggestions are limited to existing free tagged places under the selected country. New values create a free tagged place under that country.'),
      ];
    }

    // TODO: If country must be saved, map selected country tid to a dedicated field
    // in submit processing instead of keeping it in widget state only.
    // Note: country_field_name must exist on the host entity to prefill country.
    // If missing or empty, the widget intentionally starts with an empty country.

    return $element;
  }

  /**
   * Ajax callback to refresh places when country changes.
   */
  public static function ajaxRefreshPlaces(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
    return $element['places_wrapper'] ?? $element;
  }

  /**
   * Validates selected country against configured country type value.
   */
  public function validateCountryElement(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $field_path = array_slice($element['#parents'], 0, -1);
    $key_exists = NULL;
    $field_values = NestedArray::getValue($form_state->getUserInput(), $field_path, $key_exists);
    if (!$key_exists || !is_array($field_values)) {
      return;
    }

    $root_tid = static::extractTermId($field_values['country'] ?? NULL);
    if (!$root_tid) {
      return;
    }

    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($root_tid);
    if (!$term instanceof TermInterface) {
      return;
    }

    if ($term->bundle() !== $this->getSetting('vocabulary')) {
      $form_state->setError($element, $this->t('Selected country is not in the configured vocabulary.'));
      return;
    }

    if (!$this->isSelectableRootTerm($term)) {
      $form_state->setError($element, $this->t('Selected term is not a valid country or region.'));
    }
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
    if (!$key_exists || !is_array($values)) {
      return;
    }

    $normalized_values = (isset($values[0]) && is_array($values[0])) ? $values[0] : $values;
    $has_widget_input = is_array($normalized_values) && (
      array_key_exists('country', $normalized_values)
      || array_key_exists('places_checkboxes', $normalized_values)
      || array_key_exists('places_tags', $normalized_values)
    );

    // During unrelated add-more/rebuild operations, untouched paragraph widget
    // instances may not post widget keys. In that case, keep current values.
    if (!$has_widget_input) {
      return;
    }

    $root_tid = $this->extractTermId($normalized_values['country'] ?? NULL);
    $state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $state['root_tid'] = $root_tid;
    $state['country_tid'] = $root_tid;
    static::setWidgetState($form['#parents'], $field_name, $form_state, $state);

    $massaged = $this->massageFormValues($values, $form, $form_state);
    $items->setValue($massaged);
    $items->filterEmptyItems();

    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    foreach ($items as $delta => $item) {
      $field_state['original_deltas'][$delta] = $item->_original_delta ?? $delta;
      unset($item->_original_delta, $item->_weight, $item->_actions);
    }
    static::setWidgetState($form['#parents'], $field_name, $form_state, $field_state);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    if (isset($values[0]) && is_array($values[0])) {
      $values = $values[0];
    }

    $root_tid = $this->extractTermId($values['country'] ?? NULL);
    if (!$root_tid) {
      return [];
    }

    $root = $this->loadTerm($root_tid);
    if (!$root instanceof TermInterface) {
      return [];
    }

    $vocabulary = (string) $this->getSetting('vocabulary');
    if ($root->bundle() !== $vocabulary || !$this->isSelectableRootTerm($root)) {
      return [];
    }
    $is_region_root = $this->isRegionTerm($root);

    $checkbox_ids = $this->extractCheckedIds($values['places_checkboxes'] ?? []);

    $tag_existing_ids = [];
    $tag_new_labels = [];
    if (!$is_region_root) {
      $tag_selection = $this->extractTagSelections($values['places_tags'] ?? NULL);
      $tag_existing_ids = $tag_selection['ids'];
      $tag_new_labels = $tag_selection['new_labels'];
    }

    $allowed_checkbox_terms = $is_region_root
      ? $this->loadDescendantTermsByTypes($root_tid, [(string) $this->getSetting('country_value')])
      : $this->loadDirectChildTermsByTypes($root_tid, [(string) $this->getSetting('place_value')]);
    $allowed_tag_terms = $is_region_root
      ? []
      : $this->loadDirectChildTermsByTypes($root_tid, [(string) $this->getSetting('freetag_value')]);

    $allowed_checkbox_ids = array_map('intval', array_keys($allowed_checkbox_terms));
    $allowed_tag_ids = array_map('intval', array_keys($allowed_tag_terms));

    $checkbox_ids = array_values(array_intersect($checkbox_ids, $allowed_checkbox_ids));
    $tag_existing_ids = array_values(array_intersect($tag_existing_ids, $allowed_tag_ids));

    $created_or_reused = $is_region_root
      ? []
      : $this->resolveOrCreateTagTerms($root_tid, $tag_new_labels, $allowed_tag_terms, $form_state, $form);

    // Always persist the selected country term along with places/free tags.
    $final_ids = array_values(array_unique(array_merge([$root_tid], $checkbox_ids, $tag_existing_ids, $created_or_reused)));

    $massaged = [];
    foreach ($final_ids as $tid) {
      $massaged[] = ['target_id' => $tid];
    }

    return $massaged;
  }

  /**
   * Gets the configured subtitle for the current field, if any.
   */
  protected function getFieldSubtitle(FieldDefinitionInterface $field_definition): ?string {
    if (!method_exists($field_definition, 'getThirdPartySetting')) {
      return NULL;
    }

    $subtitle = $field_definition->getThirdPartySetting('field_subtitle', 'subtitle', '');
    if (!is_string($subtitle)) {
      return NULL;
    }

    $subtitle = trim($subtitle);
    return $subtitle !== '' ? $subtitle : NULL;
  }

  /**
   * Resolve term IDs from current widget state/input.
   */
  protected function getCurrentFieldSelectedIds(FieldItemListInterface $items): array {
    $selected = [];

    foreach ($items as $item) {
      if (!empty($item->target_id)) {
        $selected[] = (int) $item->target_id;
      }
    }

    return array_values(array_unique($selected));
  }

  /**
   * Resolve submitted widget selections (for AJAX rebuilds).
   */
  protected function getSubmittedSelectedIds(FormStateInterface $form_state, array $parents): array {
    $result = [
      'has_input' => FALSE,
      'ids' => [],
    ];

    $key_exists = NULL;
    $input = NestedArray::getValue($form_state->getUserInput(), $parents, $key_exists);
    if (!$key_exists || !is_array($input)) {
      return $result;
    }

    $ids = [];
    if (array_key_exists('places_checkboxes', $input)) {
      $result['has_input'] = TRUE;
      $ids = array_merge($ids, $this->extractCheckedIds($input['places_checkboxes']));
    }
    if (array_key_exists('places_tags', $input)) {
      $result['has_input'] = TRUE;
      $parsed = $this->extractTagSelections($input['places_tags']);
      $ids = array_merge($ids, $parsed['ids']);
    }

    $result['ids'] = array_values(array_unique(array_map('intval', $ids)));
    return $result;
  }

  /**
   * Resolve current root term ID (country or region) from input/state/entity.
   */
  protected function resolveCurrentRootTid(FieldItemListInterface $items, FormStateInterface $form_state, array $form_parents, array $parents): ?int {
    $field_name = $this->fieldDefinition->getName();

    $state = static::getWidgetState($form_parents, $field_name, $form_state);
    if (!is_array($state)) {
      $state = [];
    }

    $root_tid = isset($state['root_tid']) ? (int) $state['root_tid'] : (isset($state['country_tid']) ? (int) $state['country_tid'] : NULL);
    if ($root_tid && !$this->isSelectableRootTid($root_tid)) {
      $root_tid = NULL;
    }

    $key_exists = NULL;
    $input = NestedArray::getValue($form_state->getUserInput(), $parents, $key_exists);
    if ($key_exists && is_array($input) && array_key_exists('country', $input)) {
      $from_input = $this->extractTermId($input['country']);
      $root_tid = ($from_input && $this->isSelectableRootTid($from_input)) ? $from_input : NULL;
    }

    $values_key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $parents, $values_key_exists);
    if ($values_key_exists && is_array($values) && array_key_exists('country', $values)) {
      $from_values = $this->extractTermId($values['country']);
      if ($from_values && $this->isSelectableRootTid($from_values)) {
        $root_tid = $from_values;
      }
    }

    if (!$root_tid) {
      $current_selected_ids = $this->getCurrentFieldSelectedIds($items);
      $root_tid = $this->findSelectableRootTidInTermIds($current_selected_ids);
      if (!$root_tid) {
        $root_tid = $this->inferRootTidFromChildTerms($current_selected_ids);
      }
    }

    if (!$root_tid) {
      $host_entity = $items->getEntity();
      $country_field_name = (string) $this->getSetting('country_field_name');
      if (
        $host_entity
        && $country_field_name !== ''
        && $host_entity->hasField($country_field_name)
        && !$host_entity->get($country_field_name)->isEmpty()
      ) {
        $field_values = $host_entity->get($country_field_name)->getValue();
        $host_tids = [];
        foreach ($field_values as $item) {
          if (!empty($item['target_id']) && is_numeric($item['target_id'])) {
            $host_tids[] = (int) $item['target_id'];
          }
        }

        $root_tid = $this->findSelectableRootTidInTermIds($host_tids);
        if (!$root_tid) {
          $root_tid = $this->inferRootTidFromChildTerms($host_tids);
        }
      }
    }

    return $root_tid ? (int) $root_tid : NULL;
  }

  /**
   * Find first valid root term id (country or region) in a term-id list.
   */
  protected function findSelectableRootTidInTermIds(array $tids): ?int {
    if (empty($tids)) {
      return NULL;
    }

    $candidate_tids = array_values(array_unique(array_filter(array_map('intval', $tids), static fn (int $tid): bool => $tid > 0)));
    if (empty($candidate_tids)) {
      return NULL;
    }

    $candidate_tids = array_slice($candidate_tids, 0, self::COUNTRY_RECOVERY_SCAN_LIMIT);

    $terms = $this->loadTerms($candidate_tids);

    // Prefer region-like terms when both region and country are present in the
    // saved field values. This ensures edit forms rebuild in region mode.
    foreach ($candidate_tids as $tid) {
      $term = $terms[$tid] ?? NULL;
      if ($term instanceof TermInterface && $this->isRegionTerm($term)) {
        return $tid;
      }
    }

    foreach ($candidate_tids as $tid) {
      $term = $terms[$tid] ?? NULL;
      if ($term instanceof TermInterface && $this->isCountryTerm($term)) {
        return $tid;
      }
    }

    return NULL;
  }

  /**
   * Infer root by checking parents of selected child terms.
   */
  protected function inferRootTidFromChildTerms(array $tids): ?int {
    if (empty($tids)) {
      return NULL;
    }

    $candidate_tids = array_values(array_unique(array_filter(array_map('intval', $tids), static fn (int $tid): bool => $tid > 0)));
    if (empty($candidate_tids)) {
      return NULL;
    }

    $candidate_tids = array_slice($candidate_tids, 0, self::COUNTRY_RECOVERY_SCAN_LIMIT);
    $terms = $this->loadTerms($candidate_tids);

    $parent_candidates = [];
    foreach ($terms as $term) {
      if (!$term instanceof TermInterface || !$term->hasField('parent')) {
        continue;
      }
      foreach ($term->get('parent')->getValue() as $parent_item) {
        $parent_tid = (int) ($parent_item['target_id'] ?? 0);
        if ($parent_tid > 0) {
          $parent_candidates[] = $parent_tid;
        }
      }
    }

    if (empty($parent_candidates)) {
      return NULL;
    }

    return $this->findSelectableRootTidInTermIds($parent_candidates);
  }

  /**
   * Check if a term id is a valid country term for this widget.
   */
  protected function isCountryTid(int $tid): bool {
    if ($tid <= 0) {
      return FALSE;
    }
    $term = $this->loadTerm($tid);
    return $term instanceof TermInterface && $this->isCountryTerm($term);
  }

  /**
   * Check if a term id is a valid root term (country or region-like).
   */
  protected function isSelectableRootTid(int $tid): bool {
    if ($tid <= 0) {
      return FALSE;
    }
    $term = $this->loadTerm($tid);
    return $term instanceof TermInterface && $this->isSelectableRootTerm($term);
  }

  /**
   * Check if a term is of configured country type in configured vocabulary.
   */
  protected function isCountryTerm(TermInterface $term): bool {
    $type_field = (string) $this->getSetting('type_field');
    $country_value = (string) $this->getSetting('country_value');
    $vocabulary = (string) $this->getSetting('vocabulary');

    if ($term->bundle() !== $vocabulary) {
      return FALSE;
    }
    if (!$term->hasField($type_field)) {
      return FALSE;
    }

    return (string) $term->get($type_field)->value === $country_value;
  }

  /**
   * Check if a term is a valid root term (country or region-like).
   */
  protected function isSelectableRootTerm(TermInterface $term): bool {
    return $this->isCountryTerm($term) || $this->isRegionTerm($term);
  }

  /**
   * Check if a term is configured as a region-like type.
   */
  protected function isRegionTerm(TermInterface $term): bool {
    $type_field = (string) $this->getSetting('type_field');
    $vocabulary = (string) $this->getSetting('vocabulary');

    if ($term->bundle() !== $vocabulary || !$term->hasField($type_field)) {
      return FALSE;
    }

    return in_array((string) $term->get($type_field)->value, $this->getRegionTypeValues(), TRUE);
  }

  /**
   * Region-like type values from widget settings.
   */
  protected function getRegionTypeValues(): array {
    $raw = (string) $this->getSetting('region_values');
    $values = array_filter(array_map('trim', explode(',', $raw)), static fn(string $value): bool => $value !== '');
    return array_values(array_unique($values));
  }

  /**
   * Type values accepted in the root autocomplete.
   */
  protected function getSelectableRootTypeValues(): array {
    $country_value = trim((string) $this->getSetting('country_value'));
    $values = $this->getRegionTypeValues();
    if ($country_value !== '') {
      $values[] = $country_value;
    }
    return array_values(array_unique($values));
  }

  /**
   * Remove invalid submitted value for the country autocomplete input.
   */
  protected function sanitizeCountryUserInput(FormStateInterface $form_state, array $parents): void {
    $input = $form_state->getUserInput();
    if (!is_array($input)) {
      return;
    }

    $country_path = array_merge($parents, ['country']);
    $key_exists = NULL;
    $raw_country = NestedArray::getValue($input, $country_path, $key_exists);
    if (!$key_exists) {
      return;
    }

    $country_tid = static::extractTermId($raw_country);
    if ($country_tid && $this->isSelectableRootTid($country_tid)) {
      return;
    }

    // Invalid submissions (e.g. place labels or comma-joined values) should
    // never override the computed/default country value.
    NestedArray::setValue($input, $country_path, '');
    $form_state->setUserInput($input);
  }

  /**
   * Keep raw tags input so custom widget parsing can create free tags on save.
   */
  public static function validatePlacesTagsElement(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $input = $form_state->getUserInput();
    $key_exists = NULL;
    $raw_value = is_array($input)
      ? NestedArray::getValue($input, $element['#parents'], $key_exists)
      : NULL;

    if (!$key_exists) {
      $raw_value = $element['#value'] ?? NULL;
    }

    $form_state->setValueForElement($element, $raw_value);
  }

  /**
   * Load direct children of selected root by configured type values.
   *
   * Direct child means taxonomy parent contains the selected root tid.
   */
  protected function loadDirectChildTermsByTypes(int $parent_tid, array $type_values): array {
    $vocabulary = (string) $this->getSetting('vocabulary');
    $type_field = (string) $this->getSetting('type_field');

    $query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vocabulary)
      ->condition('parent', $parent_tid)
      ->sort('name', 'ASC');

    if (count($type_values) === 1) {
      $query->condition($type_field, reset($type_values));
    }
    else {
      $query->condition($type_field, array_values($type_values), 'IN');
    }

    $tids = $query->execute();
    if (empty($tids)) {
      return [];
    }

    return $this->loadTerms(array_map('intval', $tids));
  }

  /**
   * Load all descendant terms of a parent by configured type values.
   *
   * This traverses taxonomy hierarchy level-by-level and returns matching
   * descendants from any depth (children, grandchildren, etc.).
   */
  protected function loadDescendantTermsByTypes(int $root_parent_tid, array $type_values): array {
    if ($root_parent_tid <= 0) {
      return [];
    }

    $vocabulary = (string) $this->getSetting('vocabulary');
    $type_field = (string) $this->getSetting('type_field');

    $queue = [$root_parent_tid];
    $visited_parents = [];
    $matched_tids = [];

    while ($queue !== []) {
      $parent_batch = array_values(array_unique(array_filter(array_map('intval', $queue), static fn(int $tid): bool => $tid > 0)));
      $queue = [];
      if ($parent_batch === []) {
        break;
      }

      $remaining_parents = array_values(array_diff($parent_batch, $visited_parents));
      if ($remaining_parents === []) {
        continue;
      }
      $visited_parents = array_values(array_unique(array_merge($visited_parents, $remaining_parents)));

      $children_query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', $vocabulary)
        ->condition('parent', $remaining_parents, 'IN');
      $child_tids = $children_query->execute();
      if (empty($child_tids)) {
        continue;
      }

      $child_tids = array_values(array_unique(array_map('intval', $child_tids)));
      $queue = array_values(array_unique(array_merge($queue, $child_tids)));

      $match_query = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', $vocabulary)
        ->condition('tid', $child_tids, 'IN');

      if (count($type_values) === 1) {
        $match_query->condition($type_field, reset($type_values));
      }
      else {
        $match_query->condition($type_field, array_values($type_values), 'IN');
      }

      $level_matches = $match_query->execute();
      if (!empty($level_matches)) {
        $matched_tids = array_values(array_unique(array_merge($matched_tids, array_map('intval', $level_matches))));
      }
    }

    if ($matched_tids === []) {
      return [];
    }

    $terms = $this->loadTerms($matched_tids);
    uasort($terms, static function (TermInterface $a, TermInterface $b): int {
      return strcasecmp((string) $a->label(), (string) $b->label());
    });

    return $terms;
  }

  /**
   * Extract checked checkbox IDs.
   */
  protected function extractCheckedIds(mixed $raw): array {
    if (!is_array($raw)) {
      return [];
    }

    $ids = [];
    foreach ($raw as $key => $value) {
      // Accept only explicit "checked" states; ignore NULL/empty/zero values.
      if (is_numeric($key)) {
        $key_int = (int) $key;
        if ($key_int <= 0) {
          continue;
        }

        // Drupal checkboxes usually submit checked values as the option key.
        if ((string) $value === (string) $key_int) {
          $ids[] = $key_int;
          continue;
        }

        // Some wrappers can submit checked boxes as truthy values.
        if ($value === 1 || $value === '1' || $value === TRUE || $value === 'on') {
          $ids[] = $key_int;
        }
        continue;
      }

      if (is_numeric($value) && (int) $value > 0) {
        $ids[] = (int) $value;
      }
    }

    return array_values(array_unique($ids));
  }

  /**
   * Extract existing ids and new labels from the tags autocomplete input.
   */
  protected function extractTagSelections(mixed $raw): array {
    $ids = [];
    $new_labels = [];

    if (is_array($raw)) {
      foreach ($raw as $item) {
        if (is_array($item) && isset($item['target_id']) && is_numeric($item['target_id'])) {
          $ids[] = (int) $item['target_id'];
          continue;
        }

        if (is_scalar($item)) {
          $this->extractTagToken((string) $item, $ids, $new_labels);
        }
      }
    }
    elseif (is_scalar($raw)) {
      $tokens = array_filter(array_map('trim', explode(',', (string) $raw)));
      foreach ($tokens as $token) {
        $this->extractTagToken($token, $ids, $new_labels);
      }
    }

    return [
      'ids' => array_values(array_unique(array_map('intval', $ids))),
      'new_labels' => array_values(array_unique($new_labels)),
    ];
  }

  /**
   * Parse one tag token as existing term id or new label.
   */
  protected function extractTagToken(string $token, array &$ids, array &$new_labels): void {
    $token = trim($token);
    if ($token === '') {
      return;
    }

    if (ctype_digit($token)) {
      $ids[] = (int) $token;
      return;
    }

    $existing_tid = EntityAutocomplete::extractEntityIdFromAutocompleteInput($token);
    if ($existing_tid) {
      $ids[] = (int) $existing_tid;
      return;
    }

    $new_labels[] = $token;
  }

  /**
   * Resolve or create terms for free-tag labels.
   */
  protected function resolveOrCreateTagTerms(int $country_tid, array $labels, array $allowed_tag_terms, FormStateInterface $form_state, array $form): array {
    if (empty($labels)) {
      return [];
    }

    $by_label = [];
    foreach ($allowed_tag_terms as $term) {
      $key = mb_strtolower(trim((string) $term->label()));
      if ($key !== '' && !isset($by_label[$key])) {
        $by_label[$key] = (int) $term->id();
      }
    }

    $resolved_ids = [];
    $need_create = [];

    foreach ($labels as $label) {
      $trimmed = trim($label);
      if ($trimmed === '') {
        continue;
      }
      $key = mb_strtolower($trimmed);
      if (isset($by_label[$key])) {
        $resolved_ids[] = $by_label[$key];
        continue;
      }
      $need_create[$key] = $trimmed;
    }

    if (empty($need_create)) {
      return array_values(array_unique($resolved_ids));
    }

    $vocabulary = (string) $this->getSetting('vocabulary');
    $type_field = (string) $this->getSetting('type_field');
    $freetag_value = (string) $this->getSetting('freetag_value');

    $access_handler = \Drupal::entityTypeManager()->getAccessControlHandler('taxonomy_term');
    $can_create = $access_handler->createAccess($vocabulary, \Drupal::currentUser(), [], TRUE);
    if (!$can_create->isAllowed()) {
      $name = implode('][', array_merge($form['#parents'], [$this->fieldDefinition->getName(), 'places_tags']));
      $form_state->setErrorByName($name, $this->t('You do not have permission to create terms in %vocabulary.', ['%vocabulary' => $vocabulary]));
      return array_values(array_unique($resolved_ids));
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    foreach ($need_create as $key => $label) {
      // Re-check for duplicates under this country before creation.
      $match = $this->findDirectChildByName($country_tid, $label);
      if ($match) {
        $resolved_ids[] = $match;
        continue;
      }

      $values = [
        'vid' => $vocabulary,
        'name' => $label,
        'parent' => [$country_tid],
      ];
      $term = $term_storage->create($values);

      if ($term->hasField($type_field)) {
        $term->set($type_field, $freetag_value);
      }

      $term->save();
      $new_tid = (int) $term->id();
      if ($new_tid > 0) {
        $resolved_ids[] = $new_tid;
        $by_label[$key] = $new_tid;
      }
    }

    return array_values(array_unique($resolved_ids));
  }

  /**
   * Find a direct child term under country by case-insensitive name.
   */
  protected function findDirectChildByName(int $country_tid, string $name): ?int {
    $normalized = mb_strtolower(trim($name));
    if ($normalized === '') {
      return NULL;
    }

    $terms = $this->loadDirectChildTermsByTypes($country_tid, [
      (string) $this->getSetting('freetag_value'),
    ]);

    foreach ($terms as $term) {
      if (mb_strtolower(trim((string) $term->label())) === $normalized) {
        return (int) $term->id();
      }
    }

    return NULL;
  }

  /**
   * Extract taxonomy term id from different input formats.
   */
  protected static function extractTermId(mixed $value): ?int {
    if (is_array($value) && isset($value['target_id']) && is_numeric($value['target_id'])) {
      return (int) $value['target_id'];
    }

    if (is_numeric($value)) {
      return (int) $value;
    }

    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return NULL;
      }

      if (ctype_digit($value)) {
        return (int) $value;
      }

      $id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($value);
      if ($id) {
        return (int) $id;
      }
    }

    return NULL;
  }

  /**
   * Load one term.
   */
  protected function loadTerm(int $tid): ?TermInterface {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    return ($term instanceof TermInterface) ? $term : NULL;
  }

  /**
   * Load terms keyed by tid.
   */
  protected function loadTerms(array $tids): array {
    if (empty($tids)) {
      return [];
    }

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadMultiple(array_values(array_unique(array_map('intval', $tids))));

    $keyed = [];
    foreach ($terms as $term) {
      if ($term instanceof TermInterface) {
        $keyed[(int) $term->id()] = $term;
      }
    }

    return $keyed;
  }

  /**
   * Build checkbox options with non-empty labels.
   */
  protected function buildCheckboxOptions(array $terms): array {
    $options = [];
    foreach ($terms as $term) {
      if (!$term instanceof TermInterface) {
        continue;
      }

      $tid = (int) $term->id();
      if ($tid <= 0) {
        continue;
      }

      $label = trim((string) $term->label());
      if ($label === '' && $term->hasTranslation($term->language()->getId())) {
        $label = trim((string) $term->getUntranslated()->label());
      }
      if ($label === '' && $term->hasField('name') && !$term->get('name')->isEmpty()) {
        $label = trim((string) $term->get('name')->value);
      }
      if ($label === '') {
        $label = 'Term ' . $tid;
      }

      $options[(string) $tid] = $label;
    }

    return $options;
  }

}
