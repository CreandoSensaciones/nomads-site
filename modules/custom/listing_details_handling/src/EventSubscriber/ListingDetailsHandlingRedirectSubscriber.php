<?php

namespace Drupal\listing_details_handling\EventSubscriber;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Overrides listing form redirects when "Add details" is used.
 */
class ListingDetailsHandlingRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -1000],
    ];
  }

  /**
   * Response handler for listing add/edit submissions.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if ($request->getMethod() !== 'POST') {
      return;
    }

    if ($request->request->get('form_id') !== 'node_listing_form') {
      return;
    }

    if (!$request->request->has('add_details')) {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse) {
      return;
    }

    $listing_id = $this->extractNodeId($response->getTargetUrl());
    if (!$listing_id) {
      return;
    }

    $listing = Node::load($listing_id);
    if (!$listing) {
      return;
    }

    if (!$listing->hasField('field_listing_ref') || $listing->get('field_listing_ref')->isEmpty()) {
      return;
    }

    $details_id = (int) $listing->get('field_listing_ref')->target_id;
    if (!$details_id) {
      return;
    }

    $url = Url::fromRoute('entity.node.edit_form', [
      'node' => $details_id,
    ]);

    $event->setResponse(new RedirectResponse($url->setAbsolute()->toString()));
  }

  /**
   * Extract a node id from a redirect URL.
   */
  private function extractNodeId(string $url): ?int {
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) {
      return NULL;
    }

    if (preg_match('#/node/(\\d+)$#', $path, $matches)) {
      return (int) $matches[1];
    }

    return NULL;
  }

}
