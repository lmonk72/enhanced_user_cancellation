<?php

namespace Drupal\enhanced_user_cancellation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Checks access for enhanced user cancellation routes.
 */
class EnhancedUserCancellationAccessCheck implements AccessInterface {

  /**
   * Checks access to the enhanced user cancellation form.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose account is being cancelled.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(UserInterface $user, AccountInterface $account) {
    // Allow if user is cancelling their own account and has permission
    if ($user->id() == $account->id() && $account->hasPermission('cancel own user account enhanced')) {
      return AccessResult::allowed();
    }
    
    // Allow if user is an admin
    if ($account->hasPermission('administer enhanced user cancellation')) {
      return AccessResult::allowed();
    }
    
    // Deny all other access
    return AccessResult::forbidden();
  }

}