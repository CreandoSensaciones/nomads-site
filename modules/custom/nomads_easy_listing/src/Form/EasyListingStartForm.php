<?php

namespace Drupal\nomads_easy_listing\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\TermInterface;

/**
 * Provides the first step for easy listing creation.
 */
class EasyListingStartForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'nomads_easy_listing_start_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $step = (int) ($form_state->get('step') ?? 1);
    $form_state->set('step', $step);

    $form['#attributes']['class'][] = 'nomads-easy-listing';
    $form['#prefix'] = '<div id="nomads-easy-listing-wrapper">';
    $form['#suffix'] = '</div>';

    if ($step === 1) {
      $form['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Which is the name of the initiative you like to list?'),
        '#required' => TRUE,
        '#maxlength' => 255,
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and continue'),
        '#ajax' => [
          'callback' => '::ajaxRebuild',
          'wrapper' => 'nomads-easy-listing-wrapper',
        ],
      ];

      return $form;
    }

    if ($step === 2) {
      $form['message'] = [
        '#markup' => $this->t('Step 2 placeholder. Content will be added later.'),
      ];
      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#ajax' => [
          'callback' => '::ajaxRebuild',
          'wrapper' => 'nomads-easy-listing-wrapper',
        ],
      ];
      return $form;
    }

    if ($step === 3) {
      $root_tid = (int) ($form_state->get('types_root_tid') ?? 0);
      $terms = $root_tid ? $this->loadBranchTerms($root_tid) : $this->loadRootTerms();

      $form['message'] = [
        '#markup' => $this->t('Which category fits best for your initiative?'),
      ];

      $form['grid'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-easy-listing__grid'],
        ],
      ];

      foreach ($terms as $term) {
        $form['grid']['term_' . $term->id()] = $this->buildTermCard(
          $term,
          $root_tid ? 'branch' : 'root'
        );
      }

      if (empty($terms)) {
        $form['empty'] = [
          '#markup' => $this->t('No terms available.'),
        ];
      }

      return $form;
    }

    $listing_id = $form_state->get('listing_id');
    $form['message'] = [
      '#markup' => $this->t('Step 4 placeholder. Listing created.'),
    ];

    if ($listing_id) {
      $edit_url = Url::fromRoute('entity.node.edit_form', ['node' => $listing_id]);
      $view_url = Url::fromRoute('entity.node.canonical', ['node' => $listing_id]);

      $raw_items = [
        Link::fromTextAndUrl($this->t('Edit listing'), $edit_url)->toRenderable(),
        Link::fromTextAndUrl($this->t('View listing'), $view_url)->toRenderable(),
      ];
      $render_items = [];
      foreach ($raw_items as $delta => $item) {
        if (is_array($item)) {
          $render_items[$delta] = $item;
        }
        else {
          $render_items[$delta] = ['#markup' => (string) $item];
        }
      }

      $form['links'] = [
        '#theme' => 'item_list',
        '#items' => $render_items,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $step = (int) ($form_state->get('step') ?? 1);
    if ($step === 1) {
      $title = trim((string) $form_state->getValue('title'));

      $node = Node::create([
        'type' => 'listing',
        'title' => $title,
        'langcode' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
        'uid' => \Drupal::currentUser()->id(),
      ]);
      $node->save();

      $form_state->set('listing_id', $node->id());
      $form_state->set('step', 2);
      $form_state->setRebuild();
      return;
    }

    if ($step === 2) {
      $form_state->set('step', 3);
      $form_state->setRebuild();
      return;
    }

    if ($step === 3) {
      $trigger = $form_state->getTriggeringElement();
      $term_id = isset($trigger['#term_id']) ? (int) $trigger['#term_id'] : 0;
      $stage = $trigger['#selection_stage'] ?? '';

      if ($term_id && $stage === 'root') {
        $form_state->set('types_root_tid', $term_id);
        $form_state->setRebuild();
        return;
      }

      if ($term_id && $stage === 'branch') {
        $listing_id = $form_state->get('listing_id');
        if ($listing_id) {
          $node = Node::load($listing_id);
          if ($node && $node->hasField('field_types')) {
            $node->set('field_types', [['target_id' => $term_id]]);
            $node->save();
          }
        }

        $form_state->set('step', 4);
        $form_state->setRebuild();
        return;
      }
    }

    return;
  }

  /**
   * AJAX callback to re-render the current step.
   */
  public function ajaxRebuild(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * Build a grid card for a taxonomy term.
   */
  private function buildTermCard(TermInterface $term, string $stage): array {
    $card = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-easy-listing__card'],
      ],
    ];

    if ($term->hasField('field_icon') && !$term->get('field_icon')->isEmpty()) {
      $card['icon'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-easy-listing__icon'],
        ],
        'media' => $term->get('field_icon')->view([
          'label' => 'hidden',
        ]),
      ];
    }

    $card['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => Html::escape($term->label()),
      '#attributes' => [
        'class' => ['nomads-easy-listing__title'],
      ],
    ];

    if ($term->hasField('field_easy_listing') && !$term->get('field_easy_listing')->isEmpty()) {
      $card['text'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['nomads-easy-listing__text'],
        ],
        'content' => $term->get('field_easy_listing')->view([
          'label' => 'hidden',
        ]),
      ];
    }

    $card['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['nomads-easy-listing__actions'],
      ],
    ];
    $card['actions']['select'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
      '#term_id' => $term->id(),
      '#selection_stage' => $stage,
      '#ajax' => [
        'callback' => '::ajaxRebuild',
        'wrapper' => 'nomads-easy-listing-wrapper',
      ],
    ];

    return $card;
  }

  /**
   * Load top-level terms for the type vocabulary.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The top-level terms.
   */
  private function loadRootTerms(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = [];
    $vocabularies = $this->getTypesVocabularies();

    foreach ($vocabularies as $vocabulary) {
      $tree = $storage->loadTree($vocabulary, 0, 1, TRUE);
      foreach ($tree as $item) {
        if (!empty($item->entity)) {
          $terms[$item->entity->id()] = $item->entity;
        }
      }

      if (empty($tree)) {
        foreach ($this->loadTermsByParent($vocabulary, 0) as $term) {
          $terms[$term->id()] = $term;
        }
      }

      if (empty($terms)) {
        foreach ($this->loadTermsByVocabulary($vocabulary) as $term) {
          if ($term->get('parent')->isEmpty()) {
            $terms[$term->id()] = $term;
          }
        }
      }
    }

    return array_values($terms);
  }

  /**
   * Load all descendants of a term in the type vocabulary.
   *
   * @param int $root_tid
   *   The root term id.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The descendant terms in a flat list.
   */
  private function loadBranchTerms(int $root_tid): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = [];
    $root = $storage->load($root_tid);
    if (!$root instanceof TermInterface) {
      return [];
    }

    $tree = $storage->loadTree($root->bundle(), $root_tid, NULL, TRUE);

    foreach ($tree as $item) {
      if (!empty($item->entity)) {
        $terms[] = $item->entity;
      }
    }

    if (empty($terms)) {
      foreach ($this->loadTermsByDescendants($root->bundle(), $root_tid) as $term) {
        $terms[] = $term;
      }
    }

    if (empty($terms)) {
      $terms[] = $root;
    }

    return $terms;
  }

  /**
   * Resolve the taxonomy vocabularies used by field_types on listings.
   *
   * @return string[]
   *   Vocabulary machine names.
   */
  private function getTypesVocabularies(): array {
    $field = FieldConfig::loadByName('node', 'listing', 'field_types');
    if (!$field) {
      return ['type'];
    }

    $handler_settings = $field->getSetting('handler_settings');
    if (!empty($handler_settings['target_bundles'])) {
      return array_keys($handler_settings['target_bundles']);
    }

    return ['type'];
  }

  /**
   * Load taxonomy terms by parent without access checks.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param int $parent
   *   Parent term ID.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Terms under the parent, ordered by weight and name.
   */
  private function loadTermsByParent(string $vocabulary, int $parent): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->condition('parent', $parent)
      ->sort('weight')
      ->sort('name');

    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

  /**
   * Load all descendant terms for a parent without access checks.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param int $parent
   *   Parent term ID.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Descendant terms in a flat list.
   */
  private function loadTermsByDescendants(string $vocabulary, int $parent): array {
    $seen = [];
    $queue = [$parent];
    $results = [];

    while (!empty($queue)) {
      $current = (int) array_shift($queue);
      foreach ($this->loadTermsByParent($vocabulary, $current) as $term) {
        $tid = $term->id();
        if (isset($seen[$tid])) {
          continue;
        }
        $seen[$tid] = TRUE;
        $results[] = $term;
        $queue[] = $tid;
      }
    }

    return $results;
  }

  /**
   * Load all terms in a vocabulary without access checks.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Terms in the vocabulary ordered by weight and name.
   */
  private function loadTermsByVocabulary(string $vocabulary): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->sort('weight')
      ->sort('name');

    $ids = $query->execute();
    return $ids ? $storage->loadMultiple($ids) : [];
  }

}
