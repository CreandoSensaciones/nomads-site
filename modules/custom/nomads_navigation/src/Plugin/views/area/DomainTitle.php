<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\area;

use Drupal\views\Attribute\ViewsArea;

/**
 * Renders the configured domain-specific View title.
 */
#[ViewsArea('nomads_navigation_domain_title')]
final class DomainTitle extends DomainTextAreaBase {

  /**
   * {@inheritdoc}
   */
  protected function getConfigKey(): string {
    return 'view_title';
  }

  /**
   * {@inheritdoc}
   */
  protected function getHtmlTag(): string {
    return 'h1';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCssClass(): string {
    return 'nomads-navigation-domain-title';
  }

}
