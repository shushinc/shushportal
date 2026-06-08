(function (Drupal, once) {
  'use strict';

  function closestElement(element, selector, boundary) {
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

  function getRangeRows(tbody, attributeId) {
    return Array.prototype.filter.call(
      tbody.querySelectorAll('[data-rate-sheet-range-row]'),
      function (row) {
        return row.getAttribute('data-attribute-id') === attributeId;
      }
    );
  }

  function updateReviewRangeControls(tbody, attributeId) {
    var rows = getRangeRows(tbody, attributeId);
    var initialRow = null;
    var attributeRow = tbody.querySelector('[data-rate-sheet-attribute-row][data-attribute-id="' + attributeId + '"]');

    rows.forEach(function (row) {
      if (!initialRow && row.hasAttribute('data-rate-sheet-initial-range-row')) {
        initialRow = row;
      }
    });

    if (!initialRow) {
      initialRow = rows.length ? rows[0] : null;
    }

    if (!initialRow) {
      return;
    }

    var totalRanges = rows.length;
    var collapsed = attributeRow && attributeRow.getAttribute('data-rate-sheet-collapsed') === 'true';
    var toggleButton = initialRow.querySelector('[data-rate-sheet-review-toggle-ranges]');
    var toggleIcon = initialRow.querySelector('[data-rate-sheet-review-toggle-icon]');
    var summary = initialRow.querySelector('[data-rate-sheet-review-range-summary]');

    if (totalRanges <= 1) {
      collapsed = false;

      if (attributeRow) {
        attributeRow.setAttribute('data-rate-sheet-collapsed', 'false');
      }
    }

    rows.forEach(function (row) {
      row.hidden = row !== initialRow && collapsed;
    });

    if (toggleButton) {
      toggleButton.hidden = totalRanges <= 1;
      toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggleButton.setAttribute('aria-label', collapsed ? 'Expand ranges' : 'Collapse ranges');
      toggleButton.setAttribute('title', collapsed ? 'Expand ranges' : 'Collapse ranges');
      toggleButton.classList.toggle('is-collapsed', collapsed);
    }

    if (toggleIcon) {
      toggleIcon.textContent = collapsed ? '+' : '-';
    }

    if (summary) {
      summary.hidden = !(collapsed && totalRanges > 1);
      summary.textContent = totalRanges + ' ranges';
    }
  }

  function initLegacyReviewTable(table) {
    if (!table.tBodies.length) {
      return;
    }

    var tbody = table.tBodies[0];

    Array.prototype.forEach.call(tbody.querySelectorAll('[data-rate-sheet-attribute-row]'), function (attributeRow) {
      var attributeId = attributeRow.getAttribute('data-attribute-id');

      if (attributeId) {
        updateReviewRangeControls(tbody, attributeId);
      }
    });

    table.addEventListener('click', function (event) {
      var toggleButton = closestElement(event.target, '[data-rate-sheet-review-toggle-ranges]', table);

      if (!toggleButton) {
        return;
      }

      event.preventDefault();

      var attributeId = toggleButton.getAttribute('data-attribute-id');
      var attributeRow = tbody.querySelector('[data-rate-sheet-attribute-row][data-attribute-id="' + attributeId + '"]');

      if (!attributeId || !attributeRow) {
        return;
      }

      attributeRow.setAttribute(
        'data-rate-sheet-collapsed',
        attributeRow.getAttribute('data-rate-sheet-collapsed') === 'true' ? 'false' : 'true'
      );

      updateReviewRangeControls(tbody, attributeId);
    });
  }

  function setElementHidden(element, hidden) {
    if (!element) {
      return;
    }

    element.hidden = hidden;
    element.setAttribute('aria-hidden', hidden ? 'true' : 'false');
  }

  function setReviewAccordionCollapsed(item, collapsed) {
    var toggleButton = item.querySelector('[data-rate-sheet-review-accordion-toggle]');
    var toggleText = toggleButton ? toggleButton.querySelector('.rate-sheet-accordion-toggle-text') : null;
    var toggleIcon = item.querySelector('[data-rate-sheet-review-accordion-icon]');
    var panel = item.querySelector('[data-rate-sheet-review-accordion-panel]');

    item.setAttribute('data-rate-sheet-collapsed', collapsed ? 'true' : 'false');
    item.classList.toggle('is-collapsed', collapsed);
    item.classList.toggle('is-expanded', !collapsed);

    setElementHidden(panel, collapsed);

    if (toggleButton) {
      toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggleButton.setAttribute('aria-label', collapsed ? 'Expand ranges' : 'Collapse ranges');
      toggleButton.setAttribute('title', collapsed ? 'Expand ranges' : 'Collapse ranges');
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

  function initReviewAccordionCards(container) {
    var cards = Array.prototype.slice.call(container.querySelectorAll('[data-rate-sheet-review-attribute-card]'));

    cards.forEach(function (card) {
      setReviewAccordionCollapsed(card, card.getAttribute('data-rate-sheet-collapsed') === 'true');
    });

    container.addEventListener('click', function (event) {
      var toggleButton = closestElement(event.target, '[data-rate-sheet-review-accordion-toggle]', container);

      if (!toggleButton) {
        return;
      }

      var card = closestElement(toggleButton, '[data-rate-sheet-review-attribute-card]', container);

      if (!card) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      setReviewAccordionCollapsed(card, card.getAttribute('data-rate-sheet-collapsed') !== 'true');
    });
  }

  Drupal.behaviors.rateSheetApprovalStatusFilter = {
    attach: function (context) {
      once('rate-sheet-approval-status-filter', '.select-status', context).forEach(function (select) {
        select.addEventListener('change', function () {
          var url = location.origin + location.pathname;

          if (select.value > 0) {
            url += '?status=' + select.value;
          }

          location.href = url;
        });
      });
    }
  };

  Drupal.behaviors.rateSheetReviewRanges = {
    attach: function (context) {
      once('rate-sheet-review-ranges', '[data-rate-sheet-review-ranges]', context).forEach(function (element) {
        if (element.tBodies && element.tBodies.length) {
          initLegacyReviewTable(element);
          return;
        }

        initReviewAccordionCards(element);
      });
    }
  };

})(Drupal, once);
