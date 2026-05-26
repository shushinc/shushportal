(function (Drupal, once) {
  'use strict';

  function initAttributeFilter(table) {
    var filterInput = document.querySelector('[data-rate-sheet-attribute-filter]');

    if (!filterInput) {
      return;
    }

    filterInput.addEventListener('input', function () {
      var query = filterInput.value.trim().toLowerCase();
      var attributeRows = table.querySelectorAll('[data-rate-sheet-attribute-row]');
      var rangeRows = table.querySelectorAll('[data-rate-sheet-range-row]');

      attributeRows.forEach(function (row) {
        var attributeId = row.getAttribute('data-attribute-id');
        var attributeName = row.getAttribute('data-attribute-name') || '';
        var matched = attributeName.indexOf(query) !== -1;

        row.style.display = matched ? '' : 'none';

        rangeRows.forEach(function (rangeRow) {
          if (rangeRow.getAttribute('data-attribute-id') === attributeId) {
            rangeRow.style.display = matched ? '' : 'none';
          }
        });
      });
    });
  }

  function getScrollContainer(element) {
    var dialogContent = element.closest('.ui-dialog-content');

    if (dialogContent) {
      return dialogContent;
    }

    var parent = element.parentElement;

    while (parent && parent !== document.body) {
      var style = window.getComputedStyle(parent);
      var overflowY = style.overflowY;

      if (overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') {
        return parent;
      }

      parent = parent.parentElement;
    }

    return window;
  }

  function initFloatingSubmit(floatingButton) {
    var submitWrapper = document.querySelector('[data-rate-sheet-submit-wrapper]');
    var submitButton = submitWrapper ? submitWrapper.querySelector('input[type="submit"], button[type="submit"]') : null;
    var scrollContainer = null;

    if (!submitWrapper || !submitButton) {
      return;
    }

    function getContainerRect() {
      if (scrollContainer === window) {
        return {
          top: 0,
          right: window.innerWidth || document.documentElement.clientWidth,
          bottom: window.innerHeight || document.documentElement.clientHeight,
          left: 0
        };
      }

      return scrollContainer.getBoundingClientRect();
    }

    function submitIsVisible() {
      var submitRect = submitWrapper.getBoundingClientRect();
      var containerRect = getContainerRect();

      return submitRect.top < containerRect.bottom && submitRect.bottom > containerRect.top;
    }

    function positionFloatingButton() {
      var containerRect = getContainerRect();
      var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
      var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
      var spacing = 14;

      floatingButton.style.bottom = (Math.max(spacing, viewportHeight - containerRect.bottom + spacing) -50) + 'px';
      floatingButton.style.right = (Math.max(spacing, viewportWidth - containerRect.right + spacing) - 50) + 'px';
    }

    function hideFloatingButton() {
      floatingButton.classList.remove('is-visible');

      window.setTimeout(function () {
        if (!floatingButton.classList.contains('is-visible')) {
          floatingButton.hidden = true;
        }
      }, 250);
    }

    function showFloatingButton() {
      positionFloatingButton();
      floatingButton.hidden = false;

      window.requestAnimationFrame(function () {
        floatingButton.classList.add('is-visible');
      });
    }

    function updateFloatingButton() {
      if (submitIsVisible()) {
        hideFloatingButton();
        return;
      }

      showFloatingButton();
    }

    function removeScrollListener(container) {
      if (!container) {
        return;
      }

      if (container === window) {
        window.removeEventListener('scroll', updateFloatingButton);
      }
      else {
        container.removeEventListener('scroll', updateFloatingButton);
      }
    }

    function addScrollListener(container) {
      if (container === window) {
        window.addEventListener('scroll', updateFloatingButton, { passive: true });
      }
      else {
        container.addEventListener('scroll', updateFloatingButton, { passive: true });
      }
    }

    function refreshScrollContainer() {
      var newScrollContainer = getScrollContainer(submitWrapper);

      if (newScrollContainer === scrollContainer) {
        return;
      }

      removeScrollListener(scrollContainer);
      scrollContainer = newScrollContainer;
      addScrollListener(scrollContainer);
    }

    floatingButton.addEventListener('click', function () {
      if (submitButton.form && typeof submitButton.form.requestSubmit === 'function') {
        submitButton.form.requestSubmit(submitButton);
        return;
      }

      submitButton.click();
    });

    window.addEventListener('resize', function () {
      refreshScrollContainer();
      updateFloatingButton();
    });

    refreshScrollContainer();
    updateFloatingButton();

    window.setTimeout(function () {
      refreshScrollContainer();
      updateFloatingButton();
    }, 100);
  }

  Drupal.behaviors.rateSheetUx = {
    attach: function (context) {
      once('rate-sheet-attribute-filter', '[data-rate-sheet-ranges]', context).forEach(initAttributeFilter);
      once('rate-sheet-floating-submit', '[data-rate-sheet-floating-submit]', context).forEach(initFloatingSubmit);
    }
  };

})(Drupal, once);
