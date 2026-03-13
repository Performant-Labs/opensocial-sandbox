<?php

namespace Drupal\pl_notifications\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to cancel all subscriptions.
 */
class CancelAllSubscriptionsForm extends ConfirmFormBase {

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The user whose subscriptions will be cancelled.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pl_notifications_cancel_all';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel all subscriptions?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will remove all follow/subscription flags. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('pl_notifications.notification_settings', [
      'user' => $this->user->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $flag_ids = ['follow_content', 'follow_user'];
    $count = 0;

    foreach ($flag_ids as $flag_id) {
      $flag = $this->flagService->getFlagById($flag_id);
      if (!$flag) {
        continue;
      }

      $flaggings = $this->flagService->getAllEntityFlaggings($flag, $this->user);
      foreach ($flaggings as $flagging) {
        $this->flagService->unflag($flag, $flagging->getFlaggable(), $this->user);
        $count++;
      }
    }

    $this->messenger()->addStatus($this->t('Cancelled @count subscriptions.', ['@count' => $count]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
