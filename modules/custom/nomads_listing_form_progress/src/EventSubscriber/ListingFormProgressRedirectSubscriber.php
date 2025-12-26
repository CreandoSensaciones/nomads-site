<?php

namespace Drupal\nomads_listing_form_progress\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces listing form redirects to the details form flow.
 */
class ListingFormProgressRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Creates a new redirect subscriber.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -1000],
    ];
  }

  /**
   * Overrides listing form redirects after save.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$request->isMethod('POST')) {
      return;
    }

    $form_id = (string) $request->request->get('form_id', '');
    if ($form_id === '') {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse) {
      return;
    }

    $node_id = $this->extractNodeIdFromRedirect($response->getTargetUrl());
    if (!$node_id) {
      return;
    }

    if ($form_id === 'node_listing_form') {
      $listing = $this->entityTypeManager->getStorage('node')->load($node_id);
      if (!$listing instanceof NodeInterface || $listing->bundle() !== 'listing') {
        return;
      }

      $details_id = nomads_listing_form_progress_get_details_id((int) $listing->id(), $listing);
      if ($details_id) {
        $url = Url::fromRoute('entity.node.edit_form', ['node' => $details_id]);
        $event->setResponse(new RedirectResponse($url->setAbsolute()->toString()));
        return;
      }

      $url = Url::fromRoute('node.add', ['node_type' => 'details'], [
        'query' => ['listing_ref' => (int) $listing->id()],
      ]);
      $event->setResponse(new RedirectResponse($url->setAbsolute()->toString()));
      return;
    }

    if ($form_id === 'node_details_form') {
      $listing_id = (int) $request->query->get('listing_ref');
      if (!$listing_id) {
        $details = $this->entityTypeManager->getStorage('node')->load($node_id);
        if ($details instanceof NodeInterface && $details->hasField('field_listing_ref') && !$details->get('field_listing_ref')->isEmpty()) {
          $listing_id = (int) $details->get('field_listing_ref')->target_id;
        }
      }

      if ($listing_id) {
        $url = Url::fromRoute('entity.node.canonical', ['node' => $listing_id]);
        $event->setResponse(new RedirectResponse($url->setAbsolute()->toString()));
      }
    }
  }

  /**
   * Extract a node id from a redirect target URL.
   */
  protected function extractNodeIdFromRedirect(string $target_url): ?int {
    $parsed = UrlHelper::parse($target_url);
    $path = $parsed['path'] ?? '';
    if (preg_match('~^/node/(\\d+)(/|$)~', $path, $matches)) {
      return (int) $matches[1];
    }
    return NULL;
  }

}
