<?php

namespace Drupal\optgroup_taxonomy_select\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;

/**
 * Plugin implementation of the 'optgroup_taxonomy_select' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "optgroup_taxonomy_select",
 *   label = @Translation("OptGroup Taxonomy Select"),
 *   entity_types = {"taxonomy_term"},
 *   group = "optgroup_taxonomy_select",
 *   weight = 0
 * )
 */
class OptGroupEntityReferenceSelection extends TermSelection {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // We (currently) only support one vocabulary at a time, so convert the
    // checkboxes to radios.
    $form['target_bundles']['#type'] = 'radios';

    // Target bundle is stored as an array, but radios element expects a string.
    $form['target_bundles']['#default_value'] = reset($form['target_bundles']['#default_value']);
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public static function elementValidateFilter(&$element, FormStateInterface $form_state) {
    // Convert submitted value to array.
    $element['#value'] = [$element['#value']];
    $form_state->setValueForElement($element, $element['#value']);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    if ($match || $limit) {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $options = [];

    $bundles = $this->entityTypeBundleInfo->getBundleInfo('taxonomy_term');
    $bundle_names = $this->getConfiguration()['target_bundles'] ?: array_keys($bundles);

    $has_admin_access = $this->currentUser->hasPermission('administer taxonomy');
    $unpublished_terms = [];
    foreach ($bundle_names as $bundle) {
      if (($vocabulary = Vocabulary::load($bundle)) === NULL) {
        // Couldn't load vocabulary, so move on to the target bundle.
        continue;
      }
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary->id(), 0, NULL, TRUE);
      if (!$terms) {
        // No terms, so move on to the next target bundle.
        continue;
      }
      $parent = NULL;
      foreach ($terms as $term) {
        // If there is a reason to not show this term...
        if (!$has_admin_access && (!$term->isPublished() || in_array($term->parent->target_id, $unpublished_terms))) {
          // ...add it to the list of unpublished terms and move on to the next
          // term.
          $unpublished_terms[] = $term->id();
          continue;
        }
        $label = Html::escape($this->entityRepository->getTranslationFromContext($term)->label());
        // If this is a top level term...
        if ($term->depth === 0) {
          // ...don't add it to the options, but use it
          $parent = $label;
        }
        else {
          if ($parent) {
            $options[$vocabulary->id()][$parent][$term->id()] = str_repeat('-', $term->depth - 1) . $label;
          }
        }
      }
    }

    return $options;
  }

}
