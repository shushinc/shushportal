(function (Drupal, once) {
  'use strict';

  function getRangeRows(tbody, attributeId) {
    return Array.prototype.filter.call(
      tbody.querySelectorAll('[data-rate-sheet-range-row]'),
      function (row) {
        return row.getAttribute('data-attribute-id') === attributeId;
      }
    );
  }

  function updateRangesPayload(table) {
    var form = table.closest('form');
    var payloadInput = form ? form.querySelector('[data-rate-sheet-ranges-payload]') : null;

    if (!payloadInput || !table.tBodies.length) {
      return;
    }

    var tbody = table.tBodies[0];
    var payload = {};

    Array.prototype.forEach.call(tbody.querySelectorAll('[data-rate-sheet-attribute-row]'), function (attributeRow) {
      var attributeId = attributeRow.getAttribute('data-attribute-id');

      if (!attributeId) {
        return;
      }

      payload[attributeId] = {};

      getRangeRows(tbody, attributeId).forEach(function (rangeRow) {
        var rangeIndex = rangeRow.getAttribute('data-range-index');
        var inputs = rangeRow.querySelectorAll('[data-rate-sheet-range-field]');

        if (!rangeIndex || !inputs.length) {
          return;
        }

        payload[attributeId][rangeIndex] = {
          from_range: '0',
          to_range: '0',
          partial_range: '0',
          success_rate: '0'
        };

        Array.prototype.forEach.call(inputs, function (input) {
          var fieldName = input.getAttribute('data-rate-sheet-range-field');

          if (fieldName) {
            payload[attributeId][rangeIndex][fieldName] = input.value || '0';
          }
        });
      });
    });

    payloadInput.value = JSON.stringify(payload);
  }

  function updateRangeControls(tbody, attributeId) {
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
    var toggleButton = initialRow.querySelector('[data-rate-sheet-toggle-ranges]');
    var toggleIcon = initialRow.querySelector('[data-rate-sheet-toggle-icon]');
    var summary = initialRow.querySelector('[data-rate-sheet-range-summary]');

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

  Drupal.behaviors.rateSheetRanges = {
    attach: function (context) {
      once('rate-sheet-ranges', '[data-rate-sheet-ranges]', context).forEach(function (table) {
        if (!table.tBodies.length) {
          return;
        }

        var tbody = table.tBodies[0];
        var form = table.closest('form');

        Array.prototype.forEach.call(tbody.querySelectorAll('[data-rate-sheet-attribute-row]'), function (attributeRow) {
          var attributeId = attributeRow.getAttribute('data-attribute-id');

          if (attributeId) {
            updateRangeControls(tbody, attributeId);
          }
        });

        updateRangesPayload(table);

        table.addEventListener('input', function () {
          updateRangesPayload(table);
        });

        table.addEventListener('change', function () {
          updateRangesPayload(table);
        });

        if (form) {
          form.addEventListener('submit', function () {
            updateRangesPayload(table);
          }, true);
        }

        table.addEventListener('click', function (event) {
          var addButton = event.target.closest('[data-rate-sheet-add-range]');
          var removeButton = event.target.closest('[data-rate-sheet-remove-range]');
          var toggleButton = event.target.closest('[data-rate-sheet-toggle-ranges]');

          if (addButton && table.contains(addButton)) {
            event.preventDefault();

            var attributeId = addButton.getAttribute('data-attribute-id');
            var parentRow = addButton.closest('[data-rate-sheet-attribute-row]');

            if (!attributeId || !parentRow) {
              return;
            }

            var maxIndex = -1;
            var existingRangeRows = getRangeRows(tbody, attributeId);

            existingRangeRows.forEach(function (row) {
              var currentIndex = parseInt(row.getAttribute('data-range-index'), 10);

              if (!Number.isNaN(currentIndex) && currentIndex > maxIndex) {
                maxIndex = currentIndex;
              }
            });

            var rangeIndex = maxIndex + 1;
            var row = document.createElement('tr');

            row.className = 'rate-sheet-range-row rate-sheet-range-row--dynamic';
            row.setAttribute('data-rate-sheet-range-row', '');
            row.setAttribute('data-attribute-id', attributeId);
            row.setAttribute('data-range-index', rangeIndex);

            row.appendChild(document.createElement('td'));

            var labelCell = document.createElement('td');
            var label = document.createElement('span');

            labelCell.className = 'rate-sheet-range-label';
            label.className = 'rate-sheet-range-label-text';
            label.textContent = 'Range ' + (rangeIndex + 1);
            labelCell.appendChild(label);
            row.appendChild(labelCell);

            ['from_range', 'to_range', 'partial_range', 'success_rate'].forEach(function (fieldName) {
              var cell = document.createElement('td');
              var wrapper = document.createElement('div');
              var input = document.createElement('input');

              wrapper.className = 'rate-sheet-dynamic-number-field';

              input.type = 'number';
              input.name = 'rate_sheet_item_ranges[' + attributeId + '][' + rangeIndex + '][' + fieldName + ']';
              input.id = 'edit-rate-sheet-item-ranges-' + attributeId + '-' + rangeIndex + '-' + fieldName.replace(/_/g, '-');
              input.min = '0';
              input.step = '0.001';
              input.value = '0.000';
              input.className = 'form-number rate-sheet-range-input';
              input.setAttribute('data-rate-sheet-range-field', fieldName);
              input.setAttribute('data-attribute-id', attributeId);
              input.setAttribute('data-range-index', rangeIndex);

              wrapper.appendChild(input);
              cell.appendChild(wrapper);
              row.appendChild(cell);
            });

            row.appendChild(document.createElement('td'));

            var actionsCell = document.createElement('td');
            var removeRangeButton = document.createElement('button');

            actionsCell.className = 'rate-sheet-range-actions';
            removeRangeButton.type = 'button';
            removeRangeButton.className = 'button button--small rate-sheet-remove-range';
            removeRangeButton.setAttribute('data-rate-sheet-remove-range', '');
            removeRangeButton.textContent = 'Remove';

            actionsCell.appendChild(removeRangeButton);
            row.appendChild(actionsCell);

            var referenceRow = existingRangeRows.length ? existingRangeRows[existingRangeRows.length - 1] : parentRow;
            var attributeRow = tbody.querySelector('[data-rate-sheet-attribute-row][data-attribute-id="' + attributeId + '"]');

            referenceRow.parentNode.insertBefore(row, referenceRow.nextSibling);

            if (attributeRow) {
              attributeRow.setAttribute('data-rate-sheet-collapsed', 'false');
            }

            updateRangeControls(tbody, attributeId);
            updateRangesPayload(table);

            window.requestAnimationFrame(function () {
              var fromRangeInput = row.querySelector('[data-rate-sheet-range-field="from_range"]');

              if (fromRangeInput) {
                fromRangeInput.focus();
                fromRangeInput.select();
              }
            });

            return;
          }

          if (removeButton && table.contains(removeButton)) {
            event.preventDefault();

            var removableRow = removeButton.closest('[data-rate-sheet-range-row]');

            if (!removableRow || !removableRow.classList.contains('rate-sheet-range-row--dynamic')) {
              return;
            }

            var removedAttributeId = removableRow.getAttribute('data-attribute-id');

            removableRow.parentNode.removeChild(removableRow);

            if (removedAttributeId) {
              updateRangeControls(tbody, removedAttributeId);
            }

            updateRangesPayload(table);

            return;
          }

          if (toggleButton && table.contains(toggleButton)) {
            event.preventDefault();

            var toggleAttributeId = toggleButton.getAttribute('data-attribute-id');
            var toggleAttributeRow = tbody.querySelector('[data-rate-sheet-attribute-row][data-attribute-id="' + toggleAttributeId + '"]');

            if (!toggleAttributeId || !toggleAttributeRow) {
              return;
            }

            toggleAttributeRow.setAttribute(
              'data-rate-sheet-collapsed',
              toggleAttributeRow.getAttribute('data-rate-sheet-collapsed') === 'true' ? 'false' : 'true'
            );

            updateRangeControls(tbody, toggleAttributeId);
          }
        });
      });
    }
  };

})(Drupal, once);
