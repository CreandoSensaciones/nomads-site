<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

#[ViewsField('paragraph_parent_teaser')]
final class ParagraphParentTeaser extends ParagraphParentFieldBase {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['view_mode'] = ['default' => 'teaser'];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View mode'),
      '#default_value' => $this->options['view_mode'],
      '#required' => TRUE,
      '#description' => $this->t('Enter the machine name of the node view mode to render.'),
    ];
  }

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

    $view_mode = trim((string) ($this->options['view_mode'] ?? 'teaser'));
    if ($view_mode === '') {
      $view_mode = 'teaser';
    }

    return \Drupal::entityTypeManager()
      ->getViewBuilder('node')
      ->view($node, $view_mode);
  }
}
