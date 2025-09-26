<?php

namespace Drupal\enhanced_user_cancellation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Enhanced User Cancellation module.
 */
class EnhancedUserCancellationConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['enhanced_user_cancellation.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enhanced_user_cancellation_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('enhanced_user_cancellation.settings');

    $form['deletion_period'] = [
      '#type' => 'number',
      '#title' => $this->t('Deletion Period (hours)'),
      '#description' => $this->t('Number of hours to wait before permanently deleting a user account after cancellation is requested.'),
      '#default_value' => $config->get('deletion_period') ?? 72,
      '#min' => 1,
      '#max' => 8760, // One year
      '#required' => TRUE,
    ];

    $form['email_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send email notifications'),
      '#description' => $this->t('Send email confirmation when users request account cancellation.'),
      '#default_value' => $config->get('email_notifications') ?? TRUE,
    ];

    $form['email_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Email Settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="email_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['email_settings']['email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Subject'),
      '#default_value' => $config->get('email_subject') ?? 'Account Cancellation Confirmation - [site:name]',
      '#description' => $this->t('Subject line for the confirmation email. You can use tokens.'),
    ];

    $form['email_settings']['email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Body'),
      '#default_value' => $config->get('email_body') ?? $this->getDefaultEmailBody(),
      '#description' => $this->t('Body of the confirmation email. You can use tokens.'),
      '#rows' => 10,
    ];

    $form['delete_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete user content'),
      '#description' => $this->t('Also delete content authored by the user when their account is deleted.'),
      '#default_value' => $config->get('delete_content') ?? TRUE,
    ];

    $form['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types to delete'),
      '#description' => $this->t('Select which types of content should be deleted with the user.'),
      '#options' => [
        'node' => $this->t('Nodes'),
        'comment' => $this->t('Comments'),
      ],
      '#default_value' => $config->get('content_types') ?? ['node', 'comment'],
      '#states' => [
        'visible' => [
          ':input[name="delete_content"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('enhanced_user_cancellation.settings');
    
    $config
      ->set('deletion_period', $form_state->getValue('deletion_period'))
      ->set('email_notifications', $form_state->getValue('email_notifications'))
      ->set('email_subject', $form_state->getValue('email_subject'))
      ->set('email_body', $form_state->getValue('email_body'))
      ->set('delete_content', $form_state->getValue('delete_content'))
      ->set('content_types', array_filter($form_state->getValue('content_types')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get the default email body.
   *
   * @return string
   *   The default email body text.
   */
  protected function getDefaultEmailBody() {
    return 'Hello [user:display-name],

Your account cancellation request has been received at [site:name].

Your account will be permanently deleted on [enhanced_user_cancellation:deletion-date] unless you take action to cancel this request.

If you change your mind, please contact site administration immediately.

This action cannot be undone after the deletion date.

Thank you,
The [site:name] team';
  }

}