<?php

namespace Drupal\domain\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CacheableRouteProviderInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\Access\DomainAccessCheck;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the domain context for an http request.
 */
class DomainSubscriber implements EventSubscriberInterface {

  /**
   * The domain negotiator service.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $domainNegotiator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Domain storage handler service.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * The core access check service.
   *
   * @var \Drupal\Core\Access\AccessCheckInterface
   */
  protected $accessCheck;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The route provider to load routes by name.
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * Constructs a DomainSubscriber object.
   *
   * @param \Drupal\domain\DomainNegotiatorInterface $negotiator
   *   The domain negotiator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\domain\Access\DomainAccessCheck $access_check
   *   The access check interface.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   */
  public function __construct(DomainNegotiatorInterface $negotiator, EntityTypeManagerInterface $entity_type_manager, DomainAccessCheck $access_check, AccountInterface $account, RouteProviderInterface $route_provider) {
    $this->domainNegotiator = $negotiator;
    $this->entityTypeManager = $entity_type_manager;
    $this->domainStorage = $this->entityTypeManager->getStorage('domain');
    $this->accessCheck = $access_check;
    $this->account = $account;
    $this->routeProvider = $route_provider;
  }

  /**
   * Sets the domain context of the request.
   *
   * This method also determines the redirect status for the http request.
   *
   * Specifically, here we determine if a redirect is required. That happens
   * in one of two cases: an unauthorized request to an inactive domain is made;
   * a domain alias is set to redirect to its primary domain record.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   *
   * @see domain_alias_domain_request_alter
   */
  public function onKernelRequestDomain(RequestEvent $event) {
    // Negotiate the request and set domain context.
    $domain = $this->domainNegotiator->getActiveDomain();
    if ($domain instanceof DomainInterface) {
      if ($this->routeProvider instanceof CacheableRouteProviderInterface) {
        $this->routeProvider->addExtraCacheKeyPart('domain', $domain->id());
      }
      $domain_hostname = $domain->getHostname();
      $domain_url = $domain->getUrl();
      $redirect_status = $domain->getRedirect();
      // If domain negotiation asked for a redirect, issue it.
      if (
        is_null($redirect_status)
        && $this->accessCheck instanceof DomainAccessCheck
        && $this->accessCheck->checkPath($event->getRequest()->getPathInfo())
      ) {
        // Else check for active domain or inactive access.
        $access = $this->accessCheck->access($this->account);
        // If the access check fails, reroute to the default domain.
        // Note that Allowed, Neutral, and Failed are the options here.
        // We insist on Allowed.
        if (!$access->isAllowed()) {
          /** @var \Drupal\domain\DomainInterface $default_domain */
          $default_domain = $this->domainStorage->loadDefaultDomain();
          $domain_hostname = $default_domain->getHostname();
          $domain_url = $default_domain->getUrl();
          $redirect_status = Response::HTTP_FOUND;
        }
      }
      if ($redirect_status > 0) {
        // Pass a redirect if necessary.
        if (DomainRedirectResponse::checkTrustedHost($domain_hostname)) {
          $response = new TrustedRedirectResponse($domain_url, $redirect_status);
        }
        else {
          // If the redirect is not to a registered hostname, reject the
          // request.
          $response = new Response(
            'The provided host name is not a valid redirect.',
            Response::HTTP_UNAUTHORIZED
          );
        }
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    // This needs to fire very early in the stack, before accounts are cached.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestDomain', 50];

    return $events;
  }

}
