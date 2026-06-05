<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\area;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\domain_config\DomainConfigOverrider;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Base class for domain-specific configured text Views areas.
 */
abstract class DomainTextAreaBase extends AreaPluginBase {

  /**
   * Gets the nomads_navigation.settings key rendered by this area.
   */
  abstract protected function getConfigKey(): string;

  /**
   * Gets the HTML tag used to render this area.
   */
  abstract protected function getHtmlTag(): string;

  /**
   * Gets the wrapper CSS class.
   */
  abstract protected function getCssClass(): string;

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $text = $this->getConfiguredText();
    if ($text === '') {
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => $this->getHtmlTag(),
      '#value' => Markup::create(Xss::filterAdmin($text)),
      '#attributes' => [
        'class' => [$this->getCssClass()],
      ],
      '#cache' => [
        'contexts' => ['url.site', 'domain'],
        'tags' => $this->getConfigCacheTags(),
      ],
    ];
  }

  /**
   * Gets the configured text in active-domain precedence.
   */
  private function getConfiguredText(): string {
    foreach ($this->getConfigNames() as $config_name) {
      $data = \Drupal::service('config.storage')->read($config_name);
      if (is_array($data) && array_key_exists($this->getConfigKey(), $data)) {
        return trim((string) $data[$this->getConfigKey()]);
      }
    }

    return '';
  }

  /**
   * Gets config names in domain override order.
   *
   * @return string[]
   *   Config object names.
   */
  private function getConfigNames(): array {
    $config_names = [];
    if (\Drupal::hasService('domain.negotiator')) {
      $domain = \Drupal::service('domain.negotiator')->getActiveDomain();
      if ($domain && method_exists($domain, 'id')) {
        $config_names[] = DomainConfigOverrider::getConfigNameByDomain('nomads_navigation.settings', (string) $domain->id());
      }
    }

    $config_names[] = 'nomads_navigation.settings';

    return array_values(array_unique($config_names));
  }

  /**
   * Gets cache tags for config objects that can affect this area.
   *
   * @return string[]
   *   Config cache tags.
   */
  private function getConfigCacheTags(): array {
    return array_map(
      static fn (string $config_name): string => 'config:' . $config_name,
      $this->getConfigNames(),
    );
  }

}
