<?php

namespace Drupal\Tests\nomads_fitted_pills\Unit;

require_once __DIR__ . '/../../../src/Packer/FittedPillsPacker.php';

use Drupal\nomads_fitted_pills\Packer\FittedPillsPacker;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\nomads_fitted_pills\Packer\FittedPillsPacker
 */
class FittedPillsPackerTest extends TestCase {

  /**
   * @covers ::pack
   */
  public function testGreedyPacking(): void {
    $packer = new FittedPillsPacker();
    $items = [
      ['label' => 'aaaa'],
      ['label' => 'bbb'],
      ['label' => 'cc'],
    ];

    $rows = $packer->pack($items, [
      'max_chars_per_row' => 10,
      'pill_overhead' => 1,
      'trigger_gap' => 3,
      'min_improvement' => 2,
    ]);

    $this->assertCount(2, $rows);
    $this->assertSame(['aaaa', 'bbb'], array_column($rows[0], 'label'));
    $this->assertSame(['cc'], array_column($rows[1], 'label'));
  }

  /**
   * @covers ::pack
   */
  public function testBestFitSwapApplied(): void {
    $packer = new FittedPillsPacker();
    $items = [
      ['label' => 'aaaaa'],
      ['label' => 'bbbbb'],
      ['label' => 'cccc'],
      ['label' => 'dd'],
    ];

    $rows = $packer->pack($items, [
      'max_chars_per_row' => 15,
      'pill_overhead' => 1,
      'trigger_gap' => 3,
      'min_improvement' => 2,
    ]);

    $this->assertCount(2, $rows);
    $this->assertSame(['aaaaa', 'bbbbb', 'dd'], array_column($rows[0], 'label'));
    $this->assertSame(['cccc'], array_column($rows[1], 'label'));
  }

  /**
   * @covers ::pack
   */
  public function testNoSwapWhenImprovementTooSmall(): void {
    $packer = new FittedPillsPacker();
    $items = [
      ['label' => 'aaaaa'],
      ['label' => 'bbbbb'],
      ['label' => 'cccc'],
      ['label' => 'dd'],
    ];

    $rows = $packer->pack($items, [
      'max_chars_per_row' => 15,
      'pill_overhead' => 1,
      'trigger_gap' => 3,
      'min_improvement' => 4,
    ]);

    $this->assertCount(2, $rows);
    $this->assertSame(['aaaaa', 'bbbbb'], array_column($rows[0], 'label'));
    $this->assertSame(['cccc', 'dd'], array_column($rows[1], 'label'));
  }

  /**
   * @covers ::packWithShortPool
   */
  public function testShortPoolCutoffConstant(): void {
    $this->assertSame(10, FittedPillsPacker::SHORT_POOL_MAX_LABEL_LEN);

    $packer = new FittedPillsPacker();
    $long_items = [
      ['label' => 'VeryLongLabel'],
    ];
    $short_pool = [
      ['label' => 'Elevator'],
    ];

    $rows = $packer->packWithShortPool($long_items, $short_pool, [
      'max_chars_per_row' => 24,
      'pill_overhead' => 1,
    ]);

    $this->assertSame(['VeryLongLabel', 'Elevator'], array_column($rows[0], 'label'));
  }

  /**
   * @covers ::packWithShortPool
   */
  public function testBottomUpFilling(): void {
    $packer = new FittedPillsPacker();
    $long_items = [
      ['label' => 'LongOne'],
      ['label' => 'LongTwo'],
      ['label' => 'LongTri'],
    ];
    $short_pool = [
      ['label' => 'AA'],
      ['label' => 'BB'],
    ];

    $rows = $packer->packWithShortPool($long_items, $short_pool, [
      'max_chars_per_row' => 11,
      'pill_overhead' => 1,
    ]);

    $this->assertCount(3, $rows);
    $this->assertCount(1, $rows[0]);
    $this->assertCount(2, $rows[1]);
    $this->assertCount(2, $rows[2]);
  }

}
