(function ($, Drupal, once, drupalSettings) {
  'use strict';

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

  $('.select-status').change(function () {
    var $url = location.origin + location.pathname;
    if ($(this).val() > 0) {
      $url += '?status=' + $(this).val();
    }
    location.href = $url;
  });

  Drupal.behaviors.rateSheetReviewRanges = {
    attach: function (context) {
      once('rate-sheet-review-ranges', '[data-rate-sheet-review-ranges]', context).forEach(function (table) {
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
          var toggleButton = event.target.closest('[data-rate-sheet-review-toggle-ranges]');

          if (!toggleButton || !table.contains(toggleButton)) {
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
      });
    }
  };

})(jQuery, Drupal, once, drupalSettings);
