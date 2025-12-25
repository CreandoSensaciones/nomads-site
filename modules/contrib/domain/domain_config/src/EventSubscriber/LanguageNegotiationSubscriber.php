<?php

namespace Drupal\domain_config\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\domain_config\DomainConfigOverrider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Implementation of the LanguageNegotiationSubscriber class.
 */
class LanguageNegotiationSubscriber implements EventSubscriberInterface {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The domain config overrider service.
   *
   * @var \Drupal\domain_config\DomainConfigOverrider
   */
  protected $configOverrider;

  public function __construct(LanguageManagerInterface $language_manager, DomainConfigOverrider $config_overrider) {
    $this->languageManager = $language_manager;
    $this->configOverrider = $config_overrider;
  }

  /**
   * Responds to the "kernel.request" event.
   */
  public function onKernelRequest(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    // Language negotiation is complete by now.
    $current_language = $this->languageManager->getCurrentLanguage();
    $this->configOverrider->setLanguage($current_language);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\language\EventSubscriber\LanguageRequestSubscriber::onKernelRequestLanguage()
   */
  public static function getSubscribedEvents() {
    // Run after language negotiation (which is around 255).
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 250];
    return $events;
  }

}
