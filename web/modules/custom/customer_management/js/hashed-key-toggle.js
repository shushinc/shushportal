(function (Drupal, once) {
  'use strict';

  var style = document.createElement('style');
  style.textContent = '.hashed-key-toggle, .hashed-key-reset { margin-inline-start: 8px; }';
  document.head.appendChild(style);

  function attachBehavior(wrapper) {
    var revealUrl = wrapper.getAttribute('data-reveal-url');
    var display = wrapper.parentElement ? wrapper.parentElement.querySelector('[data-hashed-key-display]') : null;
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
      once('hashed-key-toggle', '[data-reveal-url]', context).forEach(attachBehavior);
    }
  };
})(Drupal, once);
