<?php

namespace Drupal\field_group_toggler\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Storage handler for field group toggle states.
 */
class FieldGroupTogglerStorage {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs the storage service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Gets the toggle state for a group on an entity.
   *
   * @return bool|null
   *   TRUE if on, FALSE if off, NULL if no record.
   */
  public function getState(EntityInterface $entity, string $group_name, string $langcode = NULL): ?bool {
    $langcode = $langcode ?: $entity->language()->getId();

    $result = $this->database->select('field_group_toggler_state', 'fgts')
      ->fields('fgts', ['is_on'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->condition('group_name', $group_name)
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchField();

    if ($result === FALSE) {
      return NULL;
    }
    return (bool) $result;
  }

  /**
   * Sets the toggle state for a group on an entity.
   */
  public function setState(EntityInterface $entity, string $group_name, bool $is_on, string $langcode = NULL): void {
    $langcode = $langcode ?: $entity->language()->getId();

    $existing_id = $this->database->select('field_group_toggler_state', 'fgts')
      ->fields('fgts', ['id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->condition('group_name', $group_name)
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchField();

    $fields = [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'entity_id' => $entity->id(),
      'group_name' => $group_name,
      'langcode' => $langcode,
      'is_on' => $is_on ? 1 : 0,
    ];

    if ($existing_id) {
      $this->database->update('field_group_toggler_state')
        ->fields(['is_on' => $fields['is_on']])
        ->condition('id', $existing_id)
        ->execute();
    }
    else {
      $this->database->insert('field_group_toggler_state')
        ->fields($fields)
        ->execute();
    }
  }

  /**
   * Deletes all toggle states for an entity.
   */
  public function deleteForEntity(EntityInterface $entity): void {
    if ($entity->isNew()) {
      return;
    }
    $this->database->delete('field_group_toggler_state')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

}
