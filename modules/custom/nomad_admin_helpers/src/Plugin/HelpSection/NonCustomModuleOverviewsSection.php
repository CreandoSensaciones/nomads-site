<?php

namespace Drupal\nomad_admin_helpers\Plugin\HelpSection;

use Drupal\Core\Link;
use Drupal\help\Plugin\HelpSection\HookHelpSection;

/**
 * Replaces core's module overviews section with a non-custom module list.
 */
class NonCustomModuleOverviewsSection extends HookHelpSection {

  /**
   * {@inheritdoc}
   */
  public function listTopics(): array {
    $topics = [];
    $this->moduleHandler->invokeAllWith(
      'help',
      function (callable $hook, string $module) use (&$topics): void {
        if ($this->isCustomModule($module)) {
          return;
        }
        $title = $this->moduleExtensionList->getName($module);
        $topics[$title] = Link::createFromRoute($title, 'help.page', ['name' => $module]);
      }
    );

    ksort($topics);
    return $topics;
  }

  /**
   * Checks whether an enabled module lives in a custom modules directory.
   */
  protected function isCustomModule(string $module): bool {
    $path = str_replace('\\', '/', $this->moduleExtensionList->getPath($module));
    return str_starts_with($path, 'modules/custom/') || str_contains($path, '/modules/custom/');
  }

}
