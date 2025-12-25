<?php

namespace Drupal\csv_importer\Plugin\Importer;

use Drupal\csv_importer\Plugin\ImporterBase;

/**
 * Class to import nodes.
 *
 * @Importer(
 *   id = "importer",
 *   label = @Translation("Importer"),
 *   deriver = "Drupal\csv_importer\Plugin\Derivative\ImporterDeriver"
 * )
 */
class Importer extends ImporterBase {}
