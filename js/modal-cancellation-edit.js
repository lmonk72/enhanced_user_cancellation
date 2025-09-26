(function ($, Drupal, once) {
  'use strict';

  /**
   * Enhanced User Cancellation Modal for Edit Page.
   */
  Drupal.behaviors.enhancedUserCancellationEditModal = {
    attach: function (context, settings) {
      // Look for the "Cancel Account (Enhanced)" action link on user edit pages
      once('enhanced-cancellation-edit', 'a[href*="/cancel-enhanced"]', context).forEach(function (link) {
        console.log('Enhanced User Cancellation: Found cancel link on edit page');
        
        $(link).on('click', function (e) {
          e.preventDefault();
          
          console.log('Enhanced User Cancellation: Cancel link clicked');
          
          // Create modal content
          var modalContent = '<div class="enhanced-cancellation-modal-content">' +
            '<p><strong>' + Drupal.t('Are you sure you want to cancel your account?') + '</strong></p>' +
            '<p>' + Drupal.t('Your account will be marked for deletion and you will receive an email confirmation. You have 72 hours to change your mind before your account and all associated content is permanently deleted.') + '</p>' +
            '<div class="messages messages--warning" style="margin: 15px 0;">' +
              '<div class="messages__content">' +
                Drupal.t('This action cannot be undone after the 72-hour grace period.') +
              '</div>' +
            '</div>' +
          '</div>';
          
          var cancelUrl = $(this).attr('href');
          
          // Create and show Drupal dialog
          var $dialog = $(modalContent).dialog({
            title: Drupal.t('Cancel Account'),
            width: 600,
            height: 400,
            modal: true,
            resizable: false,
            draggable: false,
            dialogClass: 'enhanced-user-cancellation-dialog',
            buttons: [
              {
                text: Drupal.t('Cancel Account'),
                'class': 'button button--danger button--primary',
                click: function() {
                  console.log('Enhanced User Cancellation: User confirmed, processing deletion');
                  
                  // Show loading state
                  $(this).find('.button--danger').text(Drupal.t('Processing...')).prop('disabled', true);
                  
                  // Extract user ID from the cancel URL
                  var userIdMatch = cancelUrl.match(/\/user\/(\d+)\/cancel-enhanced/);
                  var userId = userIdMatch ? userIdMatch[1] : null;
                  
                  if (!userId) {
                    console.error('Could not extract user ID from URL:', cancelUrl);
                    $dialog.dialog('close');
                    return;
                  }
                  
                  // First, get the form and its form_build_id and form_token
                  $.get(cancelUrl)
                    .done(function(formHtml) {
                      var $formHtml = $(formHtml);
                      var formBuildId = $formHtml.find('input[name="form_build_id"]').val();
                      var formToken = $formHtml.find('input[name="form_token"]').val();
                      var formId = $formHtml.find('input[name="form_id"]').val();
                      
                      console.log('Form data extracted:', { formBuildId, formToken, formId, userId });
                      
                      // Now submit the form with proper data
                      $.ajax({
                        url: cancelUrl,
                        type: 'POST',
                        data: {
                          'user_id': userId,
                          'form_build_id': formBuildId,
                          'form_token': formToken,
                          'form_id': formId,
                          'op': 'Cancel Account'
                        },
                        headers: {
                          'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        success: function (response) {
                          console.log('Form submission successful');
                          $dialog.dialog('close');
                          
                          // Show success message
                          var message = '<div class="messages messages--status">' +
                            '<div class="messages__content">' +
                            Drupal.t('Your account has been scheduled for deletion. You will receive a confirmation email.') +
                            '</div></div>';
                          
                          // Remove existing messages and add new one
                          $('.messages').remove();
                          $('.region-content, main').first().prepend(message);
                          
                          // Scroll to top to show message
                          $('html, body').animate({ scrollTop: 0 }, 300);
                          
                          // Redirect to home page after a delay
                          setTimeout(function() {
                            window.location.href = Drupal.url('<front>');
                          }, 3000);
                        },
                        error: function (xhr, status, error) {
                          console.error('Form submission failed:', error);
                          $dialog.dialog('close');
                          
                          var errorMessage = '<div class="messages messages--error">' +
                            '<div class="messages__content">' +
                            Drupal.t('An error occurred while processing your request. Please try again.') +
                            '</div></div>';
                          
                          $('.messages').remove();
                          $('.region-content, main').first().prepend(errorMessage);
                          $('html, body').animate({ scrollTop: 0 }, 300);
                        }
                      });
                    })
                    .fail(function() {
                      console.error('Failed to get form data');
                      $dialog.dialog('close');
                    });
                }
              },
              {
                text: Drupal.t('Keep Account'),
                'class': 'button',
                click: function() {
                  console.log('Enhanced User Cancellation: User cancelled');
                  $(this).dialog('close');
                }
              }
            ],
            close: function() {
              $(this).remove();
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, once);