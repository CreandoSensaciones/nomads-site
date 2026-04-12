<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

#[ViewsField('paragraph_parent_teaser_trigger')]
final class ParagraphParentTeaserTrigger extends ParagraphParentFieldBase {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_text'] = ['default' => 'Open details'];
    $options['target_selector'] = ['default' => '.map-detail-panel'];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $this->options['link_text'] ?? 'Open details',
      '#required' => TRUE,
    ];

    $form['target_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target selector'),
      '#default_value' => $this->options['target_selector'] ?? '.map-detail-panel',
      '#description' => $this->t('CSS selector for the teaser target container.'),
      '#required' => TRUE,
    ];
  }

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

    $title = trim((string) ($this->options['link_text'] ?? 'Open details'));
    if ($title === '') {
      $title = 'Open details';
    }

    $target_selector = trim((string) ($this->options['target_selector'] ?? '.map-detail-panel'));
    if ($target_selector === '') {
      $target_selector = '.map-detail-panel';
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'a',
      '#value' => $title,
      '#attributes' => [
        'class' => ['js-nomads-parent-teaser-trigger'],
        'href' => $this->buildTeaserUrl($node),
        'data-parent-nid' => (string) $node->id(),
        'data-paragraph-id' => (string) $paragraph->id(),
        'data-teaser-url' => $this->buildTeaserUrl($node),
        'data-target-selector' => $target_selector,
      ],
      '#attached' => [
        'library' => ['nomads_parent_teaser/map_teaser'],
      ],
    ];
  }

}
