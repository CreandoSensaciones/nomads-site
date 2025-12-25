<?php

namespace Drupal\csv_importer\Plugin\Derivative;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives importer(s) for all content entity types.
 */
class ImporterDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the ImporterDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\Core\Entity\ContentEntityType|\Drupal\Core\Config\Entity\ConfigEntityType[] $entity_types */
    $entity_types = $this->entityTypeManager->getDefinitions();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if ($entity_type->getGroup() === 'content') {
        $this->derivatives[$entity_type_id] = $base_plugin_definition + [
          'entity_type' => $entity_type_id,
        ];
        $this->derivatives[$entity_type_id]['admin_label'] = $this->t('Entity Type importer: @label', [
          '@label' => $entity_type->getLabel(),
        ]);
      }
    }

    return $this->derivatives;
  }

}
