(function ($, Drupal, once) {
  'use strict';

  /**
   * Enhanced User Cancellation Modal behavior.
   */
  Drupal.behaviors.enhancedUserCancellationModal = {
    attach: function (context, settings) {
      // Target our specific form using the modern once API
      once('enhanced-cancellation', '#enhanced-user-cancel-confirm-form', context).forEach(function (form) {
        var $form = $(form);
        var $submitButton = $form.find('input[type="submit"]');
        
        console.log('Enhanced User Cancellation: Form found and JavaScript attached');
        
        // Override the form submission to show confirmation
        $submitButton.on('click', function (e) {
          e.preventDefault();
          
          console.log('Enhanced User Cancellation: Submit button clicked');
          
          // Simple confirmation dialog
          if (confirm(Drupal.t('Are you sure you want to cancel your account? You will have 72 hours to change your mind before permanent deletion.'))) {
            console.log('Enhanced User Cancellation: User confirmed, submitting form');
            // Remove the click handler to avoid recursion and submit
            $submitButton.off('click');
            $submitButton.click();
          } else {
            console.log('Enhanced User Cancellation: User cancelled');
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);