<?php

declare(strict_types=1);

/**
 * @file
 * Views data integration for the Nomads Parent Teaser module.
 */

/**
 * Implements hook_views_data_alter().
 */
function nomads_parent_teaser_views_data_alter(array &$data): void {
  $data['paragraphs_item_field_data']['nomads_parent_node'] = [
    'title' => t('Paragraph parent node'),
    'help' => t('Relates a paragraph to its direct parent node using the Paragraphs parent metadata. Use this for parent node filters such as promoted, published, or content type.'),
    'relationship' => [
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'nid',
      'relationship field' => 'parent_id',
      'label' => t('Parent node'),
      'join_id' => 'casted_int_field_join',
      'cast' => 'left',
      'extra' => [
        [
          'table' => 'paragraphs_item_field_data',
          'field' => 'parent_type',
          'value' => 'node',
        ],
      ],
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_line_node'] = [
    'title' => t('Paragraph parent-line node'),
    'help' => t('Relates a paragraph to the first node found while walking up its parent chain. This supports nested paragraphs up to the configured depth.'),
    'relationship' => [
      'id' => 'paragraph_parent_line_node',
      'base' => 'node_field_data',
      'label' => t('Parent-line node'),
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_teaser'] = [
    'title' => t('Paragraph parent teaser'),
    'help' => t('Renders the parent node of the paragraph in a chosen view mode.'),
    'field' => [
      'id' => 'paragraph_parent_teaser',
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_title'] = [
    'title' => t('Paragraph parent title'),
    'help' => t('Renders the title of the parent node of the paragraph.'),
    'field' => [
      'id' => 'paragraph_parent_title',
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_marker_data'] = [
    'title' => t('Paragraph parent marker data'),
    'help' => t('Outputs lightweight marker metadata for the paragraph parent node.'),
    'field' => [
      'id' => 'paragraph_parent_marker_data',
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_teaser_trigger'] = [
    'title' => t('Paragraph parent teaser trigger'),
    'help' => t('Outputs a trigger link for loading the paragraph parent teaser by AJAX.'),
    'field' => [
      'id' => 'paragraph_parent_teaser_trigger',
    ],
  ];

  $data['paragraphs_item_field_data']['nomads_parent_type'] = [
    'title' => t('Paragraph parent type'),
    'help' => t('Renders the first stored value of the field_type field on the parent node of the paragraph.'),
    'field' => [
      'id' => 'paragraph_parent_type',
    ],
  ];
}
