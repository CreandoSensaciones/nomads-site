<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Drupal\nomads_navigation\ListingDomainMatcher;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists domains whose navigation term filter matches a listing.
 */
#[ViewsField('nomads_navigation_listing_domains')]
final class ListingDomains extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ListingDomains Views field.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ListingDomainMatcher $matcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('nomads_navigation.listing_domain_matcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['selected_paragraph_bundles'] = ['default' => []];
    $options['paragraph_bundle_selection_initialized'] = ['default' => FALSE];
    $options['bypass_domain_assigned'] = ['default' => FALSE];
    $options['display_style'] = ['default' => 'text'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $paragraph_options = $this->matcher->getListingParagraphBundleOptions();
    $initialized = (bool) ($this->options['paragraph_bundle_selection_initialized'] ?? FALSE);
    $selected = $initialized
      ? $this->normalizeStringList((array) ($this->options['selected_paragraph_bundles'] ?? []))
      : $this->matcher->getDefaultParagraphBundles($paragraph_options);
    $selected = array_values(array_intersect($selected, array_keys($paragraph_options)));

    $form['selected_paragraph_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Paragraph types'),
      '#description' => $this->t('Only terms on the selected listing paragraph types are considered. Node fields are always checked.'),
      '#options' => $paragraph_options,
      '#default_value' => $selected,
      '#access' => $paragraph_options !== [],
    ];
    $form['paragraph_bundle_selection_initialized'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];
    $form['bypass_domain_assigned'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include listings assigned to each domain'),
      '#description' => $this->t('When checked, a listing assigned to a domain is listed for that domain even when it does not match the domain filter terms.'),
      '#default_value' => !empty($this->options['bypass_domain_assigned']),
    ];
    $form['display_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Display style'),
      '#options' => [
        'text' => $this->t('Text'),
        'pills' => $this->t('Pills'),
      ],
      '#default_value' => $this->options['display_style'] ?? 'text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $node = $this->getEntity($values);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'listing') {
      return [];
    }

    $domains = $this->matcher->getMatchingDomains(
      $node,
      $this->getSelectedParagraphBundles(),
      !empty($this->options['bypass_domain_assigned']),
    );
    $labels = [];
    foreach ($domains as $domain) {
      $label = $this->matcher->getDomainSubdomainLabel($domain);
      if ($label !== '') {
        $labels[] = $label;
      }
    }

    $cache = [
        'tags' => $this->matcher->getCacheTags(),
        'contexts' => ['languages:language_interface'],
    ];

    if (($this->options['display_style'] ?? 'text') !== 'pills') {
      return [
        '#plain_text' => implode(', ', $labels),
        '#cache' => $cache,
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-pills', 'nomads-pills--listing-domains'],
      ],
      '#cache' => $cache,
    ];
    foreach ($labels as $delta => $label) {
      $build['pill_' . $delta] = [
        '#prefix' => '<span class="nomads-pill">',
        '#plain_text' => $label,
        '#suffix' => '</span>',
      ];
    }

    return $build;
  }

  /**
   * Gets selected paragraph bundles for this field instance.
   *
   * @return string[]
   *   Selected paragraph bundle machine names.
   */
  private function getSelectedParagraphBundles(): array {
    $paragraph_options = $this->matcher->getListingParagraphBundleOptions();
    if ($paragraph_options === []) {
      return [];
    }

    if (empty($this->options['paragraph_bundle_selection_initialized'])) {
      return $this->matcher->getDefaultParagraphBundles($paragraph_options);
    }

    $selected = $this->normalizeStringList((array) ($this->options['selected_paragraph_bundles'] ?? []));
    return array_values(array_intersect($selected, array_keys($paragraph_options)));
  }

  /**
   * Normalizes a string list from checkbox values.
   *
   * @param mixed[] $values
   *   Raw values.
   *
   * @return string[]
   *   Non-empty unique strings.
   */
  private function normalizeStringList(array $values): array {
    $normalized = [];
    foreach ($values as $key => $value) {
      $value = is_string($value) ? $value : (is_string($key) ? $key : '');
      if ($value !== '' && $value !== '0') {
        $normalized[] = $value;
      }
    }

    return array_values(array_unique($normalized));
  }

}
