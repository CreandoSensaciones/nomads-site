<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[ViewsField('paragraph_parent_teaser')]
final class ParagraphParentTeaser extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['view_mode'] = ['default' => 'teaser'];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['view_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View mode'),
      '#default_value' => $this->options['view_mode'],
      '#required' => TRUE,
      '#description' => $this->t('Enter the machine name of the node view mode to render.'),
    ];
  }

  public function query(): void {}

  public function render(ResultRow $values): array {
    $paragraph = $this->getEntity($values);
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

    return $this->entityTypeManager
      ->getViewBuilder('node')
      ->view($node, $view_mode);
  }

  private function findParentNode(ParagraphInterface $paragraph): ?NodeInterface {
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

}
