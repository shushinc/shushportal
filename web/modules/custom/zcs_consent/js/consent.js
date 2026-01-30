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

  Drupal.behaviors.msisdnLineCounter = {
    attach(context) {
      once('msisdn-line-counter', 'textarea[name="msisdn"]', context).forEach((textarea) => {
        const maxLines = parseInt(textarea.dataset.maxLines, 10) || 1000;
        const counter = textarea.closest('.consent-data')?.querySelector('.lines-counter');

        if (!counter) {
          return;
        }

        const updateCounter = () => {
          const value = textarea.value.trim();
          const lines = value === '' ? 0 : value.split(/\r\n|\r|\n/).length;
          const remaining = maxLines - lines;

          if (remaining >= 0) {
            counter.textContent = Drupal.t('@used of @max lines used (@remaining remaining)', {
              '@used': lines,
              '@max': maxLines,
              '@remaining': remaining,
            });
            counter.classList.remove('is-invalid');
          }
          else {
            counter.textContent = Drupal.t('@used lines entered. Maximum is @max.', {
              '@used': lines,
              '@max': maxLines,
            });
            counter.classList.add('is-invalid');
          }
        };

        textarea.addEventListener('input', updateCounter);
        updateCounter();
      });
    }
  };

})(jQuery, Drupal, once);
