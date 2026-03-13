<?php

namespace Drupal\pl_group_mission\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\group\Entity\GroupInterface;

/**
 * Provides a 'Group Mission Statement' block.
 *
 * Displays a summary of the group's description in the sidebar.
 *
 * @Block(
 *   id = "pl_group_mission",
 *   admin_label = @Translation("Group Mission Statement"),
 *   category = @Translation("PL Custom"),
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group", label = @Translation("Group"), required = FALSE)
 *   }
 * )
 */
class GroupMissionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Try to get group from context or route.
    $group = NULL;

    try {
      $group = $this->getContextValue('group');
    }
    catch (\Exception $e) {
      // Context not available; try route.
    }

    if (!$group) {
      $route_match = \Drupal::routeMatch();
      $group = $route_match->getParameter('group');
      if (is_numeric($group)) {
        $group = \Drupal::entityTypeManager()->getStorage('group')->load($group);
      }
    }

    if (!$group instanceof GroupInterface) {
      return [];
    }

    if (!$group->hasField('field_group_description') || $group->get('field_group_description')->isEmpty()) {
      return [];
    }

    $description = $group->get('field_group_description')->value;
    $summary = $this->truncateText($description, 300);
    $group_id = $group->id();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pl-group-mission'],
        'id' => 'pl-group-mission',
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('About this group'),
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $summary,
      ],
    ];

    // Add "Read more" link if text was truncated.
    if (mb_strlen($description) > 300) {
      $build['read_more'] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this->t('Read more'),
        '#attributes' => [
          'href' => "/group/{$group_id}/about",
          'class' => ['read-more-link'],
        ],
      ];
    }

    $build['#cache'] = [
      'contexts' => ['route'],
      'tags' => $group->getCacheTags(),
    ];

    return $build;
  }

  /**
   * Truncate text to a maximum length at a word boundary.
   */
  protected function truncateText($text, $max_length) {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $max_length) {
      return $text;
    }
    $truncated = mb_substr($text, 0, $max_length);
    $last_space = mb_strrpos($truncated, ' ');
    if ($last_space !== FALSE) {
      $truncated = mb_substr($truncated, 0, $last_space);
    }
    return $truncated . '…';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['group_list']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
