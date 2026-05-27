(function (Drupal, once) {
  'use strict';

  /**
   * Debounce function to limit how often a function is called.
   *
   * @param {Function} func
   *   The function to debounce.
   * @param {number} wait
   *   The delay in milliseconds.
   *
   * @return {Function}
   *   The debounced function.
   */
  function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
      var context = this;
      var args = arguments;
      var later = function () {
        timeout = null;
        func.apply(context, args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Gets all attribute items within a container.
   *
   * @param {HTMLElement} container
   *   The container element.
   *
   * @return {Array}
   *   Array of attribute item elements.
   */
  function getAttributeItems(container) {
    if (!container) {
      return [];
    }
    return Array.prototype.slice.call(container.querySelectorAll('[data-rate-sheet-attribute-item]'));
  }

  /**
   * Gets all range rows for an attribute item.
   *
   * @param {HTMLElement} attributeItem
   *   The attribute item element.
   *
   * @return {Array}
   *   Array of range row elements.
   */
  function getRangeRows(attributeItem) {
    if (!attributeItem) {
      return [];
    }
    return Array.prototype.slice.call(attributeItem.querySelectorAll('[data-rate-sheet-range-row]'));
  }

  /**
   * Gets a specific attribute item by ID.
   *
   * @param {HTMLElement} container
   *   The container element.
   * @param {string} attributeId
   *   The attribute ID.
   *
   * @return {HTMLElement|null}
   *   The attribute item element or null.
   */
  function getAttributeItem(container, attributeId) {
    if (!container || !attributeId) {
      return null;
    }
    return container.querySelector('[data-rate-sheet-attribute-item][data-attribute-id="' + attributeId + '"]');
  }

  /**
   * Gets the range table for an attribute item.
   *
   * @param {HTMLElement} attributeItem
   *   The attribute item element.
   *
   * @return {HTMLElement|null}
   *   The table element or null.
   */
  function getRangeTable(attributeItem) {
    return attributeItem ? attributeItem.querySelector('[data-rate-sheet-range-table]') : null;
  }

  /**
   * Gets the table body for an attribute item's range table.
   *
   * @param {HTMLElement} attributeItem
   *   The attribute item element.
   *
   * @return {HTMLElement|null}
   *   The tbody element or null.
   */
  function getRangeTableBody(attributeItem) {
    var table = getRangeTable(attributeItem);

    if (!table || !table.tBodies.length) {
      return null;
    }

    return table.tBodies[0];
  }

  /**
   * Gets a specific range input field.
   *
   * @param {HTMLElement} rangeRow
   *   The range row element.
   * @param {string} fieldName
   *   The field name.
   *
   * @return {HTMLElement|null}
   *   The input element or null.
   */
  function getRangeInput(rangeRow, fieldName) {
    return rangeRow ? rangeRow.querySelector('[data-rate-sheet-range-field="' + fieldName + '"]') : null;
  }

  /**
   * Gets numeric value from an input with fallback.
   *
   * @param {HTMLElement} input
   *   The input element.
   * @param {number} fallback
   *   The fallback value.
   *
   * @return {number}
   *   The parsed value or fallback.
   */
  function getNumericInputValue(input, fallback) {
    var parsed = input ? parseFloat(input.value) : NaN;

    if (Number.isNaN(parsed)) {
      return fallback;
    }

    return parsed;
  }

  /**
   * Formats a range value to 3 decimal places.
   *
   * @param {number} value
   *   The value to format.
   *
   * @return {string}
   *   The formatted value.
   */
  function formatRangeValue(value) {
    var rounded = Math.round(value * 1000) / 1000;

    return rounded.toFixed(3);
  }

  function getNextFromRangeValue(attributeItem) {
    var rows = getRangeRows(attributeItem);
    var lastRow = rows.length ? rows[rows.length - 1] : null;

    if (!lastRow) {
      return '0.000';
    }

    var lastFromRangeInput = getRangeInput(lastRow, 'from_range');
    var lastToRangeInput = getRangeInput(lastRow, 'to_range');
    var lastFromRangeValue = getNumericInputValue(lastFromRangeInput, 0);
    var lastToRangeValue = getNumericInputValue(lastToRangeInput, -1);

    if (lastToRangeValue >= lastFromRangeValue) {
      return formatRangeValue(lastToRangeValue + 1);
    }

    return formatRangeValue(lastFromRangeValue + 1);
  }

  function setFiniteToRangeValue(rangeRow, value) {
    var toRangeInput = getRangeInput(rangeRow, 'to_range');

    if (!toRangeInput) {
      return;
    }

    toRangeInput.value = formatRangeValue(Math.max(0, value));
    toRangeInput.tabIndex = 0;
    toRangeInput.setAttribute('aria-hidden', 'false');
  }

  function updateRangeLabels(attributeItem) {
    getRangeRows(attributeItem).forEach(function (row, index) {
      var label = row.querySelector('.rate-sheet-range-label-text');

      if (label) {
        label.textContent = 'Range ' + (index + 1);
      }
    });
  }

  function updateTieredBadge(attributeItem, isSingleRange) {
    var badge = attributeItem.querySelector('[data-rate-sheet-tiered-badge]');

    if (!badge) {
      return;
    }

    badge.textContent = isSingleRange ? 'Flat' : 'Tiered';
    badge.setAttribute('data-rate-sheet-mode', isSingleRange ? 'flat' : 'tiered');
    badge.setAttribute('title', isSingleRange ? 'Flat pricing: one unbounded range' : 'Tiered pricing: multiple ranges');

    badge.classList.toggle('rate-sheet-pricing-mode-badge--flat', isSingleRange);
    badge.classList.toggle('rate-sheet-pricing-mode-badge--tiered', !isSingleRange);
  }

  function updateAttributeRangeMode(attributeItem) {
    var rows = getRangeRows(attributeItem);
    var isSingleRange = rows.length <= 1;
    var tieredCalculationCheckbox = attributeItem.querySelector('.rate-sheet-tiered-calculation-field input[type="checkbox"], .rate-sheet-tiered-calculation input[type="checkbox"]');

    attributeItem.classList.toggle('is-single-range', isSingleRange);
    attributeItem.classList.toggle('is-multi-range', !isSingleRange);

    if (tieredCalculationCheckbox) {
      tieredCalculationCheckbox.checked = !isSingleRange;
      tieredCalculationCheckbox.value = !isSingleRange ? '1' : '0';
    }

    updateTieredBadge(attributeItem, isSingleRange);

    rows.forEach(function (row, index) {
      var isLastRow = index === rows.length - 1;
      var toRangeInputWrapper = row.querySelector('[data-rate-sheet-to-range-input-wrapper]');
      var unboundedBadge = row.querySelector('[data-rate-sheet-unbounded-badge]');
      var toRangeInput = getRangeInput(row, 'to_range');
      var fromRangeInput = getRangeInput(row, 'from_range');
      var removeButton = row.querySelector('[data-rate-sheet-remove-range]');

      row.classList.toggle('is-single-range-row', isSingleRange);
      row.classList.toggle('is-multi-range-row', !isSingleRange);
      row.classList.toggle('is-unbounded-range-row', isLastRow);
      row.classList.toggle('is-finite-range-row', !isLastRow);

      if (toRangeInputWrapper) {
        toRangeInputWrapper.hidden = isLastRow;
      }

      if (unboundedBadge) {
        unboundedBadge.hidden = !isLastRow;
      }

      if (removeButton) {
        removeButton.hidden = !(isLastRow && rows.length > 1);
        removeButton.disabled = !(isLastRow && rows.length > 1);
      }

      if (toRangeInput) {
        if (isLastRow) {
          toRangeInput.value = '-1';
          toRangeInput.tabIndex = -1;
          toRangeInput.setAttribute('aria-hidden', 'true');
        }
        else {
          if (getNumericInputValue(toRangeInput, -1) < 0) {
            toRangeInput.value = formatRangeValue(getNumericInputValue(fromRangeInput, 0));
          }

          toRangeInput.tabIndex = 0;
          toRangeInput.setAttribute('aria-hidden', 'false');
        }
      }
    });
  }

  /**
   * Updates the hidden JSON payload with current range data.
   *
   * @param {HTMLElement} container
   *   The container element.
   */
  function updateRangesPayload(container) {
    if (!container) {
      return;
    }

    var form = container.closest('form');
    var payloadInput = form ? form.querySelector('[data-rate-sheet-ranges-payload]') : null;

    if (!payloadInput) {
      return;
    }

    var payload = {};

    try {
      getAttributeItems(container).forEach(function (attributeItem) {
      var attributeId = attributeItem.getAttribute('data-attribute-id');

      if (!attributeId) {
        return;
      }

      updateAttributeRangeMode(attributeItem);

      payload[attributeId] = {};

      getRangeRows(attributeItem).forEach(function (rangeRow) {
        var rangeIndex = rangeRow.getAttribute('data-range-index');
        var inputs = rangeRow.querySelectorAll('[data-rate-sheet-range-field]');

        if (rangeIndex === null || !inputs.length) {
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
    catch (error) {
      console.error('Error updating ranges payload:', error);
    }
  }

  /**
   * Sets the collapsed state of an accordion item.
   *
   * @param {HTMLElement} attributeItem
   *   The attribute item element.
   * @param {boolean} collapsed
   *   Whether the accordion should be collapsed.
   */
  function setAccordionCollapsed(attributeItem, collapsed) {
    if (!attributeItem) {
      return;
    }

    var toggleButton = attributeItem.querySelector('[data-rate-sheet-accordion-toggle]');
    var toggleIcon = attributeItem.querySelector('[data-rate-sheet-accordion-icon]');
    var panel = attributeItem.querySelector('[data-rate-sheet-accordion-panel]');

    attributeItem.setAttribute('data-rate-sheet-collapsed', collapsed ? 'true' : 'false');
    attributeItem.classList.toggle('is-collapsed', collapsed);
    attributeItem.classList.toggle('is-expanded', !collapsed);

    if (panel) {
      panel.hidden = collapsed;
      panel.setAttribute('aria-hidden', collapsed ? 'true' : 'false');

      // Ensure panel has proper ARIA role.
      if (!panel.getAttribute('role')) {
        panel.setAttribute('role', 'region');
      }

      // Link panel to toggle button.
      if (toggleButton && !panel.getAttribute('aria-labelledby')) {
        var buttonId = toggleButton.id || 'accordion-toggle-' + Math.random().toString(36).substr(2, 9);
        toggleButton.id = buttonId;
        panel.setAttribute('aria-labelledby', buttonId);
      }
    }

    if (toggleButton) {
      toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

      // Ensure button has proper ARIA role.
      if (!toggleButton.getAttribute('role')) {
        toggleButton.setAttribute('role', 'button');
      }
    }

    if (toggleIcon) {
      toggleIcon.textContent = collapsed ? '+' : '−';
      toggleIcon.setAttribute('aria-hidden', 'true');
    }
  }

  function initializeAccordion(container) {
    getAttributeItems(container).forEach(function (attributeItem) {
      var collapsed = attributeItem.getAttribute('data-rate-sheet-collapsed') === 'true';

      setAccordionCollapsed(attributeItem, collapsed);
      updateRangeLabels(attributeItem);
      updateAttributeRangeMode(attributeItem);
    });
  }

  function createRangeInput(attributeId, rangeIndex, fieldName, defaultValue) {
    var wrapper = document.createElement('div');
    var input = document.createElement('input');

    wrapper.className = 'rate-sheet-dynamic-number-field';

    input.type = 'number';
    input.name = 'rate_sheet_item_ranges[' + attributeId + '][' + rangeIndex + '][' + fieldName + ']';
    input.id = 'edit-rate-sheet-item-ranges-' + attributeId + '-' + rangeIndex + '-' + fieldName.replace(/_/g, '-');
    input.min = fieldName === 'to_range' ? '-1' : '0';
    input.step = '0.001';
    input.value = defaultValue || '0.000';
    input.className = 'form-number rate-sheet-range-input';
    input.setAttribute('data-rate-sheet-range-field', fieldName);
    input.setAttribute('data-attribute-id', attributeId);
    input.setAttribute('data-range-index', rangeIndex);

    wrapper.appendChild(input);

    return wrapper;
  }

  function createToRangeCell(attributeId, rangeIndex) {
    var cell = document.createElement('td');
    var inputWrapper = document.createElement('span');
    var unboundedBadge = document.createElement('span');
    var infinity = document.createElement('span');

    cell.className = 'rate-sheet-to-range-cell';
    cell.setAttribute('data-rate-sheet-to-range-cell', '');

    inputWrapper.className = 'rate-sheet-to-range-input-wrapper';
    inputWrapper.setAttribute('data-rate-sheet-to-range-input-wrapper', '');
    inputWrapper.appendChild(createRangeInput(attributeId, rangeIndex, 'to_range', '-1'));

    infinity.setAttribute('aria-hidden', 'true');
    infinity.textContent = '∞';

    unboundedBadge.className = 'rate-sheet-unbounded-badge';
    unboundedBadge.setAttribute('data-rate-sheet-unbounded-badge', '');
    unboundedBadge.appendChild(infinity);
    unboundedBadge.appendChild(document.createTextNode(' Unbounded'));

    cell.appendChild(inputWrapper);
    cell.appendChild(unboundedBadge);

    return cell;
  }

  function createRangeRow(attributeId, rangeIndex, fromRangeValue) {
    var row = document.createElement('tr');

    row.className = 'rate-sheet-range-row rate-sheet-range-row--dynamic';
    row.setAttribute('data-rate-sheet-range-row', '');
    row.setAttribute('data-attribute-id', attributeId);
    row.setAttribute('data-range-index', rangeIndex);

    var labelCell = document.createElement('td');
    var label = document.createElement('span');

    labelCell.className = 'rate-sheet-range-label';
    label.className = 'rate-sheet-range-label-text';
    label.textContent = 'Range ' + (rangeIndex + 1);
    labelCell.appendChild(label);
    row.appendChild(labelCell);

    var fromRangeCell = document.createElement('td');
    fromRangeCell.appendChild(createRangeInput(attributeId, rangeIndex, 'from_range', fromRangeValue || '0.000'));
    row.appendChild(fromRangeCell);

    row.appendChild(createToRangeCell(attributeId, rangeIndex));

    ['partial_range', 'success_rate'].forEach(function (fieldName) {
      var cell = document.createElement('td');

      cell.appendChild(createRangeInput(attributeId, rangeIndex, fieldName, '0.000'));
      row.appendChild(cell);
    });

    var actionsCell = document.createElement('td');
    var removeRangeButton = document.createElement('button');

    actionsCell.className = 'rate-sheet-range-actions';
    removeRangeButton.type = 'button';
    removeRangeButton.className = 'button button--small rate-sheet-remove-range';
    removeRangeButton.setAttribute('data-rate-sheet-remove-range', '');
    removeRangeButton.hidden = true;
    removeRangeButton.disabled = true;
    removeRangeButton.textContent = 'Remove';

    actionsCell.appendChild(removeRangeButton);
    row.appendChild(actionsCell);

    return row;
  }

  Drupal.behaviors.rateSheetRanges = {
    attach: function (context) {
      once('rate-sheet-ranges', '[data-rate-sheet-ranges]', context).forEach(function (container) {
        var form = container.closest('form');

        initializeAccordion(container);
        updateRangesPayload(container);

        // Debounce payload updates to avoid excessive JSON serialization.
        var debouncedUpdate = debounce(function () {
          updateRangesPayload(container);
        }, 300);

        container.addEventListener('input', debouncedUpdate);

        container.addEventListener('change', function () {
          updateRangesPayload(container);
        });

        if (form) {
          form.addEventListener('submit', function () {
            updateRangesPayload(container);
          }, true);
        }

        container.addEventListener('click', function (event) {
          var addButton = event.target.closest('[data-rate-sheet-add-range]');
          var removeButton = event.target.closest('[data-rate-sheet-remove-range]');
          var accordionToggle = event.target.closest('[data-rate-sheet-accordion-toggle]');

          if (addButton && container.contains(addButton)) {
            event.preventDefault();

            var attributeId = addButton.getAttribute('data-attribute-id');
            var attributeItem = getAttributeItem(container, attributeId);
            var tbody = getRangeTableBody(attributeItem);

            if (!attributeId || !attributeItem || !tbody) {
              return;
            }

            var existingRows = getRangeRows(attributeItem);
            var previousLastRow = existingRows.length ? existingRows[existingRows.length - 1] : null;
            var maxIndex = -1;
            var fromRangeValue = getNextFromRangeValue(attributeItem);
            var numericFromRangeValue = parseFloat(fromRangeValue);

            existingRows.forEach(function (row) {
              var currentIndex = parseInt(row.getAttribute('data-range-index'), 10);

              if (!Number.isNaN(currentIndex) && currentIndex > maxIndex) {
                maxIndex = currentIndex;
              }
            });

            var rangeIndex = maxIndex + 1;
            var row = createRangeRow(attributeId, rangeIndex, fromRangeValue);

            if (previousLastRow && !Number.isNaN(numericFromRangeValue)) {
              setFiniteToRangeValue(previousLastRow, numericFromRangeValue - 1);
            }

            tbody.appendChild(row);

            setAccordionCollapsed(attributeItem, false);
            updateRangeLabels(attributeItem);
            updateAttributeRangeMode(attributeItem);
            updateRangesPayload(container);

            window.requestAnimationFrame(function () {
              var fromRangeInput = row.querySelector('[data-rate-sheet-range-field="from_range"]');

              if (fromRangeInput) {
                fromRangeInput.focus();
                fromRangeInput.select();
              }
            });

            return;
          }

          if (removeButton && container.contains(removeButton)) {
            event.preventDefault();

            var removableRow = removeButton.closest('[data-rate-sheet-range-row]');
            var removedAttributeId = removableRow ? removableRow.getAttribute('data-attribute-id') : null;
            var removedAttributeItem = removedAttributeId ? getAttributeItem(container, removedAttributeId) : null;
            var rows = removedAttributeItem ? getRangeRows(removedAttributeItem) : [];
            var isLastRow = rows.length > 0 && removableRow === rows[rows.length - 1];

            if (
              !removableRow ||
              !removedAttributeItem ||
              !removableRow.classList.contains('rate-sheet-range-row--dynamic') ||
              rows.length <= 1 ||
              !isLastRow
            ) {
              return;
            }

            removableRow.parentNode.removeChild(removableRow);

            updateRangeLabels(removedAttributeItem);
            updateAttributeRangeMode(removedAttributeItem);
            updateRangesPayload(container);

            return;
          }

          if (accordionToggle && container.contains(accordionToggle)) {
            event.preventDefault();

            var toggleAttributeId = accordionToggle.getAttribute('data-attribute-id');
            var toggleAttributeItem = getAttributeItem(container, toggleAttributeId);

            if (!toggleAttributeItem) {
              return;
            }

            setAccordionCollapsed(
              toggleAttributeItem,
              toggleAttributeItem.getAttribute('data-rate-sheet-collapsed') !== 'true'
            );
          }
        });
      });
    }
  };

})(Drupal, once);
