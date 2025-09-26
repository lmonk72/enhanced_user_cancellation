<?php

namespace Drupal\enhanced_user_cancellation\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

/**
 * Service for managing enhanced user cancellation.
 */
class UserCancellationService {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a UserCancellationService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    QueueFactory $queue_factory,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Mark a user for deletion.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity to mark for deletion.
   */
  public function markUserForDeletion(User $user) {
    // Check if fields exist - if not, don't proceed
    if (!$user->hasField('field_pending_deletion') || !$user->hasField('field_deletion_requested')) {
      \Drupal::logger('enhanced_user_cancellation')
        ->error('Cannot mark user for deletion: required fields do not exist. User ID: @uid', ['@uid' => $user->id()]);
      return;
    }

    \Drupal::logger('enhanced_user_cancellation')
      ->info('Starting deletion process for user @username (ID: @uid)', [
        '@username' => $user->getAccountName(),
        '@uid' => $user->id(),
      ]);

    // Set pending deletion timestamp
    $deletion_time = \Drupal::time()->getRequestTime() + (72 * 60 * 60); // 72 hours from now
    $user->set('field_pending_deletion', $deletion_time);
    $user->set('field_deletion_requested', \Drupal::time()->getRequestTime());
    
    // Block the user account
    $user->set('status', 0);
    $user->save();

    \Drupal::logger('enhanced_user_cancellation')
      ->info('User @username (ID: @uid) fields updated and saved', [
        '@username' => $user->getAccountName(),
        '@uid' => $user->id(),
      ]);

    // Send confirmation email
    $this->sendDeletionEmail($user);

    // Log out the user if it's the current user
    $current_user = \Drupal::currentUser();
    if ($current_user->id() == $user->id()) {
      // Log out the user
      user_logout();
      
      \Drupal::logger('enhanced_user_cancellation')
        ->info('User @username (ID: @uid) logged out after account cancellation', [
          '@username' => $user->getAccountName(),
          '@uid' => $user->id(),
        ]);
    }

    // Add to deletion queue
    $queue = $this->queueFactory->get('enhanced_user_deletion');
    $queue->createItem([
      'user_id' => $user->id(),
      'deletion_time' => $deletion_time,
    ]);

    \Drupal::logger('enhanced_user_cancellation')
      ->info('User @username (ID: @uid) marked for deletion successfully', [
        '@username' => $user->getAccountName(),
        '@uid' => $user->id(),
      ]);
  }

  /**
   * Send deletion confirmation email.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   */
  protected function sendDeletionEmail(User $user) {
    $params = [
      'user' => $user,
      'message' => $this->buildDeletionEmailMessage($user),
    ];

    \Drupal::service('plugin.manager.mail')->mail(
      'enhanced_user_cancellation',
      'pending_deletion',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params
    );
  }

  /**
   * Build the deletion email message.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return string
   *   The email message.
   */
  protected function buildDeletionEmailMessage(User $user) {
    $site_name = $this->configFactory->get('system.site')->get('name') ?: 'this site';
    
    // Safely get deletion time
    if (!$user->hasField('field_pending_deletion') || !$user->get('field_pending_deletion')->value) {
      $deletion_time = \Drupal::time()->getRequestTime() + (72 * 60 * 60);
    } else {
      $deletion_time = $user->get('field_pending_deletion')->value;
    }
    
    $deletion_date = \Drupal::service('date.formatter')->format($deletion_time, 'medium');
    $username = $user->getDisplayName() ?: $user->getAccountName();

    $message = $this->t('Hello @username,', ['@username' => $username]) . "\n\n";
    $message .= $this->t('Your account cancellation request has been received at @site.', ['@site' => $site_name]) . "\n\n";
    $message .= $this->t('Your account will be permanently deleted on @date unless you take action to cancel this request.', ['@date' => $deletion_date]) . "\n\n";
    $message .= $this->t('If you change your mind, please contact site administration immediately.') . "\n\n";
    $message .= $this->t('This action cannot be undone after the deletion date.') . "\n\n";
    $message .= $this->t('Thank you,') . "\n";
    $message .= $this->t('The @site team', ['@site' => $site_name]);

    return $message;
  }

  /**
   * Process scheduled deletions during cron.
   */
  public function processScheduledDeletions() {
    $current_time = \Drupal::time()->getRequestTime();
    
    // Find users ready for deletion
    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('field_pending_deletion', $current_time, '<=')
      ->condition('field_pending_deletion', 0, '>')
      ->accessCheck(FALSE);
      
    $uids = $query->execute();
    
    if (empty($uids)) {
      return;
    }

    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    
    foreach ($users as $user) {
      $this->deleteUserAndContent($user);
    }
  }

  /**
   * Delete a user and their content.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity to delete.
   */
  public function deleteUserAndContent(User $user) {
    try {
      $username = $user->getAccountName();
      $uid = $user->id();

      // Delete user's content (nodes, comments, etc.)
      $this->deleteUserContent($user);

      // Delete the user
      $user->delete();

      \Drupal::logger('enhanced_user_cancellation')
        ->info('User @username (ID: @uid) and associated content deleted', [
          '@username' => $username,
          '@uid' => $uid,
        ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('enhanced_user_cancellation')
        ->error('Error deleting user @username (ID: @uid): @error', [
          '@username' => $user->getAccountName(),
          '@uid' => $user->id(),
          '@error' => $e->getMessage(),
        ]);
    }
  }

  /**
   * Delete content associated with a user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   */
  protected function deleteUserContent(User $user) {
    $uid = $user->id();

    // Delete nodes authored by this user
    $node_storage = $this->entityTypeManager->getStorage('node');
    $nids = $node_storage->getQuery()
      ->condition('uid', $uid)
      ->accessCheck(FALSE)
      ->execute();
    
    if (!empty($nids)) {
      $nodes = $node_storage->loadMultiple($nids);
      $node_storage->delete($nodes);
    }

    // Delete comments by this user
    if ($this->entityTypeManager->hasDefinition('comment')) {
      $comment_storage = $this->entityTypeManager->getStorage('comment');
      $cids = $comment_storage->getQuery()
        ->condition('uid', $uid)
        ->accessCheck(FALSE)
        ->execute();
      
      if (!empty($cids)) {
        $comments = $comment_storage->loadMultiple($cids);
        $comment_storage->delete($comments);
      }
    }

    // Add more content types as needed
  }

  /**
   * Get pending deletion users.
   *
   * @return array
   *   Array of users pending deletion.
   */
  public function getPendingDeletionUsers() {
    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('field_pending_deletion', 0, '>')
      ->sort('field_deletion_requested', 'DESC')
      ->accessCheck(FALSE);
      
    $uids = $query->execute();
    
    if (empty($uids)) {
      return [];
    }

    return $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
  }

  /**
   * Cancel a pending deletion request.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   */
  public function cancelPendingDeletion(User $user) {
    if ($user->hasField('field_pending_deletion')) {
      $user->set('field_pending_deletion', 0);
      $user->set('field_deletion_requested', 0);
      $user->set('status', 1); // Reactivate the account
      $user->save();

      \Drupal::logger('enhanced_user_cancellation')
        ->info('Pending deletion cancelled for user @username (ID: @uid)', [
          '@username' => $user->getAccountName(),
          '@uid' => $user->id(),
        ]);
    }
  }

}