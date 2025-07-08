(function () {
  'use strict';
  setInterval(() => {
    document.querySelectorAll('[data-testid="dashcard"]').forEach(container => {
      const titleElement = container.querySelector('div[data-testid="scalar-title"]>h3>div');
      const valueElement = container.querySelector('div[data-testid="scalar-container"]>span>h1');
      if (!titleElement || !valueElement) {
        return;
      }
      const text = titleElement.innerText.trim();
      const value = valueElement.innerText.trim();
      if (text.includes('-o-')) {
        container.parentNode.classList.add("container-small");
        if (value.includes('-') && !container.classList.contains("decrease")) {
          container.classList.add("decrease");
          valueElement.textContent =valueElement.textContent.replace('-', '');
        }
        else if (value.includes('+') && !container.classList.contains("increase")) {
          container.classList.add("increase");
          valueElement.textContent = valueElement.textContent.replace('+', '');
        }
        else if (!container.classList.contains("decrease") && !container.classList.contains("increase") && !container.classList.contains("equals")) {
          container.classList.add("equals");
        }
      }
      else {
        if (!container.classList.contains("dashcard")) {
          container.classList.add("dashcard");

          if (!valueElement.textContent.includes('|')) {
            return;
          }

          const parts = valueElement.textContent.split('|');

          if (parts.length < 2) {
            return;
          }

          const enhancedHTML = parts[0] + '<span>' + parts[1] + '</span>';
          valueElement.innerHTML = enhancedHTML;

          if (parts.length == 3) {
            titleElement.innerHTML = titleElement.innerHTML.replace('###', parts[2]);
          }
        }
      }
    }
    );
  }, 50);
}
)();

(function (document, window) {
  'use strict';
  // Listen for clicks and send to parent
  document.addEventListener('click', function (event) {

    const url = window.location.href;
    const hashPart = url.split('#')[1];
    let id = null;

    if (hashPart) {
      const params = new URLSearchParams(hashPart);
      id = params.get('id');
    }

    window.parent.postMessage({
      type: 'iframe-click',
      iframeId: id,
    }, '*');
  });

  // Listen for propagated clicks from parent
  window.addEventListener('message', function (event) {
    if (event.data.type === 'propagated-click' && event.data.targetIframe !== window.name) {
      if (event.data.sourceIframe) {
        console.log('Received click from iframe:', event.data.sourceIframe);
        // Call the event than hides the dropdown options in the filters.
      }
    }
  });
})(document, window);
