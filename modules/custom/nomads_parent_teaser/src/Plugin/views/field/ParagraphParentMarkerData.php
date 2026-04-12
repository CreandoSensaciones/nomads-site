<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

#[ViewsField('paragraph_parent_marker_data')]
final class ParagraphParentMarkerData extends ParagraphParentFieldBase {

  public function query() {}

  public function render(ResultRow $values) {
    $paragraph = $this->getParagraph($values);
    if ($paragraph === NULL) {
      return [];
    }

    $node = $this->findParentNode($paragraph);
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $attributes = [
      'class' => ['js-nomads-marker-data'],
      'hidden' => 'hidden',
      'aria-hidden' => 'true',
      'data-parent-nid' => (string) $node->id(),
      'data-paragraph-id' => (string) $paragraph->id(),
      'data-parent-title' => Html::decodeEntities($node->label()),
      'data-teaser-url' => $this->buildTeaserUrl($node),
      'data-paragraph-bundle' => $paragraph->bundle(),
      'data-node-bundle' => $node->bundle(),
    ];

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => $attributes,
      '#attached' => [
        'library' => ['nomads_parent_teaser/map_teaser'],
      ],
    ];
  }

}
