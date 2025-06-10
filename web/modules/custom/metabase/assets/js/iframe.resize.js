/**
 * @file
 * JavaScript behavior to resize iframes to match their content height.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Behavior to resize iframes to match content height.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.metabaseIframeResize = {
    attach: function (context, settings) {
      const iframes = once('metabase-iframe-resize', 'iframe', context);

      iframes.forEach(function (iframe) {

        iframe.addEventListener('load', function () {
          resizeIframe(iframe);
        });

        setInterval(function () {
          resizeIframe(iframe);
        }, 2000);
      });

      // Function to resize iframe
      function resizeIframe(iframe) {
        let boddy = iframe.contentDocument.body;
        let height = boddy.querySelectorAll('div#root > div > div')[0].clientHeight;
        if (height < 450) {
          height = 320;
        }
        iframe.style.height = (height) + 'px';
      }
    }
  };
})(Drupal, once);
