<?php

namespace Drupal\domain\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\domain\DomainNegotiatorInterface;

/**
 * Defines the DomainCacheContext service for "per domain" caching.
 */
class DomainCacheContext implements CalculatedCacheContextInterface {

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * Constructs a new DomainCacheContext service.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $domain_negotiator
   *   The domain negotiator.
   */
  public function __construct(DomainNegotiatorInterface $domain_negotiator) {
    $this->domainNegotiator = $domain_negotiator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Domain');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    return $this->domainNegotiator->getActiveId();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    return new CacheableMetadata();
  }

}
