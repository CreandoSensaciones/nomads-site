<?php

namespace Drupal\domain;

/**
 * Defines events for working with domain.
 */
final class DomainEvents {

  /**
   * Event dispatched when an active domain has been negotiated.
   *
   * @Event
   *
   * @var string
   */
  const ACTIVE_DOMAIN_SET = 'domain.active_domain_set';

}
