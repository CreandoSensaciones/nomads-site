<?php

namespace Drupal\Tests\currency\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests token integration.
 *
 * @group Currency
 */
class Token extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'currency'];

  /**
   * Tests token integration.
   */
  public function testTokenIntegration() {
    $this->installConfig(['currency']);
    $token_service = \Drupal::token();

    $tokens = [
      '[currency:code]' => 'XXX',
      '[currency:number]' => '999',
      '[currency:subunits]' => '0',
    ];
    $data = [
      'currency' => 'XXX',
    ];
    foreach ($tokens as $token => $replacement) {
      $this->assertEquals($token_service->replace($token, $data), $replacement);
    }
  }

}
