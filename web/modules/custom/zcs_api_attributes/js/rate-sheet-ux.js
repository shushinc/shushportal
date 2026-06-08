(function (Drupal, once) {
  'use strict';

  function closestElement(element, selector, boundary) {
    if (!element) {
      return null;
    }

    if (element.nodeType !== 1) {
      element = element.parentElement;
    }

    if (!element) {
      return null;
    }

    if (typeof element.closest === 'function') {
      var closest = element.closest(selector);

      if (!boundary || !closest || boundary.contains(closest)) {
        return closest;
      }

      return null;
    }

    while (element && element !== boundary && element.nodeType === 1) {
      if (typeof element.matches === 'function' && element.matches(selector)) {
        return element;
      }

      element = element.parentElement;
    }

    return null;
  }

  function getAttributeItems(container) {
    return Array.prototype.slice.call(container.querySelectorAll('[data-rate-sheet-attribute-item]'));
  }

  function getProgressiveWrapper(container) {
    return container.closest('.rate-sheet-attributes') || container.parentElement || document;
  }

  function getPositiveInteger(value, fallback) {
    var parsed = parseInt(value, 10);

    if (Number.isNaN(parsed) || parsed <= 0) {
      return fallback;
    }

    return parsed;
  }

  function initAttributeFilter(container) {
    var wrapper = getProgressiveWrapper(container);
    var filterInput = wrapper.querySelector('[data-rate-sheet-attribute-filter]') || document.querySelector('[data-rate-sheet-attribute-filter]');
    var loadMoreWrapper = wrapper.querySelector('[data-rate-sheet-load-more-wrapper]');
    var loadMoreButton = wrapper.querySelector('[data-rate-sheet-load-more]');
    var summary = wrapper.querySelector('[data-rate-sheet-progressive-summary]');
    var noResultsMessage = wrapper.querySelector('[data-rate-sheet-no-results]');
    var initialVisible = getPositiveInteger(container.getAttribute('data-rate-sheet-initial-visible'), 10);
    var loadStep = getPositiveInteger(container.getAttribute('data-rate-sheet-load-step'), 10);
    var visibleLimit = initialVisible;
    var query = '';

    function applyLegacyTableFilter() {
      var attributeRows = container.querySelectorAll('[data-rate-sheet-attribute-row]');
      var rangeRows = container.querySelectorAll('[data-rate-sheet-range-row]');

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
    }

    function getMatchedAttributeItems(attributeItems) {
      if (!query) {
        return attributeItems;
      }

      var matchedSet = new Set();

      attributeItems.forEach(function (item) {
        var attributeName = item.getAttribute('data-attribute-name') || '';

        if (attributeName.indexOf(query) !== -1) {
          matchedSet.add(item);
        }
      });

      return attributeItems.filter(function (item) {
        return matchedSet.has(item);
      });
    }

    function updateProgressiveControls(totalMatched, visibleCount, totalAttributes) {
      var remaining = totalMatched - visibleCount;

      if (noResultsMessage) {
        noResultsMessage.hidden = !(query && totalMatched === 0);
      }

      if (summary) {
        if (query) {
          summary.textContent = totalMatched === 1 ? 'Showing 1 matching attribute' : 'Showing ' + totalMatched + ' matching attributes';
        }
        else {
          summary.textContent = 'Showing ' + visibleCount + ' of ' + totalAttributes + ' attributes';
        }
      }

      if (!loadMoreWrapper || !loadMoreButton) {
        return;
      }

      if (query || remaining <= 0) {
        loadMoreWrapper.hidden = true;
        loadMoreButton.hidden = true;
        return;
      }

      loadMoreWrapper.hidden = false;
      loadMoreButton.hidden = false;
      loadMoreButton.textContent = remaining > loadStep ? 'Load 10 more attributes' : 'Load remaining ' + remaining + ' attribute' + (remaining === 1 ? '' : 's');
      loadMoreButton.setAttribute('aria-label', 'Load more API attributes. ' + remaining + ' remaining.');
    }

    function applyAccordionProgressiveReveal() {
      var attributeItems = getAttributeItems(container);

      if (!attributeItems.length) {
        applyLegacyTableFilter();
        return;
      }

      var matchedItems = getMatchedAttributeItems(attributeItems);
      var matchedSet = new Set(matchedItems);
      var visibleCount = 0;

      attributeItems.forEach(function (item) {
        var matched = matchedSet.has(item);
        var shouldShow = false;

        if (matched) {
          if (query) {
            shouldShow = true;
          }
          else {
            shouldShow = visibleCount < visibleLimit;
          }
        }

        item.style.display = shouldShow ? '' : 'none';
        item.toggleAttribute('data-rate-sheet-progressive-hidden', !shouldShow);

        if (shouldShow) {
          visibleCount++;
        }
      });

      updateProgressiveControls(matchedItems.length, visibleCount, attributeItems.length);
    }

    if (filterInput) {
      filterInput.addEventListener('input', function () {
        query = filterInput.value.trim().toLowerCase();
        applyAccordionProgressiveReveal();
      });
    }

    if (loadMoreButton) {
      loadMoreButton.addEventListener('click', function () {
        visibleLimit += loadStep;
        applyAccordionProgressiveReveal();

        window.requestAnimationFrame(function () {
          loadMoreButton.focus();
        });
      });
    }

    applyAccordionProgressiveReveal();
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

    function hideFloatingButton() {
      floatingButton.classList.remove('is-visible');

      window.setTimeout(function () {
        if (!floatingButton.classList.contains('is-visible')) {
          floatingButton.hidden = true;
        }
      }, 250);
    }

    function showFloatingButton() {
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

  function setAccordionCollapsed(attributeItem, collapsed) {
    if (!attributeItem) {
      return;
    }

    var toggleButton = attributeItem.querySelector('[data-rate-sheet-accordion-toggle]');
    var toggleIcon = attributeItem.querySelector('[data-rate-sheet-accordion-icon]');
    var toggleText = toggleButton ? toggleButton.querySelector('.rate-sheet-accordion-toggle-text') : null;
    var panel = attributeItem.querySelector('[data-rate-sheet-accordion-panel]');

    attributeItem.setAttribute('data-rate-sheet-collapsed', collapsed ? 'true' : 'false');
    attributeItem.classList.toggle('is-collapsed', collapsed);
    attributeItem.classList.toggle('is-expanded', !collapsed);

    if (panel) {
      panel.hidden = collapsed;
      panel.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
    }

    if (toggleButton) {
      toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggleButton.classList.toggle('is-collapsed', collapsed);
      toggleButton.classList.toggle('is-expanded', !collapsed);
    }

    if (toggleText) {
      toggleText.textContent = collapsed ? 'Expand' : 'Collapse';
    }

    if (toggleIcon) {
      toggleIcon.textContent = collapsed ? '+' : '−';
    }
  }

  function getInitialAccordionCollapsed(attributeItem, index) {
    var explicitState = attributeItem.getAttribute('data-rate-sheet-collapsed');

    if (explicitState === 'true') {
      return true;
    }

    if (explicitState === 'false') {
      return false;
    }

    var toggleButton = attributeItem.querySelector('[data-rate-sheet-accordion-toggle]');

    if (toggleButton) {
      var ariaExpanded = toggleButton.getAttribute('aria-expanded');

      if (ariaExpanded === 'true') {
        return false;
      }

      if (ariaExpanded === 'false') {
        return true;
      }
    }

    var panel = attributeItem.querySelector('[data-rate-sheet-accordion-panel]');

    if (panel && panel.hidden) {
      return true;
    }

    return index !== 0;
  }

  function initAccordionToggles(container) {
    var attributeItems = getAttributeItems(container);

    attributeItems.forEach(function (item, index) {
      setAccordionCollapsed(item, getInitialAccordionCollapsed(item, index));
    });

    container.addEventListener('click', function (event) {
      var toggleButton = closestElement(event.target, '[data-rate-sheet-accordion-toggle]', container);

      if (!toggleButton || toggleButton.disabled) {
        return;
      }

      var attributeItem = closestElement(toggleButton, '[data-rate-sheet-attribute-item]', container);

      if (!attributeItem || !container.contains(attributeItem)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      setAccordionCollapsed(attributeItem, attributeItem.getAttribute('data-rate-sheet-collapsed') !== 'true');
    });
  }

  Drupal.behaviors.rateSheetUx = {
    attach: function (context) {
      once('rate-sheet-attribute-filter', '[data-rate-sheet-ranges]', context).forEach(initAttributeFilter);
      once('rate-sheet-floating-submit', '[data-rate-sheet-floating-submit]', context).forEach(initFloatingSubmit);
      once('rate-sheet-accordion-toggles', '[data-rate-sheet-ranges]', context).forEach(initAccordionToggles);
    }
  };

})(Drupal, once);
