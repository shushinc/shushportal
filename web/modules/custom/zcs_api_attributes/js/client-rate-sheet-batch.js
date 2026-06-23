(function (Drupal, once) {
  'use strict';

  /**
   * Handles batch selection and operations for client rate sheets.
   */
  Drupal.behaviors.clientRateSheetBatch = {
    attach: function (context) {
      once('client-rate-sheet-batch', '[data-client-rate-sheet-batch-form]', context).forEach(function (form) {
        var selectAllCheckbox = form.querySelector('[data-select-all-checkbox]');
        var itemCheckboxes = form.querySelectorAll('[data-item-checkbox]');
        var approveButton = form.querySelector('[data-batch-approve]');
        var rejectButton = form.querySelector('[data-batch-reject]');
        var selectedCountDisplay = form.querySelector('[data-selected-count]');

        /**
         * Updates the selected count display and button states.
         */
        function updateSelectionState() {
          var checkedCount = 0;
          var totalCount = itemCheckboxes.length;

          itemCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
              checkedCount++;
            }
          });

          // Update select all checkbox state
          if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
          }

          // Update selected count display
          if (selectedCountDisplay) {
            if (checkedCount > 0) {
              selectedCountDisplay.textContent = checkedCount + ' selected';
              selectedCountDisplay.hidden = false;
            } else {
              selectedCountDisplay.hidden = true;
            }
          }

          // Enable/disable batch action buttons
          var hasSelection = checkedCount > 0;
          if (approveButton) {
            approveButton.disabled = !hasSelection;
          }
          if (rejectButton) {
            rejectButton.disabled = !hasSelection;
          }
        }

        // Handle select all checkbox
        if (selectAllCheckbox) {
          selectAllCheckbox.addEventListener('change', function () {
            var checked = selectAllCheckbox.checked;
            itemCheckboxes.forEach(function (checkbox) {
              checkbox.checked = checked;
            });
            updateSelectionState();
          });
        }

        // Handle individual checkboxes
        itemCheckboxes.forEach(function (checkbox) {
          checkbox.addEventListener('change', function () {
            updateSelectionState();
          });
        });

        // Handle approve button
        if (approveButton) {
          approveButton.addEventListener('click', function (event) {
            var checkedItems = [];
            itemCheckboxes.forEach(function (checkbox) {
              if (checkbox.checked) {
                checkedItems.push(checkbox.value);
              }
            });

            if (checkedItems.length === 0) {
              event.preventDefault();
              alert('Please select at least one item to approve.');
              return;
            }

            var confirmed = confirm(
              'Are you sure you want to approve ' + checkedItems.length + ' client rate sheet(s)?'
            );

            if (!confirmed) {
              event.preventDefault();
            }
          });
        }

        // Handle reject button
        if (rejectButton) {
          rejectButton.addEventListener('click', function (event) {
            var checkedItems = [];
            itemCheckboxes.forEach(function (checkbox) {
              if (checkbox.checked) {
                checkedItems.push(checkbox.value);
              }
            });

            if (checkedItems.length === 0) {
              event.preventDefault();
              alert('Please select at least one item to reject.');
              return;
            }

            var confirmed = confirm(
              'Are you sure you want to reject ' + checkedItems.length + ' client rate sheet(s)?'
            );

            if (!confirmed) {
              event.preventDefault();
            }
          });
        }

        // Initialize state
        updateSelectionState();
      });

      // Handle rate sheet name filter
      once('client-rate-sheet-filter', '[data-rate-sheet-name-filter]', context).forEach(function (filterInput) {
        var filterForm = filterInput.closest('form');
        
        if (!filterForm) {
          return;
        }

        filterInput.addEventListener('input', debounce(function () {
          filterForm.submit();
        }, 500));
      });
    }
  };

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

})(Drupal, once);
