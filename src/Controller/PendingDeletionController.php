<?php

namespace Drupal\enhanced_user_cancellation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\enhanced_user_cancellation\Service\UserCancellationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for pending deletion admin page.
 */
class PendingDeletionController extends ControllerBase {

  /**
   * The user cancellation service.
   *
   * @var \Drupal\enhanced_user_cancellation\Service\UserCancellationService
   */
  protected $cancellationService;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a PendingDeletionController object.
   *
   * @param \Drupal\enhanced_user_cancellation\Service\UserCancellationService $cancellation_service
   *   The user cancellation service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(UserCancellationService $cancellation_service, DateFormatterInterface $date_formatter) {
    $this->cancellationService = $cancellation_service;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('enhanced_user_cancellation.cancellation_service'),
      $container->get('date.formatter')
    );
  }

  /**
   * Display pending deletion users.
   *
   * @return array
   *   A render array for the page.
   */
  public function adminPage() {
    $pending_users = $this->cancellationService->getPendingDeletionUsers();

    $header = [
      $this->t('User'),
      $this->t('Email'),
      $this->t('Request Date'),
      $this->t('Deletion Date'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];
    $current_time = \Drupal::time()->getRequestTime();

    foreach ($pending_users as $user) {
      $deletion_time = $user->get('field_pending_deletion')->value;
      $request_time = $user->get('field_deletion_requested')->value;

      // Determine status
      $status = $deletion_time > $current_time ? $this->t('Pending') : $this->t('Ready for deletion');
      $status_class = $deletion_time > $current_time ? 'pending' : 'ready';

      $operations = [];
      
      // Delete now link
      $operations[] = Link::createFromRoute(
        $this->t('Delete Now'),
        'enhanced_user_cancellation.admin_delete',
        ['user' => $user->id()],
        ['attributes' => ['class' => ['button', 'button--danger', 'button--small']]]
      );

      // Cancel deletion link
      $operations[] = Link::createFromRoute(
        $this->t('Cancel Deletion'),
        'enhanced_user_cancellation.admin_cancel',
        ['user' => $user->id()],
        ['attributes' => ['class' => ['button', 'button--small']]]
      );

      $rows[] = [
        'data' => [
          Link::createFromRoute($user->getDisplayName(), 'entity.user.canonical', ['user' => $user->id()]),
          $user->getEmail(),
          $this->dateFormatter->format($request_time, 'short'),
          $this->dateFormatter->format($deletion_time, 'short'),
          [
            'data' => $status,
            'class' => [$status_class],
          ],
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'delete' => [
                  'title' => $this->t('Delete Now'),
                  'url' => Url::fromRoute('enhanced_user_cancellation.admin_delete', ['user' => $user->id()]),
                  'attributes' => ['class' => ['button', 'button--danger', 'button--small']],
                ],
                'cancel' => [
                  'title' => $this->t('Cancel Deletion'),
                  'url' => Url::fromRoute('enhanced_user_cancellation.admin_cancel', ['user' => $user->id()]),
                  'attributes' => ['class' => ['button', 'button--small']],
                ],
              ],
            ],
          ],
        ],
        'class' => [$status_class],
      ];
    }

    $build = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No users are currently pending deletion.'),
      '#attached' => [
        'library' => ['enhanced_user_cancellation/admin_styles'],
      ],
    ];

    // Add summary information
    $build['summary'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->formatPlural(
        count($pending_users),
        'There is 1 user pending deletion.',
        'There are @count users pending deletion.'
      ) . '</p>',
      '#weight' => -10,
    ];

    return $build;
  }

}