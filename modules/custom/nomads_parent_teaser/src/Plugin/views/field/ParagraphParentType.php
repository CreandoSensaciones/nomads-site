<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

#[ViewsField('paragraph_parent_type')]
final class ParagraphParentType extends ParagraphParentFieldBase {

  public function query() {}

  public function render(ResultRow $values) {
    $paragraph = $this->getParagraph($values);
    if ($paragraph === NULL) {
      return [];
    }

    $node = $this->findParentNode($paragraph);
    if (!$node instanceof NodeInterface || !$node->hasField('field_type')) {
      return [];
    }

    $term = $node->get('field_type')->entity;
    if (!$term instanceof TermInterface) {
      return [];
    }

    return [
      '#plain_text' => $term->label(),
    ];
  }

}
