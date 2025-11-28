(function ($, Drupal, once) {
  Drupal.behaviors.updateConsentError = {
    attach(context) {
      const consentError = once('consentErrorCheck', '.consent-error', context);

      if (consentError.length) {
        const msgEl = document.querySelector('.messages');

        if (msgEl) {

          msgEl.classList.remove('messages--status');
          msgEl.classList.add('messages--error');

          msgEl.setAttribute('role', 'error');
          msgEl.setAttribute('aria-label', 'Error message');

          setTimeout(function () {
            $(msgEl).slideUp();
          }, 4000);
        }
      }
    }
  };
})(jQuery, Drupal, once);
