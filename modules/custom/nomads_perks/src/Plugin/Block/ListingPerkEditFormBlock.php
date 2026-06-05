<?php

namespace Drupal\nomads_perks\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing perk edit form block.
 */
#[Block(
  id: 'nomads_perks_listing_perk_edit_form',
  admin_label: new TranslatableMarkup('Listing perk edit form'),
  category: new TranslatableMarkup('Nomads')
)]
class ListingPerkEditFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ListingPerkEditFormBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityFormBuilderInterface $entityFormBuilder,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity.form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->getRouteListing();
    if (!$node instanceof NodeInterface) {
      return [];
    }

    if (!$this->hasPerkFormDisplay($node)) {
      return [
        '#markup' => $this->t('The Listing perk form display is not configured.'),
        '#cache' => [
          'contexts' => ['route', 'user.permissions'],
          'tags' => ['config:core.entity_form_mode.node.perk', 'config:core.entity_form_display.node.listing.perk'],
        ],
      ];
    }

    $request = \Drupal::request();
    $previous = $request->attributes->get('_nomads_perks_force_perk_form_mode');
    $request->attributes->set('_nomads_perks_force_perk_form_mode', TRUE);
    try {
      $form = $this->entityFormBuilder->getForm($node, 'edit');
    }
    finally {
      if ($previous === NULL) {
        $request->attributes->remove('_nomads_perks_force_perk_form_mode');
      }
      else {
        $request->attributes->set('_nomads_perks_force_perk_form_mode', $previous);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nomads-perks-listing-form'],
      ],
      'form' => $form,
      '#cache' => [
        'contexts' => ['route', 'user.permissions'],
        'tags' => $node->getCacheTags(),
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    $node = $this->getRouteListing();
    if (!$node instanceof NodeInterface) {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }

    return $node
      ->access('update', $account, TRUE)
      ->addCacheContexts(['route'])
      ->addCacheableDependency($node);
  }

  /**
   * Gets the current route listing node.
   */
  protected function getRouteListing(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    if (is_numeric($node)) {
      $node = $this->entityTypeManager->getStorage('node')->load($node);
    }

    return $node instanceof NodeInterface && $node->bundle() === 'listing' ? $node : NULL;
  }

  /**
   * Checks that the perk form mode and listing form display are configured.
   */
  protected function hasPerkFormDisplay(NodeInterface $node): bool {
    if ($node->bundle() !== 'listing') {
      return FALSE;
    }

    $form_mode = $this->entityTypeManager
      ->getStorage('entity_form_mode')
      ->load('node.perk');
    $form_display = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load('node.listing.perk');

    return $form_mode && $form_mode->status() && $form_display && $form_display->status();
  }

}
