<?php

declare(strict_types=1);

namespace Drupal\paragraph_relevance\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;

#[\Drupal\Core\Field\Attribute\FieldFormatter(
  id: 'paragraph_relevance_rendered_relevant_paragraph',
  label: new TranslatableMarkup('Rendered relevant paragraph'),
  description: new TranslatableMarkup('Render referenced paragraphs filtered by field_relevance2.'),
  field_types: [
    'entity_reference_revisions',
  ],
)]
class RenderedRelevantParagraphFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'relevance_values' => [
        '1' => '1',
        '2' => '2',
        '3' => '3',
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['relevance_values'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Relevance value'),
      '#options' => [
        '1' => '1',
        '2' => '2',
        '3' => '3',
      ],
      '#default_value' => $this->getSelectedRelevanceValues(),
      '#description' => $this->t('Only paragraphs whose field_relevance2 matches a checked value will be rendered.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $selected = $this->getSelectedRelevanceValues();

    $summary[] = $selected
      ? $this->t('Relevance values: @values', ['@values' => implode(', ', $selected)])
      : $this->t('Relevance values: none');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $view_mode = $this->getSetting('view_mode');
    $selected_values = $this->getSelectedRelevanceValues();
    $elements = [];

    if ($selected_values === []) {
      return $elements;
    }

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if (!$entity->hasField('field_relevance2')) {
        continue;
      }

      $relevance = paragraph_relevance_extract_value($entity->get('field_relevance2')->getValue());
      if ($relevance === NULL || !in_array((string) $relevance, $selected_values, TRUE)) {
        continue;
      }

      // Match the standard rendered-entity formatter recursion protection.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@entity_id' => $entity->id(),
        ]);
        return $elements;
      }

      $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity->getEntityTypeId());
      $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

      if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
      }

      $depth = 0;
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting('target_type');
    return $target_type === 'paragraph'
      && parent::isApplicable($field_definition);
  }

  /**
   * Returns the configured relevance values.
   *
   * @return string[]
   *   The selected relevance values.
   */
  protected function getSelectedRelevanceValues(): array {
    $selected = $this->getSetting('relevance_values');
    if (!is_array($selected)) {
      return [];
    }

    return array_values(array_filter(array_map('strval', $selected), static fn (string $value): bool => $value !== '0' && $value !== ''));
  }

}
