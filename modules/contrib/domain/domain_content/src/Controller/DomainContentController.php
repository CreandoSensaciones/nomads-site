<?php

namespace Drupal\domain_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain_access\DomainAccessManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines domain content pages.
 */
class DomainContentController extends ControllerBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Domain Access manager.
   *
   * @var \Drupal\domain_access\DomainAccessManagerInterface
   */
  protected $manager;

  /**
   * The entity storage.
   *
   * @var \Drupal\domain\DomainStorageInterface
   */
  protected $domainStorage;

  /**
   * Constructs a new DomainContentController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\domain_access\DomainAccessManagerInterface $manager
   *   The domain access manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DomainAccessManagerInterface $manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->domainStorage = $entity_type_manager->getStorage('domain');
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('domain_access.manager')
    );
  }

  /**
   * Builds the list of domains and relevant entities.
   *
   * @param array $options
   *   A list of variables required to build editor or content pages.
   *
   * @return array
   *   A Drupal page build array.
   *
   * @see contentList()
   */
  public function buildList(array $options) {
    $account = $this->getUser();
    $build = [
      '#theme' => 'table',
      '#header' => [$this->t('Domain'), $options['column_header']],
    ];
    if ($account->hasPermission($options['all_permission'])) {
      $build['#rows'][] = [
        Link::fromTextAndUrl($this->t('All affiliates'), Url::fromUri('internal:/admin/content/' . $options['path'] . '/all_affiliates')),
        $this->getCount($options['type']),
      ];
    }
    // Loop through domains.
    $domains = $this->domainStorage->loadMultipleSorted();
    /** @var \Drupal\domain\DomainInterface $domain */
    foreach ($domains as $domain) {
      if ($account->hasPermission($options['all_permission']) || $this->manager->hasDomainPermissions($account, $domain, [$options['permission']])) {
        $row = [
          Link::fromTextAndUrl($domain->label(), Url::fromUri('internal:/admin/content/' . $options['path'] . '/' . $domain->id())),
          $this->getCount($options['type'], $domain),
        ];
        $build['#rows'][] = $row;
      }
    }
    return $build;
  }

  /**
   * Generates a list of content by domain.
   */
  public function contentList() {
    $options = [
      'type' => 'node',
      'column_header' => $this->t('Content count'),
      'permission' => 'publish to any assigned domain',
      'all_permission' => 'publish to any domain',
      'path' => 'domain-content',
    ];

    return $this->buildList($options);
  }

  /**
   * Generates a list of editors by domain.
   */
  public function editorsList() {
    $options = [
      'type' => 'user',
      'column_header' => $this->t('Editor count'),
      'permission' => 'assign domain editors',
      'all_permission' => 'assign editors to any domain',
      'path' => 'domain-editors',
    ];

    return $this->buildList($options);
  }

  /**
   * Counts the content for a domain.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to query. If passed NULL, checks status for all affiliates.
   *
   * @return int
   *   The content count for the given domain.
   */
  protected function getCount($entity_type = 'node', ?DomainInterface $domain = NULL) {
    if (is_null($domain)) {
      $field = DomainAccessManagerInterface::DOMAIN_ACCESS_ALL_FIELD;
      $value = 1;
    }
    else {
      $field = DomainAccessManagerInterface::DOMAIN_ACCESS_FIELD;
      $value = $domain->id();
    }
    // Note that we ignore node access so these queries work on any domain.
    $query = $this->entityTypeManager->getStorage($entity_type)->getQuery()
      ->condition($field, $value)
      ->accessCheck(FALSE);

    return count($query->execute());
  }

  /**
   * Returns a fully loaded user object for the current request.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user object.
   */
  protected function getUser() {
    $account = $this->currentUser();
    // Advanced grants for edit/delete require permissions.
    return $this->entityTypeManager->getStorage('user')->load($account->id());
  }

}
