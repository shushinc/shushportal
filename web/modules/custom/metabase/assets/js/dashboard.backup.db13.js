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

// Function to communicate between iframes.
(function (document, window) {
  'use strict';

  const url = window.location.href;
  const hashPart = url.split('#')[1];
  let id = null;

  if (hashPart) {
    const params = new URLSearchParams(hashPart);
    id = params.get('id');
    document.getElementsByTagName('body')[0].setAttribute('id', id);
  }

  // Listen for clicks and send to parent
  document.addEventListener('click', function (event) {

    window.parent.postMessage({
      type: 'iframe-click',
      iframeId: id,
    }, '*');
  });

  // Listen for propagated clicks from parent
  window.addEventListener('message', function (event) {
    if (event.data.type === 'propagated-click' && event.data.targetIframe !== window.name) {
      if (event.data.sourceIframe) {
        // console.log('Received click from iframe:', event.data.sourceIframe);
        document.getElementById('root').dispatchEvent(new MouseEvent('mousedown', {
          bubbles: true,
          cancelable: true
        }));
      }
    }
  });
})(document, window);

// AddFilter hack area.
function waitForReactToLoad(callback) {
  const observer = new MutationObserver((mutations, obs) => {
    const reactRoot = document.querySelector('#root') || document.querySelector('[data-reactroot]');
    if (reactRoot && reactRoot.children.length > 0) {
      const hasReactContent = reactRoot.querySelector('[data-react-helmet]') || reactRoot.innerHTML.trim().length > 0;
      if (hasReactContent) {
        obs.disconnect();
        callback();
      }
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
}

// Usage
waitForReactToLoad(() => {
  setTimeout(() => {
    document.querySelectorAll('div[data-testid="field-set-content"]').forEach(filter => {
      filter.addEventListener('click', () => {
        setTimeout(() => {
          document.querySelectorAll('[data-testid="field-values-widget"] > ul > li > div').forEach(option => {
            // option.removeEventListener('click', addFilterHandler);
            option.addEventListener('click', addFilterHandler);
          });
        }, 500);
      });
    });
  }, 500);
});

function addFilterHandler() {
  setTimeout(() => {
    document.querySelectorAll('div.mb-mantine-Popover-dropdown > form > div > button').forEach(button => {
      button.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    });
  }, 200);
}
