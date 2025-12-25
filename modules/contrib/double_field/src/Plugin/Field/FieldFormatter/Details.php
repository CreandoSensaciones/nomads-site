<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField;

/**
 * Plugin implementations for 'details' formatter.
 */
#[FieldFormatter(
  id: self::ID,
  label: new TM('Details'),
  field_types: [DoubleField::ID],
)]
final class Details extends Base {

  public const string ID = 'double_field_details';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['open' => TRUE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $element['open'] = [
      '#type' => 'checkbox',
      '#title' => new TM('Open'),
      '#default_value' => $settings['open'],
    ];
    $element += parent::settingsForm($form, $form_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $open = $this->getSetting('open');
    $summary[] = new TM('Open: @open', ['@open' => $open ? new TM('yes') : new TM('no')]);
    return \array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    $settings = $this->getSettings();

    $attributes = [
      // No other way to pass context to the theme.
      // @see double_field_theme_suggestions_details_alter()
      'double-field--field-name' => $items->getName(),
      'class' => ['double-field-details'],
    ];

    foreach ($items as $delta => $item) {
      $values = [];
      foreach (['first', 'second'] as $subfield) {
        $values[$subfield] = $item->{$subfield};
      }
      $element[$delta] = [
        '#title' => $values['first'],
        '#value' => $values['second'],
        '#type' => 'details',
        '#open' => $settings['open'],
        '#attributes' => $attributes,
      ];
    }

    return $element;
  }

}
