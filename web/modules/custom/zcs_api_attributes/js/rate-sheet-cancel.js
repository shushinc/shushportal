(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.rateSheetCancel = {
    attach: function (context) {
      once('rate-sheet-cancel', '[data-rate-sheet-cancel-button]', context).forEach(function (button) {
        button.addEventListener('click', function (event) {
          var confirmed = confirm(
            'Are you sure you want to cancel this rate sheet?\n\n' +
            'This action cannot be undone. The rate sheet will be permanently cancelled.'
          );

          if (!confirmed) {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        });
      });
    }
  };

})(Drupal, once);
