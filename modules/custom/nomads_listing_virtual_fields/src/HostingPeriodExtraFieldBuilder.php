<?php

namespace Drupal\nomads_listing_virtual_fields;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds the hosting period virtual field.
 */
class HostingPeriodExtraFieldBuilder {

  use StringTranslationTrait;

  /**
   * Constructs a HostingPeriodExtraFieldBuilder instance.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Builds the hosting period extra field render array.
   */
  public function build(ParagraphInterface $paragraph, EntityViewDisplayInterface $display, string $langcode): ?array {
    $entity = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph;

    $min_value = $this->getMinimumValue($entity, 'field_minimum_hosting');
    $typical_labels = $this->getTypicalLabels($entity, 'field_typical_hosting_period');

    if ($min_value === NULL || $typical_labels === []) {
      return NULL;
    }

    $output_style = 'default';

    return [
      '#theme' => 'nomads_hosting_period_extra_field',
      '#label' => $this->t('Hosting Period'),
      '#label_display' => $display->getComponent('nomads_hosting_period_virtual_field')['label'] ?? 'hidden',
      '#output_style' => $output_style,
      '#min_value' => $min_value,
      '#typical_labels' => $typical_labels,
      '#cache' => [
        'contexts' => ['languages:language_interface'],
        'tags' => $paragraph->getCacheTags(),
      ],
    ];
  }

  /**
   * Gets the minimum hosting value.
   */
  protected function getMinimumValue(FieldableEntityInterface $entity, string $field_name): ?string {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return NULL;
    }

    $value = $field->value;
    return $value === NULL || $value === '' ? NULL : (string) $value;
  }

  /**
   * Gets typical hosting period labels from allowed values.
   */
  protected function getTypicalLabels(FieldableEntityInterface $entity, string $field_name): array {
    if (!$entity->hasField($field_name)) {
      return [];
    }

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return [];
    }

    $definition = $entity->getFieldDefinition($field_name);
    $allowed_values = [];
    if (function_exists('options_allowed_values')) {
      $allowed_values = options_allowed_values($definition->getFieldStorageDefinition(), $entity) ?: [];
    }

    $labels = [];
    foreach ($field as $item) {
      $value = (string) $item->value;
      if ($value === '') {
        continue;
      }
      $labels[] = (string) ($allowed_values[$value] ?? $value);
    }

    return $labels;
  }

}
