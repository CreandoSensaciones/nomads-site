<?php

declare(strict_types=1);

namespace Drupal\currency\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Regex;

/**
 * Currency number constraint.
 *
 * @Plugin(
 *   id = "CurrencyNumber",
 *   label = @Translation("Currency number"),
 *   type = { "string" }
 * )
 */
class CurrencyNumber extends Regex {

  /**
   * {@inheritdoc}
   */
  public string $message = '%currency_number is not a valid ISO 4217 currency number.';

  /**
   * {@inheritdoc}
   */
  public ?string $pattern = '/^[\d]{3}$/i';

}
