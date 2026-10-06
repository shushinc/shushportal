/**
 * @file
 * Enhances the Drupal pager on the rate sheet list with AJAX navigation.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.rateSheetListPagination = {
    attach(context) {
      once('rate-sheet-list-pagination', '[data-rate-sheet-list]', context).forEach((wrapper) => {

        wrapper.addEventListener('click', async (event) => {
          const link = event.target.closest('.pager a');

          if (!link || !wrapper.contains(link)) {
            return;
          }

          event.preventDefault();

          const url = new URL(link.href, window.location.href);
          wrapper.setAttribute('aria-busy', 'true');

          try {
            const response = await fetch(url.toString(), {
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
              },
            });

            if (!response.ok) {
              throw new Error(`Unable to load rate sheets: ${response.status}`);
            }

            const html = await response.text();
            const documentFragment = new DOMParser().parseFromString(html, 'text/html');
            const replacement = documentFragment.querySelector('[data-rate-sheet-list]');

            if (!replacement) {
              throw new Error('Rate sheet list was not found in the response.');
            }

            wrapper.replaceWith(replacement);
            window.history.replaceState({}, '', url.toString());
            Drupal.attachBehaviors(replacement);
          }
          catch (error) {
            window.location.href = url.toString();
          }
          finally {
            wrapper.removeAttribute('aria-busy');
          }
        });
      });
    },
  };

})(Drupal, once);