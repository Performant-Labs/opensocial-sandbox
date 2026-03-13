<?php

namespace Drupal\pl_profile_stats\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Profile Completeness' Block.
 *
 * @Block(
 *   id = "pl_profile_completeness",
 *   admin_label = @Translation("Profile Completeness"),
 *   category = @Translation("PL Profile"),
 * )
 */
class ProfileCompletenessBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    // Load the user's profile entity.
    $profiles = $this->entityTypeManager->getStorage('profile')
      ->loadByProperties(['uid' => $uid, 'type' => 'profile']);
    $profile = reset($profiles);

    if (!$profile) {
      return [
        '#markup' => '<div class="pl-profile-completeness"><p>No profile found.</p></div>',
      ];
    }

    // Fields to check for completeness.
    $fields_to_check = [
      'field_profile_first_name' => 'First name',
      'field_profile_last_name' => 'Last name',
      'field_profile_image' => 'Profile image',
      'field_profile_organization' => 'Organization',
      'field_profile_function' => 'Job title',
      'field_profile_self_introduction' => 'Bio',
      'field_profile_expertise' => 'Expertise',
      'field_profile_interests' => 'Interests',
      'field_profile_address' => 'Location',
    ];

    $filled = 0;
    $total = count($fields_to_check);
    $missing = [];

    foreach ($fields_to_check as $field_name => $label) {
      if ($profile->hasField($field_name) && !$profile->get($field_name)->isEmpty()) {
        $filled++;
      }
      else {
        $missing[] = $label;
      }
    }

    $percentage = $total > 0 ? round(($filled / $total) * 100) : 0;

    $build = [
      '#theme' => 'pl_profile_completeness',
      '#percentage' => $percentage,
      '#filled' => $filled,
      '#total' => $total,
      '#missing' => $missing,
      '#user' => $user,
      '#cache' => ['max-age' => 0],
    ];

    return $build;
  }

}
