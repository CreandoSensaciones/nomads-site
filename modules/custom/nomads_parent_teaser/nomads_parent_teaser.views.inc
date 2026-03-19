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
  $data['paragraphs_item_field_data']['nomads_parent_teaser'] = [
    'title' => t('Paragraph parent teaser'),
    'help' => t('Renders the parent node of the paragraph in a chosen view mode.'),
    'field' => [
      'id' => 'paragraph_parent_teaser',
    ],
  ];
}
