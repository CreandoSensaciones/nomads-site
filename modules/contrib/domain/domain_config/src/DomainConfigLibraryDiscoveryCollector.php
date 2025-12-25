<?php

namespace Drupal\domain_config;

use Drupal\Core\Asset\LibraryDiscoveryCollector;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Reads library information.
 *
 * @package Drupal\domain_config
 */
class DomainConfigLibraryDiscoveryCollector extends LibraryDiscoveryCollector {

  /**
   * The active domain.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $domain;

  /**
   * Set a domain.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $domainNegotiator
   *   The domain negotiator.
   */
  public function setDomainNegotiator(DomainNegotiatorInterface $domainNegotiator) {
    $this->domain = $domainNegotiator->getActiveDomain();
  }

  /**
   * {@inheritdoc}
   */
  protected function getCid() {
    if (!isset($this->cid)) {
      $domain_id = 'null';
      if ($this->domain instanceof DomainInterface) {
        $domain_id = $this->domain->id();
      }
      $this->cid = 'library_info:' . $domain_id . ':' . $this->themeManager->getActiveTheme()->getName();
    }

    return $this->cid;
  }

}
