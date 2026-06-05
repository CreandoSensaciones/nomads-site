<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Form;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\domain_config\DomainConfigOverrider;
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
    private readonly StorageInterface $configStorage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
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
      $container->get('config.storage'),
      $container->get('cache_tags.invalidator'),
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
    $settings = $this->configFactory()->get($this->getSelectedSettingsConfigName());

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
      '#default_value' => $this->loadTerms($this->getConfiguredFilterTids($settings)),
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

    $form['specific_navigation_parent_tids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specific navigation terms'),
      '#description' => $this->t('Enter taxonomy term IDs separated by commas. Use a plain ID such as 123 to render that term as a pill. Add >, such as 123>, to render a branch of that term\'s published direct children.'),
      '#default_value' => implode(', ', $this->getRawSpecificNavigationParentTids()),
      '#maxlength' => 2048,
      '#placeholder' => $this->t('Example: 597, 1106>'),
    ];

    $form['term_label_overrides'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Term label overrides'),
      '#description' => $this->t('Optionally override labels for terms used by navigation pills and dropdowns. Use comma-separated pairs in the format "term ID: label". Example: 123: Abcdef, 456: Another label.'),
      '#default_value' => $this->formatTermLabelOverrides($this->getRawTermLabelOverrides()),
      '#maxlength' => 4096,
      '#placeholder' => $this->t('Example: 123: Abcdef, 456: Another label'),
    ];

    $form['view_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View title'),
      '#description' => $this->t('Domain-specific title rendered by the Domain title Views header. HTML is accepted and filtered when rendered.'),
      '#default_value' => $this->getRawStringSetting('view_title'),
      '#maxlength' => 255,
    ];

    $form['view_subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View subtitle'),
      '#description' => $this->t('Domain-specific subtitle rendered by the Domain subtitle Views header. HTML is accepted and filtered when rendered.'),
      '#default_value' => $this->getRawStringSetting('view_subtitle'),
      '#maxlength' => 255,
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
    $invalid_specific_navigation_ids = [];
    $specific_navigation_terms = $this->extractSpecificNavigationTerms(
      (string) $form_state->getValue('specific_navigation_parent_tids'),
      $invalid_specific_navigation_ids,
    );
    $invalid_term_label_overrides = [];
    $term_label_overrides = $this->extractTermLabelOverrides(
      (string) $form_state->getValue('term_label_overrides'),
      $invalid_term_label_overrides,
    );
    $view_title = (string) $form_state->getValue('view_title');
    $view_subtitle = (string) $form_state->getValue('view_subtitle');

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

    foreach ($specific_navigation_terms as $term_definition) {
      if (!$this->loadTerm($term_definition['id']) instanceof TermInterface) {
        $form_state->setErrorByName(
          'specific_navigation_parent_tids',
          $this->t('Specific navigation terms must be valid taxonomy terms.'),
        );
        break;
      }
    }

    if ($invalid_specific_navigation_ids !== []) {
      $form_state->setErrorByName(
        'specific_navigation_parent_tids',
        $this->t('Specific navigation terms must contain numeric term IDs, optionally followed by >, separated by commas. Invalid value(s): @values.', [
          '@values' => implode(', ', $invalid_specific_navigation_ids),
        ]),
      );
    }

    if ($invalid_term_label_overrides !== []) {
      $form_state->setErrorByName(
        'term_label_overrides',
        $this->t('Term label overrides must use comma-separated "term ID: label" pairs. Invalid value(s): @values.', [
          '@values' => implode(', ', $invalid_term_label_overrides),
        ]),
      );
    }

    foreach (array_keys($term_label_overrides) as $tid) {
      if (!$this->loadTerm((int) $tid) instanceof TermInterface) {
        $form_state->setErrorByName(
          'term_label_overrides',
          $this->t('Term label overrides must reference valid taxonomy terms.'),
        );
        break;
      }
    }

    if (mb_strlen($view_title) > 255) {
      $form_state->setErrorByName('view_title', $this->t('View title must be 255 characters or fewer.'));
    }

    if (mb_strlen($view_subtitle) > 255) {
      $form_state->setErrorByName('view_subtitle', $this->t('View subtitle must be 255 characters or fewer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $filter_tids = $this->extractTargetIds($form_state->getValue('filter_tids'));
    $sort_key_tids = $this->extractTargetIds($form_state->getValue('sort_key_tids'));
    $specific_navigation_parent_tids = $this->formatSpecificNavigationTermsForStorage($this->extractSpecificNavigationTerms((string) $form_state->getValue('specific_navigation_parent_tids')));
    $term_label_overrides = $this->extractTermLabelOverrides((string) $form_state->getValue('term_label_overrides'));
    $view_title = trim((string) $form_state->getValue('view_title'));
    $view_subtitle = trim((string) $form_state->getValue('view_subtitle'));

    $settings = [
      'filter_tid' => $filter_tids[0] ?? NULL,
      'filter_tids' => $filter_tids,
      'sort_key_tids' => $sort_key_tids,
      'specific_navigation_parent_tids' => $specific_navigation_parent_tids,
      'term_label_overrides' => $term_label_overrides,
      'view_title' => $view_title,
      'view_subtitle' => $view_subtitle,
    ];

    $config_name = $this->getSelectedSettingsConfigName();
    $existing = $this->configStorage->read($config_name);
    $data = is_array($existing) ? $existing : [];
    $this->configStorage->write($config_name, array_replace($data, $settings));

    $this->configFactory()->reset($config_name);
    $this->configFactory()->reset('nomads_navigation.settings');
    $this->resetDomainConfigOverrider();
    $this->cacheTagsInvalidator->invalidateTags([
      'config:' . $config_name,
      'config:nomads_navigation.settings',
    ]);

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
  private function getConfiguredFilterTids($settings): array {
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
    if (is_string($value)) {
      $value = trim($value);
      if ($value === '') {
        return [];
      }

      return $this->normalizeTermIds($this->extractTargetIdsFromAutocompleteString($value));
    }

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
   * Extracts target IDs from a raw entity autocomplete tags string.
   *
   * Drupal normally normalizes entity_autocomplete values before submit, but
   * keeping this fallback makes the config save replace the stored IDs even if
   * the browser submits the raw token text after edits or deletions.
   *
   * @return int[]
   *   Target IDs found in the submitted string.
   */
  private function extractTargetIdsFromAutocompleteString(string $value): array {
    $target_ids = [];
    foreach (Tags::explode($value) as $item) {
      $target_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput(trim($item));
      if ($target_id !== NULL) {
        $target_ids[] = (int) $target_id;
      }
    }

    return $target_ids;
  }

  /**
   * Gets the config name currently selected by Domain Config UI.
   */
  private function getSelectedSettingsConfigName(): string {
    if (\Drupal::hasService('domain_config_ui.manager')) {
      $manager = \Drupal::service('domain_config_ui.manager');
      if (method_exists($manager, 'getSelectedDomainId')) {
        $domain_id = $manager->getSelectedDomainId();
        if (!is_string($domain_id) || $domain_id === '') {
          return 'nomads_navigation.settings';
        }
      }

      $config_name = $manager->getSelectedConfigName('nomads_navigation.settings', TRUE);
      if (is_string($config_name) && $config_name !== '') {
        return $config_name;
      }
    }

    return 'nomads_navigation.settings';
  }

  /**
   * Gets domain-specific settings config names that may affect this field.
   *
   * @return string[]
   *   Config object names.
   */
  private function getDomainSettingsConfigNames(string $domain_id): array {
    return [
      DomainConfigOverrider::getConfigNameByDomain('nomads_navigation.settings', $domain_id),
    ];
  }

  /**
   * Gets raw, non-merged Specific navigation terms for the current config context.
   *
   * Domain Config merges array overrides recursively, which is wrong for this
   * field because an explicitly empty list must not inherit older term IDs.
   *
   * @return string[]
   *   Configured term tokens.
   */
  private function getRawSpecificNavigationParentTids(): array {
    foreach ($this->getSettingsConfigNamesForRead() as $config_name) {
      $data = $this->configStorage->read($config_name);
      if (is_array($data) && array_key_exists('specific_navigation_parent_tids', $data)) {
        $value = $data['specific_navigation_parent_tids'];
        return is_array($value) ? $this->normalizeSpecificNavigationTokens($value) : [];
      }
    }

    return [];
  }

  /**
   * Gets raw, non-merged term label overrides for the current config context.
   *
   * @return array<int, string>
   *   Labels keyed by term ID.
   */
  private function getRawTermLabelOverrides(): array {
    foreach ($this->getSettingsConfigNamesForRead() as $config_name) {
      $data = $this->configStorage->read($config_name);
      if (is_array($data) && array_key_exists('term_label_overrides', $data)) {
        return $this->normalizeTermLabelOverrides($data['term_label_overrides']);
      }
    }

    return [];
  }

  /**
   * Gets a raw string setting for the current config context.
   */
  private function getRawStringSetting(string $key): string {
    foreach ($this->getSettingsConfigNamesForRead() as $config_name) {
      $data = $this->configStorage->read($config_name);
      if (is_array($data) && array_key_exists($key, $data)) {
        return (string) $data[$key];
      }
    }

    return '';
  }

  /**
   * Gets settings config names in read precedence for the current form context.
   *
   * @return string[]
   *   Config object names.
   */
  private function getSettingsConfigNamesForRead(): array {
    $config_names = [
      $this->getSelectedSettingsConfigName(),
    ];

    if (\Drupal::hasService('domain_config_ui.manager')) {
      $manager = \Drupal::service('domain_config_ui.manager');
      if (method_exists($manager, 'getSelectedDomainId')) {
        $domain_id = $manager->getSelectedDomainId();
        if (is_string($domain_id) && $domain_id !== '') {
          $domain_config_names = $this->getDomainSettingsConfigNames($domain_id);
          $config_names = array_merge($config_names, array_reverse($domain_config_names));
        }
      }
    }

    $config_names[] = 'nomads_navigation.settings';

    return array_values(array_unique($config_names));
  }

  /**
   * Clears cached domain overrides after writing domain config directly.
   */
  private function resetDomainConfigOverrider(): void {
    if (\Drupal::hasService('domain_config.overrider')) {
      \Drupal::service('domain_config.overrider')->reset();
    }
  }

  /**
   * Extracts Specific navigation term definitions from a comma-separated field.
   *
   * @param string[]|null $invalid_values
   *   Invalid entries found in the submitted text.
   *
   * @return array<int, array{id: int, branch: bool}>
   *   Positive unique term definitions.
   */
  private function extractSpecificNavigationTerms(string $value, ?array &$invalid_values = NULL): array {
    $invalid_values = [];
    $value = trim($value);
    if ($value === '') {
      return [];
    }

    $terms = [];
    $seen = [];
    foreach (explode(',', $value) as $item) {
      $item = trim($item);
      if ($item === '') {
        continue;
      }
      if (!preg_match('/^\d+>?$/', $item)) {
        $invalid_values[] = $item;
        continue;
      }

      $branch = str_ends_with($item, '>');
      $id = (int) rtrim($item, '>');
      if ($id <= 0) {
        $invalid_values[] = $item;
        continue;
      }

      $key = $id . ($branch ? '>' : '');
      if (isset($seen[$key])) {
        continue;
      }

      $seen[$key] = TRUE;
      $terms[] = [
        'id' => $id,
        'branch' => $branch,
      ];
    }

    return $terms;
  }

  /**
   * Formats Specific navigation term definitions for config storage.
   *
   * @param array<int, array{id: int, branch: bool}> $terms
   *   Term definitions.
   *
   * @return string[]
   *   Config tokens.
   */
  private function formatSpecificNavigationTermsForStorage(array $terms): array {
    return array_map(
      static fn (array $term): string => (string) $term['id'] . ($term['branch'] ? '>' : ''),
      $terms,
    );
  }

  /**
   * Normalizes stored Specific navigation tokens, including legacy integer IDs.
   *
   * @param mixed[] $tokens
   *   Stored values.
   *
   * @return string[]
   *   Config tokens.
   */
  private function normalizeSpecificNavigationTokens(array $tokens): array {
    $normalized = [];
    $seen = [];

    foreach ($tokens as $token) {
      $token = trim((string) $token);
      if (!preg_match('/^\d+>?$/', $token)) {
        continue;
      }

      $branch = str_ends_with($token, '>');
      $id = (int) rtrim($token, '>');
      if ($id <= 0) {
        continue;
      }

      $normalized_token = (string) $id . ($branch ? '>' : '');
      if (isset($seen[$normalized_token])) {
        continue;
      }

      $seen[$normalized_token] = TRUE;
      $normalized[] = $normalized_token;
    }

    return $normalized;
  }

  /**
   * Extracts term label overrides from comma-separated "term ID: label" pairs.
   *
   * @param string[]|null $invalid_values
   *   Invalid entries found in the submitted text.
   *
   * @return array<int, string>
   *   Labels keyed by term ID.
   */
  private function extractTermLabelOverrides(string $value, ?array &$invalid_values = NULL): array {
    $invalid_values = [];
    $value = trim($value);
    if ($value === '') {
      return [];
    }

    $overrides = [];
    foreach (explode(',', $value) as $item) {
      $item = trim($item);
      if ($item === '') {
        continue;
      }

      $parts = explode(':', $item, 2);
      if (count($parts) !== 2) {
        $invalid_values[] = $item;
        continue;
      }

      $term_id = trim($parts[0]);
      $label = trim($parts[1]);
      if (!ctype_digit($term_id) || (int) $term_id <= 0 || $label === '') {
        $invalid_values[] = $item;
        continue;
      }

      $overrides[(int) $term_id] = $label;
    }

    return $overrides;
  }

  /**
   * Formats term label overrides for the settings form.
   *
   * @param array<int, string> $overrides
   *   Labels keyed by term ID.
   */
  private function formatTermLabelOverrides(array $overrides): string {
    $items = [];
    foreach ($overrides as $term_id => $label) {
      $items[] = (int) $term_id . ': ' . $label;
    }

    return implode(', ', $items);
  }

  /**
   * Normalizes stored term label override data.
   *
   * @return array<int, string>
   *   Labels keyed by term ID.
   */
  private function normalizeTermLabelOverrides(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $overrides = [];
    foreach ($value as $term_id => $label) {
      $term_id = (int) $term_id;
      $label = trim((string) $label);
      if ($term_id > 0 && $label !== '') {
        $overrides[$term_id] = $label;
      }
    }

    return $overrides;
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
