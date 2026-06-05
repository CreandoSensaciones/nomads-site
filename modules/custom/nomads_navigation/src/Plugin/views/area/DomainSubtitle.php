<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\area;

use Drupal\views\Attribute\ViewsArea;

/**
 * Renders the configured domain-specific View subtitle.
 */
#[ViewsArea('nomads_navigation_domain_subtitle')]
final class DomainSubtitle extends DomainTextAreaBase {

  /**
   * {@inheritdoc}
   */
  protected function getConfigKey(): string {
    return 'view_subtitle';
  }

  /**
   * {@inheritdoc}
   */
  protected function getHtmlTag(): string {
    return 'h3';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCssClass(): string {
    return 'nomads-navigation-domain-subtitle';
  }

}
