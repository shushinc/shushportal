(function (Drupal, once) {
  'use strict';

  /**
   * Formats a number with thousands separators.
   *
   * @param {number|string} value
   *   The value to format.
   * @param {number} decimalPlaces
   *   Number of decimal places to show.
   *
   * @return {string}
   *   The formatted number string.
   */
  function formatNumberWithSeparators(value, decimalPlaces) {
    var numericValue = parseFloat(value);

    if (Number.isNaN(numericValue)) {
      return '';
    }

    // Use Intl.NumberFormat for proper locale formatting
    var formatter = new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 0,
      maximumFractionDigits: decimalPlaces || 3,
      useGrouping: true
    });

    return formatter.format(numericValue);
  }

  /**
   * Removes formatting from a number string.
   *
   * @param {string} formattedValue
   *   The formatted value string.
   *
   * @return {string}
   *   The unformatted number string.
   */
  function unformatNumber(formattedValue) {
    if (typeof formattedValue !== 'string') {
      return String(formattedValue);
    }

    // Remove all commas and other non-numeric characters except decimal point, minus sign, and digits
    return formattedValue.replace(/[^\d.-]/g, '');
  }

  /**
   * Gets the number of decimal places in a value.
   *
   * @param {string} value
   *   The value string.
   *
   * @return {number}
   *   The number of decimal places.
   */
  function getDecimalPlaces(value) {
    var stringValue = String(value);
    var decimalIndex = stringValue.indexOf('.');

    if (decimalIndex === -1) {
      return 0;
    }

    return Math.min(3, stringValue.length - decimalIndex - 1);
  }

  /**
   * Applies number formatting to range input fields.
   *
   * @param {HTMLElement} input
   *   The input element.
   */
  function applyNumberFormatting(input) {
    if (!input) {
      return;
    }

    var fieldName = input.getAttribute('data-rate-sheet-range-field');

    // Don't format from_range as it's readonly
    if (fieldName === 'from_range') {
      return;
    }

    // Check if handlers are already attached
    if (input.hasAttribute('data-number-format-attached')) {
      return;
    }
    input.setAttribute('data-number-format-attached', 'true');

    // Store original attributes
    var originalType = input.type;
    var originalMin = input.min;
    var originalMax = input.max;
    var originalStep = input.step;

    // On focus, change to text type and show unformatted value for editing
    input.addEventListener('focus', function () {
      // Change to text to allow any input during editing
      input.type = 'text';
      
      var currentValue = input.value;
      if (currentValue) {
        input.value = unformatNumber(currentValue);
      }
    });

    // On blur, format the value and restore number type
    input.addEventListener('blur', function () {
      var currentValue = input.value;
      
      if (currentValue && currentValue.trim() !== '') {
        var unformatted = unformatNumber(currentValue);
        var numericValue = parseFloat(unformatted);

        if (!Number.isNaN(numericValue)) {
          var decimalPlaces = getDecimalPlaces(unformatted);
          var formatted = formatNumberWithSeparators(numericValue, decimalPlaces);
          
          // Temporarily set to text to accept formatted value
          input.type = 'text';
          input.value = formatted;
        }
      }
      
      // Keep as text type to preserve formatted display
      // The form submission handler will unformat before submit
    });

    // Format initial value if it exists and is not empty
    var initialValue = input.value;
    if (initialValue && initialValue.trim() !== '' && initialValue !== '0' && initialValue !== '-1') {
      var numericValue = parseFloat(initialValue);
      if (!Number.isNaN(numericValue)) {
        var decimalPlaces = getDecimalPlaces(initialValue);
        var formatted = formatNumberWithSeparators(numericValue, decimalPlaces);
        input.type = 'text';
        input.value = formatted;
      }
    }
  }

  /**
   * Unformats all number inputs before form submission.
   *
   * @param {HTMLFormElement} form
   *   The form element.
   */
  function unformatAllInputsBeforeSubmit(form) {
    var rangeInputs = form.querySelectorAll('[data-rate-sheet-range-field]');

    Array.prototype.forEach.call(rangeInputs, function (input) {
      if (input.type === 'number' && input.value) {
        var fieldName = input.getAttribute('data-rate-sheet-range-field');
        
        // Don't process from_range as it's readonly
        if (fieldName !== 'from_range') {
          input.value = unformatNumber(input.value);
        }
      }
    });
  }

  Drupal.behaviors.rateSheetNumberFormat = {
    attach: function (context) {
      // Apply formatting to existing range inputs
      once('rate-sheet-number-format', '[data-rate-sheet-range-field]', context).forEach(function (input) {
        applyNumberFormatting(input);
      });

      // Watch for dynamically added inputs
      once('rate-sheet-number-format-observer', '[data-rate-sheet-ranges]', context).forEach(function (container) {
        var observer = new MutationObserver(function (mutations) {
          mutations.forEach(function (mutation) {
            if (mutation.addedNodes.length) {
              Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                if (node.nodeType === 1) {
                  var inputs = node.querySelectorAll ? node.querySelectorAll('[data-rate-sheet-range-field]') : [];
                  
                  Array.prototype.forEach.call(inputs, function (input) {
                    applyNumberFormatting(input);
                  });

                  // Check if the node itself is an input
                  if (node.hasAttribute && node.hasAttribute('data-rate-sheet-range-field')) {
                    applyNumberFormatting(node);
                  }
                }
              });
            }
          });
        });

        observer.observe(container, {
          childList: true,
          subtree: true
        });
      });

      // Unformat all inputs before form submission
      once('rate-sheet-number-format-submit', 'form', context).forEach(function (form) {
        var rangesContainer = form.querySelector('[data-rate-sheet-ranges]');
        
        if (!rangesContainer) {
          return;
        }

        form.addEventListener('submit', function (event) {
          unformatAllInputsBeforeSubmit(form);
        }, true);
      });
    }
  };

})(Drupal, once);
