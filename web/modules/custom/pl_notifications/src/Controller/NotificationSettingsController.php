<?php

namespace Drupal\pl_notifications\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the notification settings page.
 */
class NotificationSettingsController extends ControllerBase {

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * {@inheritdoc}
   */
  public function __construct(FlagServiceInterface $flag_service) {
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag')
    );
  }

  /**
   * Builds the notification settings page.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   *
   * @return array
   *   Render array.
   */
  public function page(UserInterface $user) {
    $current_user = $this->currentUser();

    // Only allow viewing own settings or as admin.
    if ($current_user->id() != $user->id() && !$current_user->hasPermission('administer users')) {
      return [
        'access_denied' => [
          '#markup' => '<p>' . $this->t('You do not have permission to view these settings.') . '</p>',
        ],
      ];
    }

    // Handle toggle_disable query parameter FIRST (before building page).
    $request = \Drupal::request();
    if ($request->query->get('toggle_disable')) {
      $current_disabled = \Drupal::state()->get('pl_notifications_disabled_' . $user->id(), FALSE);
      $new_state = !$current_disabled;
      \Drupal::state()->set('pl_notifications_disabled_' . $user->id(), $new_state);
      $this->messenger()->addStatus(
        $new_state
          ? $this->t('All notifications have been temporarily disabled.')
          : $this->t('Notifications have been re-enabled.')
      );
      return $this->redirect('pl_notifications.notification_settings', ['user' => $user->id()]);
    }

    $build = [];

    // Disable all caching on this page so state changes are reflected immediately.
    $build['#cache'] = ['max-age' => 0];

    // --- Active Subscriptions ---
    $subscriptions = $this->getSubscriptions($user);
    $total_count = count($subscriptions);

    $build['summary'] = [
      '#markup' => '<div class="pl-notifications-summary"><h3>' .
        $this->t('Active Subscriptions: @count', ['@count' => $total_count]) .
        '</h3></div>',
    ];

    // Check if notifications are temporarily disabled.
    $disabled = \Drupal::state()->get('pl_notifications_disabled_' . $user->id(), FALSE);

    if ($disabled) {
      $build['disabled_notice'] = [
        '#markup' => '<div class="messages messages--warning"><p>' .
          $this->t('All notifications are currently <strong>temporarily disabled</strong>.') .
          '</p></div>',
      ];
    }

    // --- Subscriptions table ---
    if (!empty($subscriptions)) {
      $header = [
        $this->t('Type'),
        $this->t('Title'),
        $this->t('Actions'),
      ];

      $rows = [];
      foreach ($subscriptions as $sub) {
        $remove_url = Url::fromRoute('flag.action_link_unflag', [
          'flag' => $sub['flag_id'],
          'entity_id' => $sub['entity_id'],
        ]);
        $rows[] = [
          $sub['type'],
          $sub['title'],
          Link::fromTextAndUrl($this->t('Remove'), $remove_url)->toString(),
        ];
      }

      $build['subscriptions_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No active subscriptions.'),
        '#attributes' => ['class' => ['pl-notifications-subscriptions']],
      ];
    }
    else {
      $build['no_subs'] = [
        '#markup' => '<p>' . $this->t('You have no active subscriptions.') . '</p>',
      ];
    }

    // --- Actions ---
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pl-notifications-actions']],
    ];

    // Toggle disable/enable all.
    $build['actions']['toggle_disable'] = [
      '#type' => 'link',
      '#title' => $disabled
        ? $this->t('Re-enable all notifications')
        : $this->t('Temporarily disable all notifications'),
      '#url' => Url::fromRoute('pl_notifications.notification_settings', [
        'user' => $user->id(),
      ], ['query' => ['toggle_disable' => 1]]),
      '#attributes' => [
        'class' => ['button', $disabled ? 'button--primary' : 'button--danger'],
        'id' => 'pl-notifications-toggle-disable',
      ],
    ];

    // Cancel all.
    if (!empty($subscriptions)) {
      $build['actions']['cancel_all'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel all subscriptions'),
        '#url' => Url::fromRoute('pl_notifications.cancel_all', [
          'user' => $user->id(),
        ]),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'id' => 'pl-notifications-cancel-all',
        ],
      ];
    }

    // Link to email settings.
    $build['email_settings_link'] = [
      '#markup' => '<p>' . Link::fromTextAndUrl(
        $this->t('Edit email notification frequency settings →'),
        Url::fromRoute('entity.user.edit_form', ['user' => $user->id()])
      )->toString() . '</p>',
    ];

    return $build;
  }

  /**
   * Get all flag-based subscriptions for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return array
   *   Array of subscription arrays with keys: type, title, flag_id, entity_id.
   */
  protected function getSubscriptions(UserInterface $user) {
    $subscriptions = [];

    // Get all follow flags.
    $flag_ids = ['follow_content'];

    // Check for follow_user flag (from social_follow_user).
    $follow_user_flag = $this->flagService->getFlagById('follow_user');
    if ($follow_user_flag) {
      $flag_ids[] = 'follow_user';
    }

    foreach ($flag_ids as $flag_id) {
      $flag = $this->flagService->getFlagById($flag_id);
      if (!$flag) {
        continue;
      }

      $flaggings = $this->flagService->getAllEntityFlaggings($flag, $user);
      foreach ($flaggings as $flagging) {
        $entity = $flagging->getFlaggable();
        if ($entity) {
          $type_label = $flag_id === 'follow_content' ? $this->t('Content') : $this->t('User');
          $subscriptions[] = [
            'type' => $type_label,
            'title' => $entity->label(),
            'flag_id' => $flag_id,
            'entity_id' => $entity->id(),
          ];
        }
      }
    }

    return $subscriptions;
  }

}
