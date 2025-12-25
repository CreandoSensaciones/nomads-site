<?php

namespace Drupal\currency\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\currency\Entity\CurrencyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles the "enable currency" route.
 *
 * @deprecated in currency:8.x-3.6 and is removed from currency:4.0.0.
 *    This controller is no longer used by the Currency module.
 * @see https://www.drupal.org/project/currency/issues/3551097
 */
class EnableCurrency extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    protected UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('url_generator'));
  }

  /**
   * Enables a currency.
   *
   * @param \Drupal\currency\Entity\CurrencyInterface $currency
   *   The currency entity to be enabled.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the currency collection page.
   */
  public function execute(CurrencyInterface $currency) {
    $currency->enable();
    $currency->save();

    return new RedirectResponse($this->urlGenerator->generateFromRoute('entity.currency.collection', [
      'absolute' => TRUE,
    ]));
  }

}
