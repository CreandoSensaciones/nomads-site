<?php

namespace Drupal\masonry_fields\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\masonry\Services\MasonryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[FieldFormatter(
  id: 'masonry_media',
  label: new TranslatableMarkup('Masonry (media images)'),
  description: new TranslatableMarkup('Display referenced media items in a Masonry layout.'),
  field_types: [
    'entity_reference',
  ],
)]
class MasonryMediaFormatter extends EntityReferenceEntityFormatter {

  /**
   * The Masonry service.
   *
   * @var \Drupal\masonry\Services\MasonryService
   */
  protected $masonryService;

  /**
   * Constructs a MasonryMediaFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\masonry\Services\MasonryService $masonry_service
   *   The Masonry service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, MasonryService $masonry_service) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $logger_factory, $entity_type_manager, $entity_display_repository);
    $this->masonryService = $masonry_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('masonry.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $masonry_defaults = \Drupal::service('masonry.service')->getMasonryDefaultOptions();
    return [
      'masonry' => FALSE,
    ] + $masonry_defaults + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['masonry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Masonry'),
      '#description' => $this->t('Display items in a Masonry layout.'),
      '#default_value' => $this->getSetting('masonry'),
    ];

    if ($this->masonryService->isMasonryInstalled()) {
      $masonry_options = $this->getMasonryOptions();
      $masonry_form = $this->masonryService->buildSettingsForm($masonry_options);

      foreach ($masonry_form as $key => &$element) {
        if (!is_array($element) || str_starts_with((string) $key, '#')) {
          continue;
        }

        if (!isset($element['#states']['visible'])) {
          $element['#states']['visible'] = [];
        }
        $element['#states']['visible']['input.form-checkbox[name$="[masonry]"]'] = ['checked' => TRUE];
      }
      unset($element);

      $elements += $masonry_form;
    }
    else {
      $elements['masonry']['#disabled'] = TRUE;
      $elements['masonry']['#description'] = $this->t('This option has been disabled because the Masonry library is not installed.');
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->masonryService->isMasonryInstalled()) {
      $summary[] = $this->getSetting('masonry') ? $this->t('Masonry: Enabled') : $this->t('Masonry: Disabled');
    }
    else {
      $summary[] = $this->t('Masonry: Library not installed');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    if (!$this->getSetting('masonry') || !$this->masonryService->isMasonryInstalled()) {
      return $elements;
    }

    $field_name = $this->fieldDefinition->getName();
    $container_id = Html::getUniqueId('masonry-field-' . $items->getEntity()->id() . '-' . $field_name);
    $elements['#attributes']['id'] = $container_id;
    $elements['#attributes']['class'][] = 'masonry-field';
    $elements['#attributes']['class'][] = 'masonry-field--' . Html::getClass($field_name);

    foreach ($items as $delta => $item) {
      $items[$delta]->_attributes['class'][] = 'masonry-field__item';
    }

    $options = $this->getMasonryOptions();
    $this->masonryService->applyMasonryDisplay(
      $elements,
      '#' . $container_id,
      '.masonry-field__item',
      $options,
      ['masonry_fields']
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getType() !== 'entity_reference') {
      return FALSE;
    }

    if ($field_definition->getSetting('target_type') !== 'media') {
      return FALSE;
    }

    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

  /**
   * Get Masonry options merged with defaults.
   *
   * @return array
   *   The Masonry options for this formatter.
   */
  protected function getMasonryOptions() {
    $defaults = $this->masonryService->getMasonryDefaultOptions();
    $options = [];

    foreach (array_keys($defaults) as $key) {
      $options[$key] = $this->getSetting($key);
    }

    return $options + $defaults;
  }

}
