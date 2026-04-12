<?php

declare(strict_types=1);

namespace Drupal\nomads_parent_teaser\Plugin\views\area;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;

#[ViewsArea('nomads_parent_teaser_map_panel')]
final class NomadsParentTeaserMapPanel extends AreaPluginBase {

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['panel_title'] = ['default' => ''];
    $options['empty_message'] = ['default' => 'Select a listing on the map to view details.'];
    $options['panel_classes'] = ['default' => ''];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['panel_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Panel title'),
      '#default_value' => $this->options['panel_title'] ?? '',
      '#description' => $this->t('Optional heading shown above the detail panel content.'),
    ];

    $form['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty message'),
      '#default_value' => $this->options['empty_message'] ?? 'Select a listing on the map to view details.',
      '#required' => TRUE,
    ];

    $form['panel_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Additional classes'),
      '#default_value' => $this->options['panel_classes'] ?? '',
      '#description' => $this->t('Space-separated CSS classes added to the outer panel wrapper.'),
    ];
  }

  public function render($empty = FALSE) {
    $classes = preg_split('/\s+/', trim((string) ($this->options['panel_classes'] ?? ''))) ?: [];

    return nomads_parent_teaser_build_map_panel([
      'panel_title' => trim((string) ($this->options['panel_title'] ?? '')),
      'empty_message' => trim((string) ($this->options['empty_message'] ?? '')) ?: 'Select a listing on the map to view details.',
      'panel_classes' => $classes,
    ]);
  }

}
