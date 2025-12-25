<?php

declare(strict_types=1);

namespace Drupal\double_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup as TM;

/**
 * Base class for list formatters.
 */
abstract class ListBase extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['inline' => TRUE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $settings = $this->getSettings();

    $element['inline'] = [
      '#type' => 'checkbox',
      '#title' => new TM('Display as inline element'),
      '#default_value' => $settings['inline'],
      '#weight' => -10,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($this->getSetting('inline')) {
      $summary[] = new TM('Display as inline element');
    }
    return array_merge($summary, parent::settingsSummary());
  }

}
