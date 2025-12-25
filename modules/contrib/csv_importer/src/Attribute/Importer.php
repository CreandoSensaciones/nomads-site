<?php

namespace Drupal\csv_importer\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Importer attribute object.
 *
 * Plugin namespace: Plugin\Importer.
 *
 * @see \Drupal\csv_importer\Plugin\ImporterManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Importer extends Plugin {

  /**
   * Constructs an Importer attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the importer.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the importer.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
