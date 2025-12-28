<?php

namespace Drupal\masonry\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;

/**
 * Wrapper methods for Masonry API methods.
 *
 * @ingroup masonry
 */
class MasonryService {
  use StringTranslationTrait;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a MasonryService object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory) {
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Get default Masonry options.
   *
   * @return array
   *   An associative array of default options for Masonry.
   *   Contains:
   *   - layoutColumnWidth: The width of each column (in pixels, percentage or as
   *     a CSS selector).
   *   - gutterWidth: The spacing between each column (in pixels, percentage or as
   *     a CSS selector).
   *   - isLayoutResizable: Automatically rearrange items when the container is
   *     resized.
   *   - isLayoutAnimated: Animate item rearrangements.
   *   - layoutAnimationDuration: The duration of animations (in milliseconds).
   *   - isLayoutFitsWidth: Sets the width of the container to the nearest
   *     column. Ideal for centering Masonry layouts.
   *   - isLayoutRtlMode: Display items from right-to-left.
   *   - isLayoutImagesLoadedFirst: Load all images first before triggering
   *     Masonry.
   *   - isLayoutImagesLazyLoaded: Custom observer to support layout rebuild in
   *     lazysizes images lazy loading.
   *   - imageLazyloadSelector: lazyLoad class selector used by lazysizes.
   *   - imageLazyloadedSelector: lazyLoaded class selector used by lazysizes.
   *   - stampSelector: Specifies which elements are stamped within the layout
   *     using css selector.
   *   - isItemsWidthForce: Forces the items width to column width.
   *   - isItemsPositionInPercent: Sets item positions in percent values, rather
   *     than pixel values.
   */
  public function getMasonryDefaultOptions() {
    $options = [
      'layoutColumnWidth' => '',
      'gutterWidth' => '0',
      'isLayoutResizable' => TRUE,
      'isLayoutAnimated' => TRUE,
      'layoutAnimationDuration' => 500,
      'isLayoutFitsWidth' => FALSE,
      'isLayoutRtlMode' => ($this->languageManager->getCurrentLanguage()->getDirection() == LanguageInterface::DIRECTION_RTL),
      'isLayoutImagesLoadedFirst' => TRUE,
      'isLayoutImagesLazyLoaded' => FALSE,
      'imageLazyloadSelector' => 'lazyload',
      'imageLazyloadedSelector' => 'lazyloaded',
      'stampSelector' => '',
      'isItemsWidthForce' => TRUE,
      'isItemsPositionInPercent' => FALSE,
      'extraOptions' => [],
    ];

    // Loazyloading classes are auto-calculated for user simplicity. When
    // lazysizes is used without a Drupal module, this means DX is able to use
    // hook_masonry_default_options_alter or hook_masonry_options_form_alter to
    // override this setting.
    if ($this->moduleHandler->moduleExists('lazy')) {
      $config = $this->configFactory->get('lazy.settings');
      $options['imageLazyloadSelector'] = $config->get('lazysizes.lazyClass');
      $options['imageLazyloadedSelector'] = $config->get('lazysizes.loadedClass');
    }

    return $options;
  }

  /**
   * Apply Masonry to a container.
   *
   * @param array $form
   *   The form to which the JS will be attached.
   * @param string $container
   *   The CSS selector of the container element to apply Masonry to.
   * @param string $item_selector
   *   The CSS selector of the items within the container.
   * @param array $options
   *   An associative array of Masonry options.
   * @param string[] $masonry_ids
   *   Some optional IDs to target this particular display in
   *   hook_masonry_script_alter().
   */
  public function applyMasonryDisplay(array &$form, string $container, string $item_selector, array $options = [], array $masonry_ids = ['masonry_default']) {

    if (!empty($container)) {
      // For any options not specified, use default options.
      $options += $this->getMasonryDefaultOptions();
      if (!isset($item_selector)) {
        $item_selector = '';
      }

      // Rework column width to determine the choosen unit.
      $options['layoutColumnWidth'] = str_replace(' ', '', $options['layoutColumnWidth']);
      $options['layoutColumnWidthUnit'] = 'css';
      if ($this->endsWith($options['layoutColumnWidth'], 'px')) {
        $options['layoutColumnWidthUnit'] = 'px';
        $options['layoutColumnWidth'] = str_replace('px', '', $options['layoutColumnWidth']);
      } else if ($this->endsWith($options['layoutColumnWidth'], '%')) {
        $options['layoutColumnWidthUnit'] = '%';
        $options['layoutColumnWidth'] = str_replace('%', '', $options['layoutColumnWidth']);
      }

      // Rework gutter width to determine the choosen unit.
      $options['gutterWidth'] = str_replace(' ', '', $options['gutterWidth']);
      $options['gutterWidthUnit'] = 'css';
      if ($this->endsWith($options['gutterWidth'], 'px')) {
        $options['gutterWidthUnit'] = 'px';
        $options['gutterWidth'] = str_replace('px', '', $options['gutterWidth']);
      } else if ($this->endsWith($options['gutterWidth'], '%')) {
        $options['gutterWidthUnit'] = '%';
        $options['gutterWidth'] = str_replace('%', '', $options['gutterWidth']);
      }

      // Setup Masonry script.
      $masonry = [
        'masonry' => [
          $container => [
            'masonry_ids' => $masonry_ids,
            'item_selector' => $item_selector,
            'column_width' => $options['layoutColumnWidth'],
            'column_width_units' => $options['layoutColumnWidthUnit'],
            'gutter_width' => $options['gutterWidth'],
            'gutter_width_units' => $options['gutterWidthUnit'],
            'resizable' => (bool) $options['isLayoutResizable'],
            'animated' => (bool) $options['isLayoutAnimated'],
            'animation_duration' => (int) $options['layoutAnimationDuration'],
            'fit_width' => (bool) $options['isLayoutFitsWidth'],
            'rtl' => (bool) $options['isLayoutRtlMode'],
            'images_first' => (bool) $options['isLayoutImagesLoadedFirst'],
            'images_lazyload' => (bool) $options['isLayoutImagesLazyLoaded'],
            'lazyload_selector' => $options['imageLazyloadSelector'],
            'lazyloaded_selector' => $options['imageLazyloadedSelector'],
            'stamp' => $options['stampSelector'],
            'force_width' => (bool) $options['isItemsWidthForce'],
            'percent_position' => (bool) $options['isItemsPositionInPercent'],
            'extra_options' => $options['extraOptions'],
          ],
        ],
      ];

      // Allow other modules and themes to alter the settings.
      $context = [
        'container' => $container,
        'item_selector' => $item_selector,
        'options' => $options,
      ];
      $this->moduleHandler->alter('masonry_script', $masonry, $context);
      $this->themeManager->alter('masonry_script', $masonry, $context);

      $form['#attached']['library'][] = 'masonry/masonry.layout';
      if (isset($form['#attached']['drupalSettings'])) {
        $form['#attached']['drupalSettings'] += $masonry;
      }
      else {
        $form['#attached']['drupalSettings'] = $masonry;
      }
    }
  }

  /**
   * Build the masonry setting configuration form.
   *
   * @param array $default_values
   *   (optional) The default values for the form.
   *
   * @return array
   *   The form.
   */
  public function buildSettingsForm(array $default_values = []) {

    // Load module default values if empty.
    if (empty($default_values)) {
      $default_values = $this->getMasonryDefaultOptions();
    }

    $form['layoutColumnWidth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Column width'),
      '#description' => $this->t('The width of columns in the layout. Can be : <br/>
 - empty: column will be the size of the first item found <br/>
 - a size in pixel (ex. <em>10px</em>) <br/>
 - a size in percentage (ex. <em>10%</em>) <br/>
 - a CSS selector (ex. <em>#column-size</em>) <br/>
 See the <a href="http://masonry.desandro.com/options.html#columnwidth">masonry doc</a> for more information.'),
      '#default_value' => $default_values['layoutColumnWidth'],
      '#attributes' => [
        'placeholder' => $this->t('ex: 500px | 30% | .grid-sizer')
      ]
    ];
    $form['gutterWidth'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gutter width'),
      '#description' => $this->t('The spacing between each column. Can be : <br/>
 - empty: no gutter<br/>
 - a size in pixel (ex. <em>10px</em>) <br/>
 - a size in percentage (ex. <em>10%</em>) <br/>
 - a CSS selector (ex. <em>#column-size</em>) <br/>
 See the <a href="http://masonry.desandro.com/options.html#gutter">masonry doc</a> for more information.'),
      '#default_value' => $default_values['gutterWidth'],
      '#attributes' => [
        'placeholder' => $this->t('ex: 10px | 2% | .gutter-sizer')
      ]
    ];
    $form['stampSelector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stamp Selector'),
      '#description' => $this->t("Specifies which elements are stamped within the layout using css selector. <br/> See the <a href='http://masonry.desandro.com/options.html#stamp'>masonry doc</a> for more information."),
      '#default_value' => $default_values['stampSelector'],
      '#attributes' => [
        'placeholder' => $this->t('ex: .stamp-item')
      ]
    ];
    $form['isLayoutResizable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Resizable'),
      '#description' => $this->t("Automatically rearrange items when the container is resized."),
      '#default_value' => $default_values['isLayoutResizable'],
    ];
    $form['isLayoutAnimated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Animated'),
      '#description' => $this->t("Animate item rearrangements."),
      '#default_value' => $default_values['isLayoutAnimated'],
      '#states' => [
        'visible' => [
          'input.form-checkbox[name*="isLayoutResizable"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['layoutAnimationDuration'] = [
      '#type' => 'number',
      '#title' => $this->t('Animation duration'),
      '#description' => $this->t("The duration of animations (1000 ms = 1 sec)."),
      '#default_value' => $default_values['layoutAnimationDuration'],
      '#size' => 5,
      '#maxlength' => 4,
      '#field_suffix' => $this->t('ms'),
      '#states' => [
        'visible' => [
          'input.form-checkbox[name*="isLayoutResizable"]' => ['checked' => TRUE],
          'input.form-checkbox[name*="isLayoutAnimated"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['isLayoutFitsWidth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fit width'),
      '#description' => $this->t("Sets the width of the container to the nearest column. Ideal for centering Masonry layouts. <br/> See the <a href='http://masonry.desandro.com/options.html#fitwidth'>masonry doc</a> for more information."),
      '#default_value' => $default_values['isLayoutFitsWidth'],
    ];
    $form['isLayoutImagesLoadedFirst'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load images first'),
      '#description' => $this->t("Load all images first before triggering Masonry."),
      '#default_value' => $default_values['isLayoutImagesLoadedFirst'],
    ];
    $form['isLayoutImagesLazyLoaded'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add listener for lazy loaded images.'),
      '#description' => $this->t("If using the lazysizes library, you should probably activate this option."),
      '#default_value' => $default_values['isLayoutImagesLazyLoaded'],
    ];
    $form['isItemsWidthForce'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force item width to the size of a column.'),
      '#description' => $this->t("Sets items width to the size of column width. (/!\ Only works if the columnWidth size is defined to a value rather than a CSS selector)"),
      '#default_value' => $default_values['isItemsWidthForce'],
    ];
    $form['isItemsPositionInPercent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force percent position in layout'),
      '#description' => $this->t("Sets item positions in percent values, rather than pixel values. Checking this will works well with percent-width items, as items will not transition their position on resize. <br/> See the <a href='http://masonry.desandro.com/options.html#percentposition'>masonry doc</a> for more information."),
      '#default_value' => $default_values['isItemsPositionInPercent'],
    ];

    // Allow other modules and themes to alter the form.
    $this->moduleHandler->alter('masonry_options_form', $form, $default_values);
    $this->themeManager->alter('masonry_options_form', $form, $default_values);

    $form['#validate'][] = [$this, 'validateSettingsForm'];
    return $form;
  }

  /**
   * Validate the masonry setting configuration form.
   */
  public function validateSettingsForm(&$form, FormStateInterface &$form_state) {
    $column_width = $form_state->getValue(['style_options', 'layoutColumnWidth']);
    if (is_numeric($column_width)) {
      $form_state->setErrorByName('style_options][layoutColumnWidth', t('The unit seems to be missing on this field.'));
    }

    $gutter = $form_state->getValue(['style_options', 'gutterWidth']);
    if (is_numeric($gutter)) {
      $form_state->setErrorByName('style_options][gutterWidth', t('The unit seems to be missing on this field.'));
    }
  }

  /**
   * Check if the Masonry library is installed.
   *
   * @return string|null
   *   The masonry library install path.
   */
  public function isMasonryInstalled() {

    if (\Drupal::hasService('library.libraries_directory_file_finder')) {
      $library_path = \Drupal::service('library.libraries_directory_file_finder')->find('masonry/dist/masonry.pkgd.min.js');
    }
    elseif ($this->moduleHandler->moduleExists('libraries')) {
      $library_path = \Drupal::service('libraries.manager')->load('masonry') . '/dist/masonry.pkgd.min.js';
    }
    else {
      $library_path = 'libraries/masonry/dist/masonry.pkgd.min.js';
    }

    return file_exists($library_path) ? $library_path : NULL;
  }

  /**
   * Check if the ImagesLoaded library is installed.
   *
   * @return string|null
   *   The imagesloaded library install path.
   */
  public function isImagesloadedInstalled() {

    if (\Drupal::hasService('library.libraries_directory_file_finder')) {
      $library_path = \Drupal::service('library.libraries_directory_file_finder')->find('imagesloaded/imagesloaded.pkgd.min.js');
    }
    elseif ($this->moduleHandler->moduleExists('libraries')) {
      $library_path = \Drupal::service('libraries.manager')->load('imagesloaded') . '/imagesloaded.pkgd.min.js';
    }
    else {
      $library_path = 'libraries/imagesloaded/imagesloaded.pkgd.min.js';
    }

    return file_exists($library_path) ? $library_path : NULL;
  }

  /**
   * PHP8 polyfill.
   * @todo when D8 is no more supported.
   */
  protected function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if(!$length) {
      return true;
    }
    return substr($haystack, -$length) === $needle;
  }
}
