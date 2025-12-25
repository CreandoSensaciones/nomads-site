<?php

namespace Drupal\field_toggler\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

class FieldTogglerStorage {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public function getState(EntityInterface $entity, string $field_name, int $delta = 0, string $langcode = NULL): ?bool {
    $langcode = $langcode ?: $entity->language()->getId();

    $result = $this->database->select('field_toggler_state', 'fts')
      ->fields('fts', ['is_on'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->condition('field_name', $field_name)
      ->condition('langcode', $langcode)
      ->condition('delta', $delta)
      ->execute()
      ->fetchField();

    if ($result === FALSE) {
      return NULL;
    }
    return (bool) $result;
  }

  public function setState(EntityInterface $entity, string $field_name, int $delta, bool $is_on, string $langcode = NULL): void {
    $langcode = $langcode ?: $entity->language()->getId();

    $existing_id = $this->database->select('field_toggler_state', 'fts')
      ->fields('fts', ['id'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->condition('field_name', $field_name)
      ->condition('langcode', $langcode)
      ->condition('delta', $delta)
      ->execute()
      ->fetchField();

    $fields = [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'entity_id' => $entity->id(),
      'field_name' => $field_name,
      'langcode' => $langcode,
      'delta' => $delta,
      'is_on' => $is_on ? 1 : 0,
    ];

    if ($existing_id) {
      $this->database->update('field_toggler_state')
        ->fields(['is_on' => $fields['is_on']])
        ->condition('id', $existing_id)
        ->execute();
    } else {
      $this->database->insert('field_toggler_state')
        ->fields($fields)
        ->execute();
    }
  }

  public function deleteForEntity(EntityInterface $entity): void {
    if ($entity->isNew()) {
      return;
    }
    $this->database->delete('field_toggler_state')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $entity->bundle())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

}
