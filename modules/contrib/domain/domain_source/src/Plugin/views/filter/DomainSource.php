<?php

namespace Drupal\domain_source\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by published status.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('domain_source')]
class DomainSource extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * The domain storage handler.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * The domain negotiator.
   *
   * @var \Drupal\domain\DomainNegotiatorInterface
   */
  protected $negotiator;

  /**
   * Constructs a new DomainSource filter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager Service.
   * @param \Drupal\domain\DomainNegotiatorInterface $negotiator
   *   The domain negotiator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, DomainNegotiatorInterface $negotiator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->domainStorage = $entity_type_manager->getStorage('domain');
    $this->negotiator = $negotiator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('domain.negotiator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Domains');
      $this->valueOptions = [
        '_active' => $this->t('Active domain'),
      ] + $this->domainStorage->loadOptionsList();
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $active_index = array_search('_active', (array) $this->value, TRUE);
    if ($active_index !== FALSE) {
      $active_id = $this->negotiator->getActiveId();
      $this->value[$active_index] = $active_id;
    }

    parent::query();
  }

}
