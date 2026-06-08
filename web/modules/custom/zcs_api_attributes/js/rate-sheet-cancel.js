(function (Drupal, once) {
  'use strict';

  /**
   * Behavior for rate sheet cancellation confirmation.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the cancel confirmation behavior.
   */
  Drupal.behaviors.rateSheetCancel = {
    attach: function (context) {
      once('rate-sheet-cancel', '[data-rate-sheet-cancel-button]', context).forEach(function (button) {
        button.addEventListener('click', function (event) {
          var confirmed = window.confirm(
            Drupal.t('Are you sure you want to cancel this rate sheet?') + '\n\n' +
            Drupal.t('This action cannot be undone. The rate sheet will be permanently cancelled.')
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
