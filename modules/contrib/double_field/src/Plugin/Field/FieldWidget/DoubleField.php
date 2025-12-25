<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldWidget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;
use Drupal\double_field\Plugin\Field\FieldType\DoubleField as DoubleFieldItem;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'double_field' widget.
 */
#[FieldWidget(
  id: self::ID,
  label: new TM('Double Field'),
  field_types: [DoubleFieldItem::ID],
)]
final class DoubleField extends WidgetBase {

  public const string ID = 'double_field';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {

    foreach (['first', 'second'] as $subfield) {
      $settings[$subfield] = [
        // As this method is static there is no way to set an appropriate type
        // for the sub-widget. Let self::getSettings() do it instead.
        'type' => NULL,
        'label_display' => 'block',
        'size' => 30,
        'placeholder' => '',
        'label' => t('Ok'),
        'cols' => 10,
        'rows' => 5,
      ];
    }
    $settings['inline'] = FALSE;

    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $types = DoubleFieldItem::subfieldTypes();

    $field_name = $this->fieldDefinition->getName();

    $element['inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as inline element'),
      '#default_value' => $settings['inline'],
    ];

    foreach (['first', 'second'] as $subfield) {

      $type = $field_settings['storage'][$subfield]['type'];

      $title = $subfield === 'first' ? $this->t('First subfield') : $this->t('Second subfield');
      $title .= ' - ' . $types[$type];

      $element[$subfield] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
      ];

      $element[$subfield]['type'] = [
        '#type' => 'select',
        '#title' => new TM('Widget'),
        '#default_value' => $settings[$subfield]['type'],
        '#required' => TRUE,
        '#options' => $this->getSubWidgets($type, $field_settings[$subfield]['list']),
      ];

      $element[$subfield]['label_display'] = [
        '#type' => 'select',
        '#title' => new TM('Label'),
        '#default_value' => $settings[$subfield]['label_display'],
        '#required' => TRUE,
        '#options' => [
          'block' => new TM('Above'),
          'inline' => new TM('Inline'),
          'invisible' => new TM('Invisible'),
          'hidden' => new TM('Hidden'),
        ],
        '#access' => self::isLabelSupported($settings[$subfield]['type']),
      ];

      if ($settings[$subfield]['type'] === 'datetime') {
        unset(
          $element[$subfield]['label_display']['#options']['inline'],
          $element[$subfield]['label_display']['#options']['invisible'],
        );
      }

      $type_selector = "select[name='fields[$field_name][settings_edit_form][settings][$subfield][type]'";
      $element[$subfield]['size'] = [
        '#type' => 'number',
        '#title' => new TM('Size'),
        '#default_value' => $settings[$subfield]['size'],
        '#min' => 1,
        '#states' => [
          'visible' => [
            [$type_selector => ['value' => 'textfield']],
            [$type_selector => ['value' => 'email']],
            [$type_selector => ['value' => 'tel']],
            [$type_selector => ['value' => 'url']],
          ],
        ],
      ];

      $element[$subfield]['placeholder'] = [
        '#type' => 'textfield',
        '#title' => new TM('Placeholder'),
        '#default_value' => $settings[$subfield]['placeholder'],
        '#states' => [
          'visible' => [
            [$type_selector => ['value' => 'textfield']],
            [$type_selector => ['value' => 'textarea']],
            [$type_selector => ['value' => 'email']],
            [$type_selector => ['value' => 'tel']],
            [$type_selector => ['value' => 'url']],
          ],
        ],
      ];

      $element[$subfield]['label'] = [
        '#type' => 'textfield',
        '#title' => new TM('Label'),
        '#default_value' => $settings[$subfield]['label'],
        '#required' => TRUE,
        '#states' => [
          'visible' => [$type_selector => ['value' => 'checkbox']],
        ],
      ];

      $element[$subfield]['cols'] = [
        '#type' => 'number',
        '#title' => new TM('Columns'),
        '#default_value' => $settings[$subfield]['cols'],
        '#min' => 1,
        '#description' => new TM('How many columns wide the textarea should be'),
        '#states' => [
          'visible' => [$type_selector => ['value' => 'textarea']],
        ],
      ];

      $element[$subfield]['rows'] = [
        '#type' => 'number',
        '#title' => new TM('Rows'),
        '#default_value' => $settings[$subfield]['rows'],
        '#min' => 1,
        '#description' => new TM('How many rows high the textarea should be.'),
        '#states' => [
          'visible' => [$type_selector => ['value' => 'textarea']],
        ],
      ];

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
    if ($settings['inline']) {
      $summary[] = new TM('Display as inline element');
    }

    foreach (['first', 'second'] as $subfield) {
      $subfield_type = $subfield_types[$field_settings['storage'][$subfield]['type']];

      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type</b>',
        [
          '@subfield' => $subfield === 'first' ? new TM('First subfield') : new TM('Second subfield'),
          '@subfield_type' => \strtolower((string) $subfield_type),
        ]
      );

      $summary[] = new TM('Widget: @type', ['@type' => $settings[$subfield]['type']]);
      if (self::isLabelSupported($settings[$subfield]['type'])) {
        $summary[] = new TM('Label display: @label', ['@label' => $settings[$subfield]['label_display']]);
      }
      switch ($settings[$subfield]['type']) {
        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          $summary[] = new TM('Size: @size', ['@size' => $settings[$subfield]['size']]);
          if ($settings[$subfield]['placeholder'] != '') {
            $summary[] = new TM('Placeholder: @placeholder', ['@placeholder' => $settings[$subfield]['placeholder']]);
          }
          break;

        case 'checkbox':
          $summary[] = new TM('Label: @label', ['@label' => $settings[$subfield]['label']]);
          break;

        case 'select':
          break;

        case 'textarea':
          $summary[] = new TM('Columns: @cols', ['@cols' => $settings[$subfield]['cols']]);
          $summary[] = new TM('Rows: @rows', ['@rows' => $settings[$subfield]['rows']]);
          if ($settings[$subfield]['placeholder'] != '') {
            $summary[] = new TM('Placeholder: @placeholder', ['@placeholder' => $settings[$subfield]['placeholder']]);
          }
          break;
      }

    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $widget = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['double-field-elements']],
      '#attached' => ['library' => ['double_field/widget']],
    ];

    foreach (['first', 'second'] as $sub_field) {
      $sub_widget = [
        '#type' => $settings[$sub_field]['type'],
        '#default_value' => $items[$delta]->{$sub_field} ?? NULL,
        '#subfield_settings' => $settings[$sub_field],
        '#wrapper_attributes' => ['class' => ['double-field-subfield-form-item']],
      ];

      $label_display = $settings[$sub_field]['label_display'];
      $label = $field_settings[$sub_field]['label'];
      $widget_type = $settings[$sub_field]['type'];
      if ($label_display != 'hidden' && self::isLabelSupported($widget_type)) {
        $sub_widget['#title'] = $label;
        if ($label_display === 'invisible') {
          $sub_widget['#title_display'] = 'invisible';
        }
        elseif ($label_display === 'inline') {
          $sub_widget['#wrapper_attributes']['class'][] = 'container-inline';
        }
      }

      $storage_type = $field_settings['storage'][$sub_field]['type'];

      switch ($widget_type) {
        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          // Find out appropriate max length fot the element.
          $max_length_map = [
            'string' => $field_settings['storage'][$sub_field]['maxlength'],
            'telephone' => $field_settings['storage'][$sub_field]['maxlength'],
            'email' => Email::EMAIL_MAX_LENGTH,
            'uri' => 2048,
          ];
          if (isset($max_length_map[$storage_type])) {
            $sub_widget['#maxlength'] = $max_length_map[$storage_type];
          }
          if ($settings[$sub_field]['size']) {
            $sub_widget['#size'] = $settings[$sub_field]['size'];
          }
          if ($settings[$sub_field]['placeholder']) {
            $sub_widget['#placeholder'] = $settings[$sub_field]['placeholder'];
          }
          break;

        case 'checkbox':
          $sub_widget['#title'] = $settings[$sub_field]['label'];
          break;

        case 'select':
          $label = $field_settings[$sub_field]['required'] ? new TM('- Select a value -') : new TM('- None -');
          $sub_widget['#options'] = ['' => $label];
          if ($field_settings[$sub_field]['list']) {
            $sub_widget['#options'] += $field_settings[$sub_field]['allowed_values'];
          }
          break;

        case 'radios':
          $label = $field_settings[$sub_field]['required'] ? new TM('N/A') : new TM('- None -');
          $sub_widget['#options'] = ['' => $label];
          if ($field_settings[$sub_field]['list']) {
            $sub_widget['#options'] += $field_settings[$sub_field]['allowed_values'];
          }
          break;

        case 'textarea':
          if ($settings[$sub_field]['cols']) {
            $sub_widget['#cols'] = $settings[$sub_field]['cols'];
          }
          if ($settings[$sub_field]['rows']) {
            $sub_widget['#rows'] = $settings[$sub_field]['rows'];
          }
          if ($settings[$sub_field]['placeholder']) {
            $sub_widget['#placeholder'] = $settings[$sub_field]['placeholder'];
          }
          break;

        case 'number':
        case 'range':
          if (\in_array($storage_type, ['integer', 'float', 'numeric'])) {
            if ($field_settings[$sub_field]['min']) {
              $sub_widget['#min'] = $field_settings[$sub_field]['min'];
            }
            if ($field_settings[$sub_field]['max']) {
              $sub_widget['#max'] = $field_settings[$sub_field]['max'];
            }
            if ($storage_type === 'numeric') {
              $sub_widget['#step'] = \pow(0.1, $field_settings['storage'][$sub_field]['scale']);
            }
            elseif ($storage_type === 'float') {
              $sub_widget['#step'] = 'any';
            }
          }
          break;

        case 'datetime':
          // @phpstan-ignore method.notFound
          $sub_widget['#default_value'] = $items[$delta]->createDate($sub_field);
          if ($field_settings['storage'][$sub_field]['datetime_type'] === 'date') {
            $sub_widget['#date_time_element'] = 'none';
            $sub_widget['#date_time_format'] = '';
          }
          else {
            if ($sub_widget['#default_value']) {
              $sub_widget['#default_value']->setTimezone(new \DateTimezone(\date_default_timezone_get()));
            }
            // Ensure that the datetime field processing doesn't set its own
            // time zone here.
            $sub_widget['#date_timezone'] = \date_default_timezone_get();
          }
          break;
      }
      $widget[$sub_field] = $sub_widget;
    }

    if ($settings['inline']) {
      $widget['#attributes']['class'][] = 'double-field-widget-inline';
    }

    return $element + $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $storage_settings = $this->getFieldSetting('storage');

    foreach ($values as $delta => $value) {
      foreach (['first', 'second'] as $subfield) {
        if ($value[$subfield] === '') {
          $values[$delta][$subfield] = NULL;
        }
        elseif ($value[$subfield] instanceof DrupalDateTime) {
          $storage_format = $storage_settings[$subfield]['datetime_type'] === 'datetime'
            ? DoubleFieldItem::DATETIME_DATETIME_STORAGE_FORMAT
            : DoubleFieldItem::DATETIME_DATE_STORAGE_FORMAT;

          // Before it can be saved, the time entered by the user must be
          // converted to the storage time zone.
          $value[$subfield]->setTimezone(
            new \DateTimezone(DoubleFieldItem::DATETIME_STORAGE_TIMEZONE),
          );
          $values[$delta][$subfield] = $value[$subfield]->format($storage_format);
        }
      }
    }

    return $values;
  }

  /**
   * Returns available sub-widgets.
   *
   * @todo Figure out why $list is not boolean when changing field type on field
   * settings form.
   */
  protected function getSubWidgets(string $subfield_type, $list): array {
    $sub_widgets = [];

    if ($list) {
      $sub_widgets['select'] = new TM('Select list');
      $sub_widgets['radios'] = new TM('Radio buttons');
    }

    switch ($subfield_type) {

      case 'boolean':
        $sub_widgets['checkbox'] = new TM('Checkbox');
        break;

      case 'string':
        $sub_widgets['textfield'] = new TM('Textfield');
        $sub_widgets['email'] = new TM('Email');
        $sub_widgets['tel'] = new TM('Telephone');
        $sub_widgets['url'] = new TM('Url');
        $sub_widgets['color'] = new TM('Color');
        break;

      case 'email':
        $sub_widgets['email'] = new TM('Email');
        $sub_widgets['textfield'] = new TM('Textfield');
        break;

      case 'telephone':
        $sub_widgets['tel'] = new TM('Telephone');
        $sub_widgets['textfield'] = new TM('Textfield');
        break;

      case 'uri':
        $sub_widgets['url'] = new TM('Url');
        $sub_widgets['textfield'] = new TM('Textfield');
        break;

      case 'text':
        $sub_widgets['textarea'] = new TM('Text area');
        break;

      case 'integer':
      case 'float':
      case 'numeric':
        $sub_widgets['number'] = new TM('Number');
        $sub_widgets['textfield'] = new TM('Textfield');
        $sub_widgets['range'] = new TM('Range');
        break;

      case 'datetime_iso8601':
        $sub_widgets['datetime'] = new TM('Date');
        break;

    }

    return $sub_widgets;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return $element[\explode('.', $violation->getPropertyPath())[1]];
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldSettings(): array {
    $field_settings = parent::getFieldSettings();

    foreach (['first', 'second'] as $subfield) {
      $subfield_type = $field_settings['storage'][$subfield]['type'];
      if ($field_settings[$subfield]['list'] && !DoubleFieldItem::isListAllowed($subfield_type)) {
        $field_settings[$subfield]['list'] = FALSE;
      }
    }

    return $field_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = parent::getSettings();
    $field_settings = $this->getFieldSettings();

    foreach (['first', 'second'] as $subfield) {
      $widget_types = $this->getSubWidgets($field_settings['storage'][$subfield]['type'], $field_settings[$subfield]['list']);
      // Use the first eligible widget type unless it is set explicitly.
      if (!$settings[$subfield]['type']) {
        $settings[$subfield]['type'] = \key($widget_types);
      }
    }

    return $settings;
  }

  /**
   * Determines whether the widget can render subfield label.
   */
  private static function isLabelSupported(string $widget_type): bool {
    return $widget_type != 'checkbox';
  }

}
