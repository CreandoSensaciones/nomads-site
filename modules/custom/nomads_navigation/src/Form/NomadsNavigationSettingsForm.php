<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure domain-specific navigation settings.
 */
final class NomadsNavigationSettingsForm extends ConfigFormBase {

  /**
   * Allowed vocabularies for filter terms.
   */
  private const FILTER_VOCABULARIES = [
    'type',
    't',
  ];

  /**
   * Allowed vocabulary for sort keys.
   */
  private const SORT_KEY_VOCABULARIES = [
    'subsites',
  ];

  /**
   * Constructs a NomadsNavigationSettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nomads_navigation_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['nomads_navigation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('nomads_navigation.settings');

    $form['#attributes']['class'][] = 'nomads-navigation-settings-form';
    $form['#attached']['library'][] = 'nomads_navigation/settings_form';

    $form['filter_tids'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Filter'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => array_combine(
          self::FILTER_VOCABULARIES,
          self::FILTER_VOCABULARIES,
        ),
      ],
      '#default_value' => $this->loadTerms($this->getConfiguredFilterTids()),
      '#maxlength' => 255,
      '#placeholder' => $this->t('Select terms'),
    ];

    $form['sort_key_tids'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Sort keys'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => array_combine(
          self::SORT_KEY_VOCABULARIES,
          self::SORT_KEY_VOCABULARIES,
        ),
      ],
      '#default_value' => $this->loadTerms($settings->get('sort_key_tids') ?? []),
      '#maxlength' => 255,
      '#placeholder' => $this->t('Select up to two terms'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $filter_tids = $this->extractTargetIds($form_state->getValue('filter_tids'));
    $sort_key_tids = $this->extractTargetIds($form_state->getValue('sort_key_tids'));

    foreach ($filter_tids as $tid) {
      if (!$this->termBelongsToVocabulary($tid, self::FILTER_VOCABULARIES)) {
        $form_state->setErrorByName(
          'filter_tids',
          $this->t('Filter terms must be from Type or Tags.'),
        );
        break;
      }
    }

    if (count($sort_key_tids) > 2) {
      $form_state->setErrorByName(
        'sort_key_tids',
        $this->t('Sort keys accepts at most two taxonomy terms.'),
      );
    }

    foreach ($sort_key_tids as $tid) {
      if (!$this->termBelongsToVocabulary($tid, self::SORT_KEY_VOCABULARIES)) {
        $form_state->setErrorByName(
          'sort_key_tids',
          $this->t('Sort keys must be terms from Subsites.'),
        );
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $filter_tids = $this->extractTargetIds($form_state->getValue('filter_tids'));

    $this->config('nomads_navigation.settings')
      ->set('filter_tid', $filter_tids[0] ?? NULL)
      ->set('filter_tids', $filter_tids)
      ->set('sort_key_tids', $this->extractTargetIds($form_state->getValue('sort_key_tids')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Loads one taxonomy term.
   */
  private function loadTerm(int $tid): ?TermInterface {
    if ($tid <= 0) {
      return NULL;
    }

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Loads taxonomy terms from IDs.
   *
   * @param int[] $tids
   *   Term IDs.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Loaded terms.
   */
  private function loadTerms(array $tids): array {
    $tids = array_values(array_filter(array_map('intval', $tids)));
    if ($tids === []) {
      return [];
    }

    return array_values(array_filter(
      $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids),
      static fn ($term): bool => $term instanceof TermInterface,
    ));
  }

  /**
   * Gets configured filter term IDs, including legacy single-term config.
   *
   * @return int[]
   *   Configured filter term IDs.
   */
  private function getConfiguredFilterTids(): array {
    $settings = $this->config('nomads_navigation.settings');
    $filter_tids = $this->normalizeTermIds($settings->get('filter_tids') ?? []);
    if ($filter_tids !== []) {
      return $filter_tids;
    }

    return $this->normalizeTermIds([$settings->get('filter_tid')]);
  }

  /**
   * Extracts target IDs from an entity autocomplete tags value.
   *
   * @param mixed $value
   *   The submitted form value.
   *
   * @return int[]
   *   Target IDs.
   */
  private function extractTargetIds(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $target_ids = [];
    foreach ($value as $item) {
      if (isset($item['target_id'])) {
        $target_ids[] = (int) $item['target_id'];
      }
    }

    return $this->normalizeTermIds($target_ids);
  }

  /**
   * Normalizes term IDs.
   *
   * @param mixed[] $tids
   *   Raw term IDs.
   *
   * @return int[]
   *   Positive unique term IDs.
   */
  private function normalizeTermIds(array $tids): array {
    return array_values(array_unique(array_filter(array_map('intval', $tids), static fn (int $tid): bool => $tid > 0)));
  }

  /**
   * Checks whether a term belongs to one of the allowed vocabularies.
   *
   * @param string[] $allowed_vocabularies
   *   Allowed vocabulary machine names.
   */
  private function termBelongsToVocabulary(int $tid, array $allowed_vocabularies): bool {
    $term = $this->loadTerm($tid);

    return $term instanceof TermInterface && in_array($term->bundle(), $allowed_vocabularies, TRUE);
  }

}
