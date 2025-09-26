<?php

namespace Drupal\enhanced_user_cancellation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\enhanced_user_cancellation\Service\UserCancellationService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes user deletion queue items.
 *
 * @QueueWorker(
 *   id = "enhanced_user_deletion",
 *   title = @Translation("Enhanced User Deletion Queue"),
 *   cron = {"time" = 60}
 * )
 */
class UserDeletionQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The user cancellation service.
   *
   * @var \Drupal\enhanced_user_cancellation\Service\UserCancellationService
   */
  protected $cancellationService;

  /**
   * Constructs a UserDeletionQueue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\enhanced_user_cancellation\Service\UserCancellationService $cancellation_service
   *   The user cancellation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserCancellationService $cancellation_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cancellationService = $cancellation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('enhanced_user_cancellation.cancellation_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $user_id = $data['user_id'];
    $deletion_time = $data['deletion_time'];
    $current_time = \Drupal::time()->getRequestTime();

    // Only delete if the deletion time has passed
    if ($current_time >= $deletion_time) {
      $user = User::load($user_id);
      if ($user) {
        // Double check the user still has pending deletion
        if ($user->hasField('field_pending_deletion') && $user->get('field_pending_deletion')->value > 0) {
          $this->cancellationService->deleteUserAndContent($user);
        }
      }
    }
    else {
      // Re-queue for later if deletion time hasn't arrived yet
      $queue = \Drupal::queue('enhanced_user_deletion');
      $queue->createItem($data);
    }
  }

}