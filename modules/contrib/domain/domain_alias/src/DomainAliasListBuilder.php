<?php

namespace Drupal\domain_alias;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\domain\DomainInterface;

/**
 * User interface for the domain alias overview screen.
 */
class DomainAliasListBuilder extends ConfigEntityListBuilder {

  /**
   * A domain object loaded from the controller.
   *
   * @var \Drupal\domain\DomainInterface
   */
  protected $domain;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Pattern'),
      'redirect' => $this->t('Redirect'),
      'environment' => $this->t('Environment'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    // We only care about DomainAlias entities.
    if ($entity instanceof DomainAliasInterface) {
      $row['label'] = $entity->label();
      $redirect = $entity->getRedirect();
      $row['redirect'] = is_null($redirect) ? $this->t('None') : $redirect;
      $row['environment'] = $entity->getEnvironment();
    }
    $row += parent::buildRow($entity);

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = [
      '#theme' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('No aliases have been created for this domain.'),
    ];
    foreach ($this->load() as $entity) {
      $row = $this->buildRow($entity);
      if ($row !== []) {
        $build['#rows'][$entity->id()] = $row;
      }
    }
    return $build;
  }

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('domain_id', $this->getDomainId())
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * Sets the domain context for this list.
   *
   * @param \Drupal\domain\DomainInterface $domain
   *   The domain to set as context for the list.
   */
  public function setDomain(DomainInterface $domain) {
    $this->domain = $domain;
  }

  /**
   * Gets the domain context for this list.
   *
   * @return \Drupal\domain\DomainInterface
   *   The domain that is context for this list.
   */
  public function getDomainId() {
    // @todo check for a use-case where we might need to derive the id?
    return $this->domain instanceof DomainInterface ? $this->domain->id() : NULL;
  }

}
