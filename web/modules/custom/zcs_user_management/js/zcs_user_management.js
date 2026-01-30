/**
 * @file
 *
 */

 (function ($) {
    Drupal.behaviors.zcs_user_management_multiselect = {
      attach: function (context, settings) {     
          $(document).ajaxComplete(function(event, xhr, settings){
            if (settings.url.includes('/user-invite') || settings.url.includes('/user-management')) {
              $('.multi-select').multiselect({
                  buttonWidth: '100%',
                  maxHeight: 200,            
             });
            }
          });
      }};
  })(jQuery);


