<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField as DoubleFieldItem;

/**
 * Base class for Double field formatters.
 */
abstract class Base extends FormatterBase {

  /**
   * Subfield types that can be rendered as a link.
   */
  private const array LINK_TYPES = ['email', 'telephone', 'uri'];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $subfield_settings = [
      // Hidden option is useful to display data with Views module.
      'hidden' => FALSE,
      'link' => FALSE,
      'format_type' => 'medium',
      // @todo Create tests for this options.
      'thousand_separator' => '',
      'decimal_separator' => '.',
      'scale' => 2,
      'key' => FALSE,
    ];
    return [
      'first' => $subfield_settings,
      'second' => $subfield_settings,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $types = DoubleFieldItem::subfieldTypes();
    $element = [];

    // General settings.
    foreach (['first', 'second'] as $subfield) {
      $type = $field_settings['storage'][$subfield]['type'];

      $title = $subfield === 'first' ? $this->t('First subfield') : $this->t('Second subfield');
      $title .= ' - ' . $types[$type];
      if ($field_settings[$subfield]['list']) {
        $title .= ' (' . $this->t('list') . ')';
      }

      $element[$subfield] = [
        '#title' => $title,
        '#type' => 'details',
      ];

      $element[$subfield]['hidden'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hidden'),
        '#default_value' => $settings[$subfield]['hidden'],
      ];

      $element[$subfield]['link'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display as link'),
        '#default_value' => $settings[$subfield]['link'],
        '#weight' => -10,
        '#access' => \in_array($type, self::LINK_TYPES),
      ];

      if ($type === 'datetime_iso8601') {
        // @phpcs:ignore DrupalPractice.Objects.GlobalClass.GlobalClass
        $format_types = DateFormat::loadMultiple();
        $date_formatter = static::getDateFormatter();
        $time = new DrupalDateTime();
        $options = [];
        foreach ($format_types as $type => $type_info) {
          $format = $date_formatter->format($time->getTimestamp(), $type);
          $options[$type] = $type_info->label() . ' (' . $format . ')';
        }
        $element[$subfield]['format_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Date format'),
          '#description' => $this->t('Choose a format for displaying the date.'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['format_type'],
        ];
      }
      else {
        $element[$subfield]['format_type'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['format_type'],
        ];
      }

      if ($field_settings[$subfield]['list']) {
        $element[$subfield]['key'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Display key instead of label'),
          '#default_value' => $settings[$subfield]['key'],
        ];
      }

      if ($type === 'numeric' || $type === 'float' || $type === 'integer') {
        $options = [
          '' => $this->t('- None -'),
          '.' => $this->t('Decimal point'),
          ',' => $this->t('Comma'),
          ' ' => $this->t('Space'),
          \chr(8201) => $this->t('Thin space'),
          "'" => $this->t('Apostrophe'),
        ];
        $element[$subfield]['thousand_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Thousand marker'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }
      else {
        $element[$subfield]['thousand_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }

      if ($type === 'numeric' || $type === 'float') {
        $element[$subfield]['decimal_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Decimal marker'),
          '#options' => [
            '.' => $this->t('Decimal point'),
            ',' => $this->t('Comma'),
          ],
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element[$subfield]['scale'] = [
          '#type' => 'number',
          '#title' => $this->t('Scale', [], ['context' => 'decimal places']),
          '#min' => 0,
          '#max' => 10,
          '#default_value' => $settings[$subfield]['scale'],
          '#description' => $this->t('The number of digits to the right of the decimal.'),
        ];
      }
      else {
        $element[$subfield]['decimal_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element[$subfield]['scale'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['scale'],
        ];
      }

    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $subfield_types = DoubleFieldItem::subfieldTypes();

    $summary = [];
    foreach (['first', 'second'] as $subfield) {
      $subfield_type = $field_settings['storage'][$subfield]['type'];
      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type@list</b>',
        [
          '@subfield' => ($subfield === 'first' ? $this->t('First subfield') : $this->t('Second subfield')),
          '@subfield_type' => \strtolower((string) $subfield_types[$subfield_type]),
          '@list' => $field_settings[$subfield]['list'] ? ' (' . $this->t('list') . ')' : '',
        ]
      );
      if ($subfield_type === 'datetime_iso8601') {
        $summary[] = $this->t('Date format: @format', ['@format' => $settings[$subfield]['format_type']]);
      }
      if (\in_array($subfield_type, self::LINK_TYPES)) {
        $summary[] = $this->t('Link: @value', ['@value' => $settings[$subfield]['link'] ? $this->t('yes') : $this->t('no')]);
      }
      $summary[] = $this->t('Hidden: @value', ['@value' => $settings[$subfield]['hidden'] ? $this->t('yes') : $this->t('no')]);
      if ($field_settings[$subfield]['list']) {
        $summary[] = $this->t('Display key: @value', ['@value' => $settings[$subfield]['key'] ? $this->t('yes') : $this->t('no')]);

      }
      if ($subfield_type === 'numeric' || $subfield_type === 'float' || $subfield_type === 'integer') {
        $summary[] = $this->t('Number format: @format', ['@format' => $this->numberFormat($subfield, 1_234.123_456_789)]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];
    if (\count($items) > 0) {
      // A field may appear multiple times in a single view. Since items are
      // passed by reference we need to ensure they are processed only once.
      $items = clone $items;
      $this->prepareItems($items);
      $elements = parent::view($items, $langcode);
    }
    return $elements;
  }

  /**
   * Prepare field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   List of field items.
   */
  protected function prepareItems(FieldItemListInterface $items): void {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      foreach (['first', 'second'] as $subfield) {

        if ($settings[$subfield]['hidden']) {
          $item->{$subfield} = NULL;
          continue;
        }

        $type = $field_settings['storage'][$subfield]['type'];

        if ($type === 'boolean') {
          $item->{$subfield} = $field_settings[$subfield][$item->{$subfield} ? 'on_label' : 'off_label'];
        }

        // Empty string should already be converted into NULL.
        // @see Drupal\double_field\Plugin\Field\FieldWidget\DoubleField::massageFormValues()
        if ($item->{$subfield} === NULL) {
          continue;
        }

        if ($type === 'numeric' || $type === 'float' || $type === 'integer') {
          $item->{$subfield} = $this->numberFormat($subfield, $item->{$subfield});
        }

        if ($type === 'datetime_iso8601' && $item->{$subfield} && !$field_settings[$subfield]['list']) {
          // We follow the same principles as Drupal Core.
          // In the case of a datetime subfield, the date must be parsed using
          // the storage time zone and converted to the user's time zone while
          // a date-only field should have no timezone conversion performed.
          $timezone = $field_settings['storage'][$subfield]['datetime_type'] === 'datetime' ?
            \date_default_timezone_get() : DoubleFieldItem::DATETIME_STORAGE_TIMEZONE;
          // @phpstan-ignore method.notFound
          $timestamp = $items[$delta]->createDate($subfield)->getTimestamp();
          $date_formatter = static::getDateFormatter();
          $item->{$subfield} = [
            '#theme' => 'time',
            '#text' => $date_formatter->format($timestamp, $settings[$subfield]['format_type'], '', $timezone),
            '#html' => FALSE,
            '#attributes' => [
              'datetime' => $date_formatter->format($timestamp, 'custom', 'Y-m-d\TH:i:s') . 'Z',
            ],
            '#cache' => [
              'contexts' => ['timezone'],
            ],
          ];
        }

        $original_value[$subfield] = $item->{$subfield};
        if ($field_settings[$subfield]['list']) {
          $display_key = $settings[$subfield]['key'];
          if (!$display_key) {
            // Replace the value with its label if possible.
            $item->{$subfield} = $field_settings[$subfield]['allowed_values'][$item->{$subfield}] ?? NULL;
          }
        }

        if (!empty($settings[$subfield]['link'])) {
          $value = $original_value[$subfield];
          switch ($type) {
            case 'email':
              $item->{$subfield} = [
                '#type' => 'link',
                '#title' => $item->{$subfield},
                '#url' => Url::fromUri('mailto:' . $value),
              ];
              break;

            case 'telephone':
              $item->{$subfield} = [
                '#type' => 'link',
                '#title' => $item->{$subfield},
                '#url' => Url::fromUri('tel:' . \rawurlencode(\preg_replace('/\s+/', '', $value))),
                '#options' => ['external' => TRUE],
              ];
              break;

            case 'uri':
              $item->{$subfield} = [
                '#type' => 'link',
                '#title' => $item->{$subfield},
                '#url' => Url::fromUri($value),
                '#options' => ['external' => TRUE],
              ];
              break;

          }
        }
      }
      $items[$delta] = $item;
    }
  }

  /**
   * Formats a number.
   */
  protected function numberFormat(string $subfield, string|float|int $number): string {
    $settings = $this->getSetting($subfield);
    if ($this->getFieldSetting('storage')[$subfield]['type'] === 'integer') {
      $settings['scale'] = 0;
    }
    return \number_format(
      num:(float) $number,
      decimals: (int) $settings['scale'],
      decimal_separator: $settings['decimal_separator'],
      thousands_separator: $settings['thousand_separator'],
    );
  }

  /**
   * Returns date formatter.
   */
  protected static function getDateFormatter(): DateFormatterInterface {
    return \Drupal::service('date.formatter');
  }

}
