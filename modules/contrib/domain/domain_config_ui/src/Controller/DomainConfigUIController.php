<?php

namespace Drupal\domain_config_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\domain\Controller\DomainControllerBase;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainStorageInterface;
use Drupal\domain_config_ui\DomainConfigUITrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller routines for AJAX callbacks for domain actions.
 */
class DomainConfigUIController extends DomainControllerBase {

  use DomainConfigUITrait;
  use StringTranslationTrait;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new DomainControllerBase.
   *
   * @param \Drupal\domain\DomainStorageInterface $domain_storage
   *   The storage controller.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\CachedStorage $config_storage
   *   Config storage.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    DomainStorageInterface $domain_storage,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    CachedStorage $config_storage,
    MessengerInterface $messenger,
    PathMatcherInterface $path_matcher,
    RequestStack $request_stack,
  ) {
    parent::__construct($domain_storage, $entity_type_manager);
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->messenger = $messenger;
    $this->pathMatcher = $path_matcher;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('domain'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('messenger'),
      $container->get('path.matcher'),
      $container->get('request_stack')
    );
  }

  /**
   * Handles AJAX operations to add/remove configuration forms.
   *
   * @param string $route_name
   *   The route from which the AJAX request was triggered.
   * @param string $op
   *   The operation being performed, either 'enable' to enable the form,
   *   'disable' to disable the domain form, or 'remove' to disable the form
   *   and remove its stored configurations.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to redirect back to the calling form.
   *   Supported by the UrlGeneratorTrait.
   */
  public function ajaxOperation($route_name, $op) {
    $success = FALSE;
    $message = '';
    // Get the query string for the return URL.
    $query = $this->requestStack->getCurrentRequest()->getQueryString();
    $params = [];
    $parts = explode('&', $query);
    foreach ($parts as $part) {
      $element = explode('=', $part);
      if ($element[0] !== 'token') {
        $params[$element[0]] = $element[1];
      }
    }
    $url = Url::fromRoute($route_name, $params);

    // Get current module settings.
    $config = $this->configFactory->getEditable('domain_config_ui.settings');
    $path_pages = $this->standardizePaths($config->get('path_pages'));
    $new_path = '/' . $url->getInternalPath();

    if (!$url->isExternal() && $url->access()) {
      switch ($op) {
        case 'enable':
          // Check to see if we already registered this form.
          if (!$exists = $this->pathMatcher->matchPath($new_path, $path_pages)) {
            $this->addPath($new_path);
            $message = $this->t('Form added to domain configuration interface.');
            $success = TRUE;
          }
          break;

        case 'disable':
          if ($exists = $this->pathMatcher->matchPath($new_path, $path_pages)) {
            $this->removePath($new_path);
            $message = $this->t('Form removed from domain configuration interface.');
            $success = TRUE;
          }
          break;
      }
    }
    // Set a message.
    if ($success) {
      $this->messenger->addMessage($message);
    }
    else {
      $this->messenger->addError($this->t('The operation failed.'));
    }
    // Return to the invoking page.
    return new RedirectResponse($url->toString(), 302);
  }

  /**
   * Lists all stored configuration.
   */
  public function overview() {
    $elements = [];
    $page = [
      'table' => [
        '#type' => 'table',
        '#header' => [
          'name' => t('Configuration key'),
          'item' => t('Item'),
          'domain' => t('Domain'),
          'language' => t('Language'),
          'actions' => t('Actions'),
        ],
      ],
    ];
    foreach ($this->configStorage->listAll('domain.config') as $name) {
      $elements[] = self::deriveElements($name);
    }
    // Sort the items.
    if ($elements !== []) {
      uasort($elements, [$this, 'sortItems']);
      foreach ($elements as $element) {
        $operations = [
          'inspect' => [
            'url' => Url::fromRoute('domain_config_ui.inspect', ['config_name' => $element['name']]),
            'title' => $this->t('Inspect'),
          ],
          'delete' => [
            'url' => Url::fromRoute('domain_config_ui.delete', ['config_name' => $element['name']]),
            'title' => $this->t('Delete'),
          ],
        ];
        $page['table'][] = [
          'name' => ['#markup' => $element['name']],
          'item' => ['#markup' => $element['item']],
          'domain' => ['#markup' => $element['domain']],
          'language' => ['#markup' => $element['language']],
          'actions' => ['#type' => 'operations', '#links' => $operations],
        ];
      }
    }
    else {
      $page = [
        '#markup' => $this->t('No domain-specific configurations have been found.'),
      ];
    }
    return $page;
  }

  /**
   * Controller for inspecting configuration.
   *
   * @param string $config_name
   *   The domain config object being inspected.
   */
  public function inspectConfig($config_name = NULL) {
    if (is_null($config_name)) {
      $url = Url::fromRoute('domain_config_ui.list');
      return new RedirectResponse($url->toString());
    }
    $elements = self::deriveElements($config_name);
    $config = $this->configFactory->get($config_name)->getRawData();
    if ($elements['language'] === $this->t('all')->render()) {
      $language = $this->t('all languages');
    }
    else {
      $language = $this->t('the @language language.', ['@language' => $elements['language']]);
    }
    $page = [
      'help' => [
        '#type' => 'item',
        '#title' => Html::escape($config_name),
        '#markup' => $this->t('This configuration is for the %domain domain and
          applies to %language.', [
            '%domain' => $elements['domain'],
            '%language' => $language,
          ]
        ),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];
    $page['text'] = [
      '#markup' => self::printArray($config),
    ];
    return $page;
  }

  /**
   * Derives the parts of a config object for presentation.
   *
   * @param string $name
   *   A configuration object name.
   *
   * @return array
   *   An array of config values, keyed by name.
   */
  public static function deriveElements($name) {
    $items = explode('.', $name);
    $elements = [
      'prefix' => $items[0],
      'config' => isset($items[1]) && isset($items[2]) ? $items[1] : NULL,
      'domain' => isset($items[2]) && isset($items[3]) ? $items[2] : NULL,
      'language' => isset($items[3]) && isset($items[4]) && strlen($items[3]) === 2 ? $items[3] : NULL,
    ];

    $elements['item'] = trim(str_replace($elements, '', $name), '.');

    if (!is_null($elements['domain'])) {
      $storage = \Drupal::entityTypeManager()->getStorage('domain');
      $domain = $storage->load($elements['domain']);
      if ($domain instanceof DomainInterface) {
        $elements['domain'] = $domain->label();
      }
    }

    if (is_null($elements['language'])) {
      // Static context requires use of t() here.
      $elements['language'] = t('all')->render();
    }
    else {
      $language = \Drupal::languageManager()->getLanguage($elements['language']);
      if (!is_null($language)) {
        $elements['language'] = $language->getName();
      }
    }

    $elements['name'] = $name;

    return $elements;
  }

  /**
   * Sorts items by parent config.
   */
  public function sortItems($a, $b) {
    return strcmp($a['item'], $b['item']);
  }

  /**
   * Prints array data for the form.
   *
   * @param array $array
   *   An array of data. Note that we support two levels of nesting.
   *
   * @return string
   *   A suitable output string.
   */
  public static function printArray(array $array) {
    $items = [];
    foreach ($array as $key => $val) {
      if (!is_array($val)) {
        $value = self::formatValue($val);
        $item = [
          '#theme' => 'item_list',
          '#items' => [$value],
          '#title' => self::formatValue($key),
        ];
        $items[] = \Drupal::service('renderer')->render($item);
      }
      else {
        $list = [];
        foreach ($val as $k => $v) {
          $list[] = t('<strong>@key</strong> : @value', [
            '@key' => $k,
            '@value' => self::formatValue($v),
          ]);
        }
        $variables = [
          '#theme' => 'item_list',
          '#items' => $list,
          '#title' => self::formatValue($key),
        ];
        $items[] = \Drupal::service('renderer')->render($variables);
      }
    }
    $rendered = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    return \Drupal::service('renderer')->render($rendered);
  }

  /**
   * Formats a value as a string, for readable output.
   *
   * Taken from config_inspector module.
   *
   * @param mixed $value
   *   The value element.
   *
   * @return string
   *   The value in string form.
   */
  protected static function formatValue($value) {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
      return Html::escape($value);
    }
    // @phpstan-ignore-next-line
    if (empty($value)) {
      return '<' . t('empty') . '>';
    }

    return '<' . gettype($value) . '>';
  }

}
