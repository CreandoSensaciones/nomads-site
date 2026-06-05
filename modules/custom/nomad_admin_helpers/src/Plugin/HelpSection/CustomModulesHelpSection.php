<?php

namespace Drupal\nomad_admin_helpers\Plugin\HelpSection;

use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\help\Attribute\HelpSection;
use Drupal\help\Plugin\HelpSection\HookHelpSection;

/**
 * Provides the custom modules section for the help page.
 */
#[HelpSection(
  id: 'nomad_custom_modules',
  title: new TranslatableMarkup('Custom modules'),
  description: new TranslatableMarkup('Custom module overviews provided by modules in the modules/custom directory:'),
  weight: 1
)]
class CustomModulesHelpSection extends HookHelpSection {

  /**
   * {@inheritdoc}
   */
  public function listTopics(): array {
    $topics = [];
    $this->moduleHandler->invokeAllWith(
      'help',
      function (callable $hook, string $module) use (&$topics): void {
        if (!$this->isCustomModule($module)) {
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
