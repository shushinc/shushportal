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
        var disableButton = form.querySelector('[data-batch-disable]');
        var enableButton = form.querySelector('[data-batch-enable]');
        var selectedCountDisplay = form.querySelector('[data-selected-count]');

        function handleBulkAction(button, actionLabel) {
          if (!button) {
            return;
          }

          button.addEventListener('click', function (event) {
            var checkedItems = [];

            itemCheckboxes.forEach(function (checkbox) {
              if (checkbox.checked) {
                checkedItems.push(checkbox.value);
              }
            });

            if (checkedItems.length === 0) {
              event.preventDefault();
              alert('Please select at least one item to ' + actionLabel + '.');
              return;
            }

            var confirmed = confirm(
              'Are you sure you want to ' +
                actionLabel +
                ' ' +
                checkedItems.length +
                ' client rate sheet(s)?'
            );

            if (!confirmed) {
              event.preventDefault();
            }
          });
        }

        /**
         * Updates the selected count display and button states.
         */
        function updateSelectionState() {
          var checkedCount = 0;
          var totalCount = 0;
          var enabledCount = 0;

          itemCheckboxes.forEach(function (checkbox) {
            if (!checkbox.disabled) {
              enabledCount++;
            }
            if (checkbox.checked && !checkbox.disabled) {
              checkedCount++;
            }
            if (!checkbox.disabled) {
              totalCount++;
            }
          });

          // Update select all checkbox state (only consider enabled checkboxes)
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
          if (disableButton) {
            disableButton.disabled = !hasSelection;
          }
          if (enableButton) {
            enableButton.disabled = !hasSelection;
          }
        }

        // Handle select all checkbox
        if (selectAllCheckbox) {
          selectAllCheckbox.addEventListener('change', function () {
            var checked = selectAllCheckbox.checked;
            itemCheckboxes.forEach(function (checkbox) {
              // Only change state of enabled checkboxes
              if (!checkbox.disabled) {
                checkbox.checked = checked;
              }
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
          handleBulkAction(approveButton, 'approve');
        }

        // Handle reject button
        if (rejectButton) {
          handleBulkAction(rejectButton, 'reject');
        }

        if (disableButton) {
          handleBulkAction(disableButton, 'disable');
        }

        if (enableButton) {
          handleBulkAction(enableButton, 'enable');
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

        var tableRows = filterForm.querySelectorAll('.client-rate-sheet-table tbody tr');

        filterInput.addEventListener('input', function () {
          var query = filterInput.value.trim().toLowerCase();

          tableRows.forEach(function (row) {
            var rateSheetNameCell = row.cells[3]; // Rate Sheet Name is the 4th column (index 3)

            if (!rateSheetNameCell) {
              return;
            }

            var rateSheetName = rateSheetNameCell.textContent.toLowerCase();
            var matches = !query || rateSheetName.indexOf(query) !== -1;

            row.style.display = matches ? '' : 'none';
          });

          // Update selection state after filtering
          updateSelectionState();
        });
      });
    }
  };

})(Drupal, once);
