<?php

namespace Drupal\enhanced_user_cancellation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides a user cancel confirm form.
 */
class UserCancelConfirmForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enhanced_user_cancel_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?User $user = NULL) {
    // Only allow users to cancel their own account or admins to cancel others
    $current_user = \Drupal::currentUser();
    if (!$user || ($user->id() != $current_user->id() && !$current_user->hasPermission('administer users'))) {
      $this->messenger()->addError($this->t('Access denied.'));
      return $form;
    }

    $form['user_id'] = [
      '#type' => 'hidden',
      '#value' => $user->id(),
    ];

    $form['confirmation'] = [
      '#type' => 'markup',
      '#markup' => '<div class="enhanced-cancellation-content">
        <p><strong>' . $this->t('Are you sure you want to cancel your account?') . '</strong></p>
        <p>' . $this->t('Your account will be marked for deletion and you will receive an email confirmation. You have 72 hours to change your mind before your account and all associated content is permanently deleted.') . '</p>
        <div class="messages messages--warning">
          <div class="messages__content">
            ' . $this->t('This action cannot be undone after the 72-hour grace period.') . '
          </div>
        </div>
      </div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel Account'),
      '#attributes' => [
        'class' => ['button', 'button--danger'],
      ],
    ];

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Keep Account'),
      '#url' => \Drupal\Core\Url::fromRoute('entity.user.canonical', ['user' => $user->id()]),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    // Attach the modal JavaScript
    $form['#attached']['library'][] = 'enhanced_user_cancellation/modal_cancellation';
    
    // Add form ID for JavaScript targeting
    $form['#attributes']['id'] = 'enhanced-user-cancel-confirm-form';
    
    // Add debugging markup
    $form['debug'] = [
      '#type' => 'markup',
      '#markup' => '<!-- Enhanced User Cancellation Form - JS should attach here -->',
      '#weight' => -100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_id = $form_state->getValue('user_id');
    $user = User::load($user_id);

    \Drupal::logger('enhanced_user_cancellation')
      ->info('UserCancelConfirmForm::submitForm called for user ID: @uid', ['@uid' => $user_id]);

    if (!$user) {
      \Drupal::logger('enhanced_user_cancellation')
        ->error('User not found with ID: @uid', ['@uid' => $user_id]);
      $this->messenger()->addError($this->t('User not found.'));
      return;
    }

    try {
      \Drupal::logger('enhanced_user_cancellation')
        ->info('Calling markUserForDeletion service for user: @username', ['@username' => $user->getAccountName()]);
      
      // Mark user for deletion using static service call
      $cancellation_service = \Drupal::service('enhanced_user_cancellation.cancellation_service');
      $cancellation_service->markUserForDeletion($user);
      
      \Drupal::logger('enhanced_user_cancellation')
        ->info('Service call completed for user: @username', ['@username' => $user->getAccountName()]);
      
      $this->messenger()->addStatus($this->t('Your account has been scheduled for deletion. You will receive a confirmation email.'));
      
      // Use proper base URL instead of hardcoded '/'
      $form_state->setRedirectUrl(Url::fromRoute('<front>'));
    }
    catch (\Exception $e) {
      \Drupal::logger('enhanced_user_cancellation')
        ->error('Exception in form submission: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while processing your request. Please try again.'));
    }
  }

}