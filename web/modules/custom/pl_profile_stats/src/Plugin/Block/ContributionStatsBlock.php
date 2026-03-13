<?php

namespace Drupal\pl_profile_stats\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Contribution Stats' Block.
 *
 * @Block(
 *   id = "pl_contribution_stats",
 *   admin_label = @Translation("Contribution Stats"),
 *   category = @Translation("PL Profile"),
 * )
 */
class ContributionStatsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = $this->routeMatch->getParameter('user');
    if (!$user instanceof UserInterface) {
      return [];
    }

    $uid = $user->id();

    // Count topics.
    $topic_count = $this->countNodes($uid, 'topic');
    // Count events.
    $event_count = $this->countNodes($uid, 'event');
    // Count comments.
    $comment_count = $this->countComments($uid);
    // Count groups.
    $group_count = $this->countGroups($uid);
    // Days since registration.
    $created = $user->getCreatedTime();
    $days_active = max(1, floor((time() - $created) / 86400));

    $build = [
      '#theme' => 'pl_contribution_stats',
      '#stats' => [
        'topics' => $topic_count,
        'events' => $event_count,
        'comments' => $comment_count,
        'groups' => $group_count,
        'days_active' => $days_active,
      ],
      '#user' => $user,
      '#cache' => ['max-age' => 0],
    ];

    return $build;
  }

  /**
   * Count nodes of a given type by user.
   */
  protected function countNodes($uid, $type) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('type', $type)
      ->condition('status', 1)
      ->count();
    return (int) $query->execute();
  }

  /**
   * Count comments by user.
   */
  protected function countComments($uid) {
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->count();
    return (int) $query->execute();
  }

  /**
   * Count groups the user is a member of.
   */
  protected function countGroups($uid) {
    try {
      $query = \Drupal::database()->select('group_relationship_field_data', 'gm')
        ->condition('gm.entity_id', $uid)
        ->condition('gm.type', '%group_membership', 'LIKE');
      $query->addExpression('COUNT(DISTINCT gm.gid)', 'cnt');
      return (int) $query->execute()->fetchField();
    }
    catch (\Exception $e) {
      return 0;
    }
  }

}
