<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

#[ViewsField('paragraph_parent_title')]
final class ParagraphParentTitle extends ParagraphParentFieldBase {

  public function query() {}

  public function render(ResultRow $values) {
    $paragraph = $this->getParagraph($values);
    if (!$paragraph instanceof ParagraphInterface) {
      return [];
    }

    $node = $this->findParentNode($paragraph);
    if (!$node instanceof NodeInterface) {
      return [];
    }

    return [
      '#plain_text' => $node->label(),
    ];
  }

}
