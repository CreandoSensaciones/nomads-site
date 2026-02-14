<?php

declare(strict_types=1);

namespace Drupal\nomads_tiles\Plugin\field_group\FieldGroupFormatter;

use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'nomads_data_tile' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "nomads_data_tile",
 *   label = @Translation("Nomads Data Tile"),
 *   description = @Translation("Suppresses rendering; children are consumed by the Nomads Tiles formatter."),
 *   supported_contexts = {
 *     "view"
 *   }
 * )
 */
class NomadsDataTile extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    return [
      'show_label' => FALSE,
    ] + parent::defaultContextSettings($context);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();

    $form['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show label'),
      '#default_value' => $this->getSetting('show_label'),
      '#weight' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('show_label')) {
      $summary[] = $this->t('Show label');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    $element = [];
  }

}
