<?php

declare(strict_types=1);

namespace Drupal\currency\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Regex;

/**
 * Currency code constraint.
 *
 * @Plugin(
 *   id = "CurrencyCode",
 *   label = @Translation("Currency code"),
 *   type = { "string" }
 * )
 */
class CurrencyCode extends Regex {

  /**
   * {@inheritdoc}
   */
  public string $message = '%currency_code is not a valid ISO 4217 currency code.';

  /**
   * {@inheritdoc}
   */
  public ?string $pattern = '/^[A-Z]{3}$/i';

}
