<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField;

/**
 * Plugin implementations for 'table' formatter.
 */
#[FieldFormatter(
  id: self::ID,
  label: new TM('Table'),
  field_types: [DoubleField::ID],
)]
final class Table extends Base {

  public const string ID = 'double_field_table';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'number_column' => FALSE,
      'number_column_label' => '№',
      'first_column_label' => '',
      'second_column_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();

    $element['number_column'] = [
      '#type' => 'checkbox',
      '#title' => new TM('Enable row number column'),
      '#default_value' => $settings['number_column'],
      '#attributes' => ['class' => ['js-double-field-number-column']],
    ];
    $element['number_column_label'] = [
      '#type' => 'textfield',
      '#title' => new TM('Number column label'),
      '#size' => 30,
      '#default_value' => $settings['number_column_label'],
      '#states' => [
        'visible' => ['.js-double-field-number-column' => ['checked' => TRUE]],
      ],
    ];
    foreach (['first', 'second'] as $subfield) {
      $element[$subfield . '_column_label'] = [
        '#type' => 'textfield',
        '#title' => $subfield === 'first' ? new TM('First column label') : new TM('Second column label'),
        '#size' => 30,
        '#default_value' => $settings[$subfield . '_column_label'] ?: $this->getFieldSetting($subfield)['label'],
      ];
    }

    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();

    $summary[] = new TM('Enable row number column: @number_column', ['@number_column' => $settings['number_column'] ? new TM('yes') : new TM('no')]);
    if ($settings['number_column']) {
      $summary[] = new TM('Number column label: @number_column_label', ['@number_column_label' => $settings['number_column_label']]);
    }
    if ($settings['first_column_label'] != '') {
      $summary[] = new TM('First column label: @first_column_label', ['@first_column_label' => $settings['first_column_label']]);
    }
    if ($settings['second_column_label'] != '') {
      $summary[] = new TM('Second column label: @second_column_label', ['@second_column_label' => $settings['second_column_label']]);
    }

    return \array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $settings = $this->getSettings();

    $table = ['#type' => 'table'];

    // No other way to pass context to the theme.
    // @see double_field_theme_suggestions_table_alter()
    $table['#attributes']['double-field--field-name'] = $items->getName();
    $table['#attributes']['class'][] = 'double-field-table';

    if ($settings['first_column_label'] || $settings['second_column_label']) {
      if ($settings['number_column']) {
        $header[] = $settings['number_column_label'];
      }
      $header[] = $settings['first_column_label'];
      $header[] = $settings['second_column_label'];
      $table['#header'] = $header;
    }

    foreach ($items as $delta => $item) {
      $row = [];
      if ($settings['number_column']) {
        $row[]['#markup'] = $delta + 1;
      }

      foreach (['first', 'second'] as $subfield) {
        if ($settings[$subfield]['hidden']) {
          $row[]['#markup'] = '';
        }
        else {
          $row[] = [
            '#type' => 'inline_template',
            '#template' => '{{ value }}',
            '#context' => ['value' => $item->{$subfield}],
          ];
        }
      }

      $table[$delta] = $row;
    }

    return [$table];
  }

}
