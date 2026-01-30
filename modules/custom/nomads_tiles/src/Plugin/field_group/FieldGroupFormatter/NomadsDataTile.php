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
  public function preRender(&$element, $rendering_object) {
    $element = [];
  }

}
