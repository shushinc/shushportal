(function (Drupal, once) {
  'use strict';

  /**
   * Behavior to prevent unchecking solved reject comments.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to prevent unchecking solved comments.
   */
  Drupal.behaviors.rateSheetRejectComments = {
    attach: function (context) {
      once('rate-sheet-reject-comments', '.reject-comment-solved', context).forEach(function (checkbox) {
        var input = checkbox.querySelector('input[type="checkbox"]');
        
        if (!input) {
          return;
        }

        // Prevent any changes to the checkbox
        input.addEventListener('click', function (event) {
          event.preventDefault();
          return false;
        });

        input.addEventListener('change', function (event) {
          event.preventDefault();
          // Force it back to checked
          input.checked = true;
          return false;
        });

        // Make it readonly
        input.readOnly = true;
      });
    }
  };

})(Drupal, once);
