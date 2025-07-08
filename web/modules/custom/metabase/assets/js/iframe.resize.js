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
        if (boddy.querySelectorAll('div#root > div > div')[0] === undefined) {
          return;
        }
        let height = boddy.querySelectorAll('div#root > div > div')[0].clientHeight;
        if (height < 450) {
          height = 290;
        }
        iframe.style.height = (height) + 'px';
      }
    }
  };
})(Drupal, once);


// In parent document
window.addEventListener('message', function (event) {
  const iframes = document.getElementsByTagName('iframe');

  if (event.data.type === 'iframe-click') {
    const sourceIframe = event.data.iframeId;
    const sourceElement = this.document.getElementById(sourceIframe);

    for (const iframe of iframes) {
      if (iframe !== sourceElement) {
        iframe.contentWindow.postMessage({
          type: 'propagated-click',
          sourceIframe: sourceIframe,
        }, '*');
      }
    }
  }
});
