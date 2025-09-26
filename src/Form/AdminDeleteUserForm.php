<?php

namespace Drupal\enhanced_user_cancellation\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\enhanced_user_cancellation\Service\UserCancellationService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for admin to delete users immediately.
 */
class AdminDeleteUserForm extends ConfirmFormBase {

  /**
   * The user cancellation service.
   *
   * @var \Drupal\enhanced_user_cancellation\Service\UserCancellationService
   */
  protected $cancellationService;

  /**
   * The user to delete.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Constructs a new AdminDeleteUserForm.
   *
   * @param \Drupal\enhanced_user_cancellation\Service\UserCancellationService $cancellation_service
   *   The user cancellation service.
   */
  public function __construct(UserCancellationService $cancellation_service) {
    $this->cancellationService = $cancellation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('enhanced_user_cancellation.cancellation_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enhanced_user_cancellation_admin_delete_user';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?User $user = NULL) {
    if (!$user) {
      throw new \InvalidArgumentException('User parameter is required.');
    }

    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the user %name and all associated content?', [
      '%name' => $this->user->getDisplayName(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. The user account and all content authored by this user will be permanently deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete User and Content');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('enhanced_user_cancellation.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->cancellationService->deleteUserAndContent($this->user);
      
      $this->messenger()->addStatus($this->t('User %name and all associated content have been deleted.', [
        '%name' => $this->user->getDisplayName(),
      ]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while deleting the user. Please try again.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}