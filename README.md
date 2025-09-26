# Enhanced User Cancellation

A Drupal module that provides an enhanced user account cancellation process with a grace period, email notifications, and administrative oversight.

## Features

- **Modal Confirmation Dialog**: Users see a professional modal dialog when canceling their account instead of a basic form
- **72-Hour Grace Period**: Users have 72 hours to change their mind before permanent deletion
- **Email Notifications**: Automatic email confirmations sent to users when they initiate cancellation
- **Pending Deletion State**: User accounts are marked as "pending deletion" and blocked during the grace period
- **Administrative Interface**: Admins can view and manage all pending account deletions
- **Automatic Logout**: Users are automatically logged out when they cancel their account
- **Content Preservation**: During the grace period, user content is preserved
- **Queue-Based Processing**: Uses Drupal's queue system for reliable scheduled deletions

## Installation

1. Place the module in your `modules/custom/` directory
2. Enable the module via Drush or the admin interface:
   ```bash
   drush en enhanced_user_cancellation
   ```
3. Clear cache:
   ```bash
   drush cache:rebuild
   ```
4. Configure permissions at `/admin/people/permissions`

## Configuration

### Permissions

The module provides these permissions:
- **Access enhanced user cancellation**: Allows users to cancel their own accounts
- **Administer enhanced user cancellation**: Allows access to admin interface and settings

### Settings

Configure the module at: `/admin/config/people/enhanced-user-cancellation`

## Usage

### For Users

1. Navigate to your user account edit page (`/user/{uid}/edit`)
2. Click the "Cancel Account (Enhanced)" link
3. Confirm in the modal dialog
4. Check your email for confirmation
5. You have 72 hours to log back in to cancel the deletion

### For Administrators

**View Pending Deletions:**
- Visit `/admin/people/pending-deletion`
- Or navigate to People → Pending Account Deletions

**Manage Pending Deletions:**
- Cancel a pending deletion to restore the account
- Force immediate deletion if needed
- View deletion request dates and scheduled deletion times

## Technical Details

### Database Fields

The module adds two fields to user entities:
- `field_pending_deletion`: Timestamp when the account should be deleted
- `field_deletion_requested`: Timestamp when deletion was requested

These fields are hidden from user displays and forms.

### Queue Processing

Scheduled deletions are processed via Drupal's cron system using the `enhanced_user_deletion` queue.

### Email Templates

The module uses Drupal's mail system with the key `user_deletion_confirmation`.

### Hooks Implemented

- `hook_form_alter()`: Adds the cancellation link to user edit forms
- `hook_mail()`: Defines email templates
- `hook_cron()`: Processes scheduled deletions

### Services

- `enhanced_user_cancellation.cancellation_service`: Main service for handling user cancellations

### Routes

- `/user/{user}/cancel-enhanced`: User cancellation form
- `/admin/people/pending-deletion`: Admin interface for pending deletions
- `/admin/config/people/enhanced-user-cancellation`: Module configuration

## JavaScript Components

### Modal Dialog

The module uses jQuery UI dialogs for the confirmation modal with:
- Proper CSRF token handling
- AJAX form submission
- Success/error message display
- Automatic redirection

### Libraries

- `enhanced_user_cancellation/modal_cancellation`: Main modal functionality
- `enhanced_user_cancellation/modal_cancellation_edit`: Edit page modal functionality

## Troubleshooting

### Common Issues

**Modal not appearing:**
- Clear browser cache
- Check JavaScript console for errors
- Verify jQuery UI is loaded

**Users not appearing in pending deletion list:**
- Check if fields were created properly
- Review Drupal logs at `/admin/reports/dblog`
- Ensure module permissions are correctly set

**Admin tab not appearing:**
- Clear cache: `drush cache:rebuild`
- Check user permissions for "administer enhanced user cancellation"
- Verify menu links are properly configured

**Email notifications not sent:**
- Verify Drupal mail configuration
- Check mail logs
- Ensure email addresses are valid

### Debug Logging

The module logs extensively to help with troubleshooting. Check `/admin/reports/dblog` and filter by "enhanced_user_cancellation" type.

### Field Issues

If the required fields don't exist, reinstall the module:
```bash
drush pmu enhanced_user_cancellation
drush en enhanced_user_cancellation
```

## Development

### File Structure

```
enhanced_user_cancellation/
├── css/
│   └── enhanced-user-cancellation.css
├── js/
│   ├── modal-cancellation.js       # Main modal functionality
│   └── modal-cancellation-edit.js  # Edit page modal
├── src/
│   ├── Controller/
│   │   └── PendingDeletionController.php
│   ├── Form/
│   │   ├── EnhancedUserCancellationConfigForm.php
│   │   └── UserCancelConfirmForm.php
│   ├── Plugin/
│   │   └── QueueWorker/
│   │       └── EnhancedUserDeletionProcessor.php
│   └── Service/
│       └── UserCancellationService.php
├── enhanced_user_cancellation.info.yml
├── enhanced_user_cancellation.install
├── enhanced_user_cancellation.libraries.yml
├── enhanced_user_cancellation.links.action.yml
├── enhanced_user_cancellation.links.menu.yml
├── enhanced_user_cancellation.links.task.yml
├── enhanced_user_cancellation.module
├── enhanced_user_cancellation.permissions.yml
├── enhanced_user_cancellation.routing.yml
├── enhanced_user_cancellation.services.yml
└── README.md
```

### Extending the Module

The module is designed to be extensible:
- Override email templates by implementing `hook_mail_alter()`
- Customize the grace period in the service class
- Add additional fields for tracking cancellation reasons
- Integrate with other user management modules

## Support

For issues and feature requests, check the Drupal logs and ensure all requirements are met. The module includes extensive debugging to help identify issues.

## Requirements

- Drupal 8.8+ or Drupal 9+
- jQuery UI (usually included with Drupal core)
- PHP 7.4+

## License

GPL-2.0+

---

**Version**: 1.0.0  
**Author**: Enhanced User Cancellation Team  
**Drupal**: 8.8+, 9.x, 10.x