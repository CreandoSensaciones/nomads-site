<?php

declare(strict_types=1);

namespace Drupal\nomads_navigation\Plugin\Block;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\nomads_navigation\FilterQueryNormalizer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a month-based date period navigation block for Views pages.
 */
#[Block(
  id: 'nomads_navigation_date_period_navigation',
  admin_label: new TranslatableMarkup('Date period navigation'),
  category: new TranslatableMarkup('Nomads')
)]
final class DatePeriodNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const QUERY_KEY = 'month';
  private const SIX_MONTHS_VALUE = '6months';

  /**
   * Constructs a DatePeriodNavigationBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RequestStack $requestStack,
    private readonly TimeInterface $time,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly FilterQueryNormalizer $filterQueryNormalizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('nomads_navigation.filter_query_normalizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $options = $this->buildMonthOptions();
    $selected_value = $this->getSelectedMonth();
    $selected_label = $options[$selected_value] ?? NULL;

    $links = [];
    foreach ($options as $value => $label) {
      $links[] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $this->buildSelectionUrl($value),
        '#attributes' => [
          'class' => array_filter([
            'nomads-date-period-navigation__option',
            $selected_value === $value ? 'is-active' : NULL,
          ]),
        ],
      ];
    }

    $links[] = [
      '#type' => 'link',
      '#title' => $this->t('-clear-'),
      '#url' => $this->buildClearUrl(),
      '#attributes' => [
        'class' => ['nomads-date-period-navigation__option', 'nomads-date-period-navigation__option--clear'],
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-date-period-navigation'],
      ],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Date of temporary listings'),
        '#attributes' => [
          'class' => ['nomads-date-period-navigation__parent-label'],
        ],
      ],
      'dropdown' => [
        '#type' => 'html_tag',
        '#tag' => 'details',
        '#attributes' => [
          'class' => array_filter([
            'nomads-date-period-navigation__dropdown',
            $selected_label !== NULL ? 'is-selected' : NULL,
          ]),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'summary',
          '#value' => $selected_label ?? $this->t('Date period'),
          '#attributes' => [
            'class' => ['nomads-date-period-navigation__pill'],
          ],
        ],
        'options' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['nomads-date-period-navigation__menu'],
          ],
          'links' => $links,
        ],
      ],
      '#attached' => [
        'library' => ['nomads_navigation/date_period_navigation'],
      ],
      '#cache' => [
        'contexts' => [
          'route',
          'url.path',
          ...FilterQueryNormalizer::CACHE_CONTEXTS,
          'timezone',
        ],
        // Keep the month list fresh around month boundaries.
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds the current month plus the following 6 months.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup|string>
   *   Query values keyed to dropdown labels.
   */
  private function buildMonthOptions(): array {
    $timezone = new \DateTimeZone(date_default_timezone_get());
    $start = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone($timezone)
      ->modify('first day of this month')
      ->setTime(0, 0);

    $options = [];
    for ($i = 0; $i < 7; $i++) {
      $month = $start->modify('+' . $i . ' months');
      $value = $month->format('Y-n');
      $options[$value] = $i === 0
        ? $this->t('Actually')
        : $this->dateFormatter->format($month->getTimestamp(), 'custom', $month->format('Y') === $start->format('Y') ? 'F' : 'F Y');
    }
    $options[self::SIX_MONTHS_VALUE] = $this->t('+6 months from now');

    return $options;
  }

  /**
   * Builds a URL selecting a month value.
   */
  private function buildSelectionUrl(string $value): Url {
    $query = $this->getCurrentQuery();
    $query = $this->filterQueryNormalizer->withValue($query, self::QUERY_KEY, $value);

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Builds a URL clearing the selected month value.
   */
  private function buildClearUrl(): Url {
    $query = $this->getCurrentQuery();
    $query = $this->filterQueryNormalizer->withoutValue($query, self::QUERY_KEY);

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Reads the selected month query value.
   */
  private function getSelectedMonth(): ?string {
    $request = $this->requestStack->getCurrentRequest();
    $value = $request?->query->get(self::QUERY_KEY);

    return $this->filterQueryNormalizer->normalizeMonth($value);
  }

  /**
   * Gets current query arguments.
   *
   * @return array<string, mixed>
   *   Query arguments.
   */
  private function getCurrentQuery(): array {
    $request = $this->requestStack->getCurrentRequest();

    return $request ? $this->filterQueryNormalizer->normalize($request->query->all()) : [];
  }

}
