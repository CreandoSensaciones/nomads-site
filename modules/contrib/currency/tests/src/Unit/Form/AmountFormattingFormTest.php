<?php

namespace Drupal\Tests\currency\Unit\Form {

  use Drupal\Core\Config\Config;
  use Drupal\Core\Config\ConfigFactoryInterface;
  use Drupal\Core\Form\FormState;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Core\Messenger\MessengerInterface;
  use Drupal\Core\Render\Element\Radios;
  use Drupal\currency\Form\AmountFormattingForm;
  use Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterManagerInterface;
  use Drupal\Tests\UnitTestCase;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
   * Tests the amount formatting form.
   *
   * @coversDefaultClass \Drupal\currency\Form\AmountFormattingForm
   *
   * @group Currency
   */
  class AmountFormattingFormTest extends UnitTestCase {

    /**
     * The config factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $configFactory;

    /**
     * The controller under test.
     *
     * @var \Drupal\currency\Form\AmountFormattingForm
     */
    protected $controller;

    /**
     * The currency amount formatter manager.
     *
     * @var \Drupal\currency\Plugin\Currency\AmountFormatter\AmountFormatterManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $currencyAmountFormatterManager;

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
     * {@inheritdoc}
     */
    public function setUp(): void {
      parent::setUp();
      $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

      $this->currencyAmountFormatterManager = $this->createMock(AmountFormatterManagerInterface::class);

      $this->stringTranslation = $this->getStringTranslationStub();

      $this->messenger = $this->createMock(MessengerInterface::class);

      $this->controller = new AmountFormattingForm($this->configFactory, $this->stringTranslation, $this->currencyAmountFormatterManager);
      $this->controller->setMessenger($this->messenger);
    }

    /**
     * @covers ::create
     * @covers ::__construct
     */
    public function testCreate() {
      $container = $this->createMock(ContainerInterface::class);
      $map = [
        [
          'config.factory',
          ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
          $this->configFactory,
        ],
        [
          'plugin.manager.currency.amount_formatter',
          ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
          $this->currencyAmountFormatterManager,
        ],
        [
          'string_translation',
          ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
          $this->stringTranslation,
        ],
      ];
      $container->expects($this->any())
        ->method('get')
        ->willReturnMap($map);

      $sut = AmountFormattingForm::create($container);
      $this->assertInstanceOf(AmountFormattingForm::class, $sut);
    }

    /**
     * @covers ::getFormId
     */
    public function testGetFormId() {
      $this->assertSame('currency_amount_formatting', $this->controller->getFormId());
    }

    /**
     * @covers ::buildForm
     */
    public function testBuildForm() {
      $form = [];
      $form_state = $this->createMock(FormStateInterface::class);

      $definitions = [
        'foo' => [
          'label' => $this->randomMachineName(),
        ],
      ];

      $plugin_id = $this->randomMachineName();

      $this->currencyAmountFormatterManager->expects($this->once())
        ->method('getDefinitions')
        ->willReturn($definitions);

      $config = $this->createMock(Config::class);
      $config->expects($this->once())
        ->method('get')
        ->with('plugin_id')
        ->willReturn($plugin_id);

      $this->configFactory->expects($this->once())
        ->method('getEditable')
        ->with('currency.amount_formatting')
        ->willReturn($config);

      $this->stringTranslation->expects($this->any())
        ->method('translate')
        ->willReturnArgument(0);

      $expected = [
        '#default_value' => $plugin_id,
        '#options' => [
          'foo' => $definitions['foo']['label'],
        ],
        '#process' => [[Radios::class, 'processRadios'], [$this->controller, 'processPluginOptions']],
        '#type' => 'radios',
      ];
      $build = $this->controller->buildForm($form, $form_state);
      unset($build['default_plugin_id']['#title']);
      $this->assertSame($expected, $build['default_plugin_id']);
    }

    /**
     * @covers ::processPluginOptions
     */
    public function testProcessPluginOptions() {
      $element = [];

      $definitions = [
        'foo' => [
          'description' => $this->randomMachineName(),
        ],
        'bar' => [
          'description' => $this->randomMachineName(),
        ],
        // This must work without a description.
        'baz' => [],
      ];

      $this->currencyAmountFormatterManager->expects($this->once())
        ->method('getDefinitions')
        ->willReturn($definitions);

      $expected = [
        'foo' => [
          '#description' => $definitions['foo']['description'],
        ],
        'bar' => [
          '#description' => $definitions['bar']['description'],
        ],
      ];
      $this->assertSame($expected, $this->controller->processPluginOptions($element));
    }

    /**
     * @covers ::submitForm
     */
    public function testSubmitForm() {
      $plugin_id = $this->randomMachineName();

      $values = [
        'default_plugin_id' => $plugin_id,
      ];

      $form = [];
      $form_state = new FormState();
      $form_state->setValues($values);

      $config = $this->createMock(Config::class);
      $config->expects($this->atLeastOnce())
        ->method('set')
        ->with('plugin_id', $plugin_id);
      $config->expects($this->atLeastOnce())
        ->method('save');

      $this->configFactory->expects($this->atLeastOnce())
        ->method('getEditable')
        ->with('currency.amount_formatting')
        ->willReturn($config);

      $this->controller->submitForm($form, $form_state);
    }

  }

}
