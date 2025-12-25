<?php

namespace Drupal\currency_test;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to test the currency_sign element.
 */
class CurrencySignElement implements FormInterface, ContainerInjectionInterface {

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
    return 'currency_test_currency_sign_element';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $currency_code = NULL, $currency_sign = NULL) {
    // Nest the element to make sure that works.
    $form['container'] = [
      '#tree' => TRUE,
    ];
    $form['container']['sign'] = [
      '#currency_code' => $currency_code ? $currency_code : FALSE,
      '#default_value' => $currency_sign,
      '#type' => 'currency_sign',
      '#title' => $this->t('Foo sign'),
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
    $this->state->set('currency_test_currency_sign_element', $values['container']['sign']);
  }

}
