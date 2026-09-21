(function (Drupal, once) {
  'use strict';

  function ensureInlineStyle() {
    if (document.getElementById('customer-management-hashed-key-style')) {
      return;
    }

    var style = document.createElement('style');
    style.id = 'customer-management-hashed-key-style';
    style.textContent = '.hashed-key-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.5rem}.hashed-key-toggle,.hashed-key-reset{margin:0}';
    document.head.appendChild(style);
  }

  function attachBehavior(wrapper) {
    var revealUrl = wrapper.getAttribute('data-reveal-url');
    var fieldWrapper = wrapper.closest('.customer-management-hashed-key-field') || wrapper.parentElement;
    var display = fieldWrapper ? fieldWrapper.querySelector('[data-hashed-key-display]') : null;
    var toggleButton = wrapper.querySelector('[data-hashed-key-toggle]');

    if (!revealUrl || !display || !toggleButton) {
      return;
    }

    toggleButton.addEventListener('click', function () {
      var isMasked = toggleButton.getAttribute('data-state') !== 'shown';

      if (!isMasked) {
        display.value = '••••••••••••••••••••••••';
        toggleButton.setAttribute('data-state', 'hidden');
        toggleButton.textContent = 'Show';
        return;
      }

      toggleButton.disabled = true;

      window.fetch(revealUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Failed to reveal hashed key.');
          }
          return response.json();
        })
        .then(function (data) {
          display.value = data.hashed_key || '';
          toggleButton.setAttribute('data-state', 'shown');
          toggleButton.textContent = 'Hide';
        })
        .catch(function () {
          window.alert('Unable to reveal the hashed key.');
        })
        .finally(function () {
          toggleButton.disabled = false;
        });
    });
  }

  Drupal.behaviors.hashedKeyToggle = {
    attach: function (context) {
      ensureInlineStyle();
      once('hashed-key-toggle', '[data-reveal-url]', context).forEach(attachBehavior);
    }
  };
})(Drupal, once);
