<?php

namespace Drupal\domain;

use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a domain event for event listeners.
 */
class DomainEvent extends Event {

  /**
   * Domain object.
   *
   * @var \Drupal\domain\DomainInterface|null
   */
  protected $domain;

  /**
   * Constructs a domain event object.
   *
   * @param \Drupal\domain\DomainInterface|null $domain
   *   Domain object.
   */
  public function __construct(?DomainInterface $domain) {
    $this->domain = $domain;
  }

  /**
   * Gets the domain object.
   *
   * @return \Drupal\domain\DomainInterface|null
   *   The domain object that caused the event to fire.
   */
  public function getDomain() {
    return $this->domain;
  }

}
