<?php

namespace Drupal\Tests\currency\Unit\Entity\Currency {

  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Core\Messenger\MessengerInterface;
  use Drupal\Core\StringTranslation\TranslatableMarkup;
  use Drupal\Core\Url;
  use Drupal\currency\Entity\Currency\CurrencyDeleteForm;
  use Drupal\currency\Entity\CurrencyInterface;
  use Drupal\Tests\UnitTestCase;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
   * Tests the currency delete form.
   *
   * @coversDefaultClass \Drupal\currency\Entity\Currency\CurrencyDeleteForm
   *
   * @group Currency
   */
  class CurrencyDeleteFormTest extends UnitTestCase {

    /**
     * The currency.
     *
     * @var \Drupal\currency\Entity\CurrencyInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $currency;

    /**
     * The string translator.
     *
     * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $stringTranslation;

    /**
     * The messenger.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $messenger;

    /**
     * The class under test.
     *
     * @var \Drupal\currency\Entity\Currency\CurrencyDeleteForm
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void {
      parent::setUp();
      $this->currency = $this->createMock(CurrencyInterface::class);

      $this->stringTranslation = $this->getStringTranslationStub();

      $this->messenger = $this->createMock(MessengerInterface::class);

      $this->sut = new CurrencyDeleteForm($this->stringTranslation);
      $this->sut->setEntity($this->currency);
      $this->sut->setMessenger($this->messenger);
    }

    /**
     * @covers ::create
     * @covers ::__construct
     */
    public function testCreate() {
      $container = $this->createMock(ContainerInterface::class);
      $container->expects($this->once())
        ->method('get')
        ->with('string_translation')
        ->willReturn($this->stringTranslation);

      $sut = CurrencyDeleteForm::create($container);
      $this->assertInstanceOf(CurrencyDeleteForm::class, $sut);
    }

    /**
     * @covers ::getQuestion
     */
    public function testGetQuestion() {
      $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getQuestion());
    }

    /**
     * @covers ::getConfirmText
     */
    public function testGetConfirmText() {
      $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getConfirmText());
    }

    /**
     * @covers ::getCancelUrl
     */
    public function testGetCancelUrl() {
      $url = $this->sut->getCancelUrl();
      $this->assertInstanceOf(Url::class, $url);
      $this->assertSame('entity.currency.collection', $url->getRouteName());
    }

    /**
     * @covers ::submitForm
     */
    public function testSubmitForm() {
      $this->currency->expects($this->once())
        ->method('delete');

      $form = [];
      $form_state = $this->createMock(FormStateInterface::class);
      $form_state->expects($this->once())
        ->method('setRedirectUrl');

      $this->sut->submitForm($form, $form_state);
    }

  }

}
