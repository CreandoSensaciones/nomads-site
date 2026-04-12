<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsRelationship;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Views;

/**
 * Relates a paragraph to the nearest node in its parent chain.
 */
#[ViewsRelationship('paragraph_parent_line_node')]
final class ParagraphParentLineNode extends RelationshipPluginBase {

  /**
   * Maximum ancestor depth available in the Views UI.
   */
  private const MAX_DEPTH = 12;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['depth'] = ['default' => 8];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $depth_options = [];
    for ($depth = 1; $depth <= self::MAX_DEPTH; $depth++) {
      $depth_options[$depth] = $this->formatPlural($depth, '1 paragraph parent level', '@count paragraph parent levels');
    }

    $form['depth'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum parent paragraph levels'),
      '#description' => $this->t('How far Views should walk upward through paragraph parents before joining the node. Direct node parents are always checked first.'),
      '#options' => $depth_options,
      '#default_value' => (int) ($this->options['depth'] ?? 8),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();

    $depth = max(1, min(self::MAX_DEPTH, (int) ($this->options['depth'] ?? 8)));
    $paragraph_aliases = [$this->tableAlias];
    $left_alias = $this->tableAlias;
    $link_point = $this->relationship;
    if ($link_point === 'none' || $link_point === NULL || $link_point === '') {
      $link_point = $this->view->storage->get('base_table');
    }

    for ($level = 1; $level <= $depth; $level++) {
      $join = Views::pluginManager('join')->createInstance('casted_int_field_join', [
        'table' => 'paragraphs_item_field_data',
        'field' => 'id',
        'left_table' => $left_alias,
        'left_field' => 'parent_id',
        'cast' => 'left',
        'adjusted' => TRUE,
        'extra' => [
          [
            'table' => 'paragraphs_item_field_data',
            'field' => 'parent_type',
            'value' => 'paragraph',
          ],
        ],
      ]);

      $alias = $this->query->queueTable('paragraphs_item_field_data', $link_point, $join, 'nomads_parent_line_p' . $level);
      if (!$alias) {
        break;
      }

      $paragraph_aliases[] = $alias;
      $left_alias = $alias;
    }

    $node_id_cases = [];
    foreach ($paragraph_aliases as $alias) {
      $node_id_cases[] = "CASE WHEN $alias.parent_type = 'node' THEN $alias.parent_id END";
    }

    $node_id_expression = 'COALESCE(' . implode(', ', $node_id_cases) . ')';
    $node_id_expression = \Drupal::service('views.cast_sql')->getFieldAsInt($node_id_expression);

    $join = Views::pluginManager('join')->createInstance('standard', [
      'table' => 'node_field_data',
      'field' => 'nid',
      'left_table' => $this->tableAlias,
      'left_field' => 'id',
      'left_formula' => $node_id_expression,
      'adjusted' => TRUE,
      'type' => !empty($this->options['required']) ? 'INNER' : 'LEFT',
    ]);

    $alias = 'node_field_data_' . $this->table;
    $this->alias = $this->query->addRelationship($alias, $join, 'node_field_data', $link_point);

    $table_data = Views::viewsData()->get('node_field_data');
    if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
      $this->query->addTag($table_data['table']['base']['access query tag']);
    }
  }

}
