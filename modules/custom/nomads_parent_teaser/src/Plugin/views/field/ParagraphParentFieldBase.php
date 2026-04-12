<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

abstract class ParagraphParentFieldBase extends FieldPluginBase {

  protected function getParagraph(ResultRow $values): ?ParagraphInterface {
    $paragraph = $this->getEntity($values);
    return $paragraph instanceof ParagraphInterface ? $paragraph : NULL;
  }

  protected function findParentNode(ParagraphInterface $paragraph): ?NodeInterface {
    $visited = [];
    $current = $paragraph;

    while ($current instanceof ParagraphInterface) {
      $entity_id = $current->id();
      if ($entity_id !== NULL && isset($visited[$entity_id])) {
        return NULL;
      }
      if ($entity_id !== NULL) {
        $visited[$entity_id] = TRUE;
      }

      $parent = $current->getParentEntity();
      if (!$parent instanceof ContentEntityInterface) {
        return NULL;
      }

      if ($parent instanceof NodeInterface) {
        return $parent;
      }

      if (!$parent instanceof ParagraphInterface) {
        return NULL;
      }

      $current = $parent;
    }

    return NULL;
  }

  protected function buildTeaserUrl(NodeInterface $node): string {
    return '/nomads-parent-teaser/' . $node->id();
  }

}
