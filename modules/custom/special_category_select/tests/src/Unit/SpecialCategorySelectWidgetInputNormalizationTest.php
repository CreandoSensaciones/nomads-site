<?php

namespace Drupal\Tests\special_category_select\Unit;

require_once __DIR__ . '/../../../src/Plugin/Field/FieldWidget/SpecialCategorySelectWidget.php';

use Drupal\special_category_select\Plugin\Field\FieldWidget\SpecialCategorySelectWidget;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\special_category_select\Plugin\Field\FieldWidget\SpecialCategorySelectWidget
 */
class SpecialCategorySelectWidgetInputNormalizationTest extends TestCase {

  /**
   * Calls protected normalizeInputValues().
   */
  private function normalize(array $values): array {
    $method = new \ReflectionMethod(SpecialCategorySelectWidget::class, 'normalizeInputValues');
    $method->setAccessible(TRUE);
    return $method->invoke(NULL, $values);
  }

  /**
   * @covers ::normalizeInputValues
   */
  public function testNormalizesExplicitTargetIdsWithOrder(): void {
    $normalized = $this->normalize([
      '_order' => '20,10',
      0 => ['target_id' => '10'],
      1 => ['target_id' => '20'],
    ]);

    $this->assertSame([
      ['target_id' => 20],
      ['target_id' => 10],
    ], $normalized);
  }

  /**
   * @covers ::normalizeInputValues
   */
  public function testFallsBackToCfValuesWhenTargetIdsMissing(): void {
    $normalized = $this->normalize([
      '_cf_values' => "44\n55\n",
    ]);

    $this->assertSame([
      ['target_id' => 44],
      ['target_id' => 55],
    ], $normalized);
  }

  /**
   * @covers ::normalizeInputValues
   */
  public function testIgnoresInvalidOrZeroValues(): void {
    $normalized = $this->normalize([
      '_cf_values' => "0\nabc\n91\n",
    ]);

    $this->assertSame([
      ['target_id' => 91],
    ], $normalized);
  }

}

