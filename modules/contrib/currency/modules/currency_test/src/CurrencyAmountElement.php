<?php

namespace Drupal\currency_test;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to test the currency_amount element.
 */
class CurrencyAmountElement implements FormInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency_test_currency_amount_element';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $minimum_amount = NULL, $maximum_amount = NULL, $currency_code = NULL) {
    // Nest the element to make sure that works.
    $form['container'] = [
      '#tree' => TRUE,
    ];
    $form['container']['amount'] = [
      '#limit_currency_codes' => $currency_code ? [$currency_code] : [],
      '#type' => 'currency_amount',
      '#title' => $this->t('Foo amount'),
      '#minimum_amount' => $minimum_amount,
      '#maximum_amount' => $maximum_amount,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->state->set('currency_test_currency_amount_element', $values['container']['amount']);
  }

}
