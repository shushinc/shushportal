(function (Drupal, once) {
  'use strict';

  /**
   * Creates the client selection modal DOM structure.
   *
   * @param {Array} clients
   *   Array of client objects with id and label properties.
   * @param {Array} selectedClientIds
   *   Array of currently selected client IDs.
   *
   * @return {Object}
   *   Object containing modal elements.
   */
  function createClientModal(clients, selectedClientIds) {
    var overlay = document.createElement('div');
    overlay.className = 'rate-sheet-client-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'client-modal-title');

    var modal = document.createElement('div');
    modal.className = 'rate-sheet-client-modal';

    var header = document.createElement('div');
    header.className = 'rate-sheet-client-modal-header';

    var title = document.createElement('h2');
    title.id = 'client-modal-title';
    title.textContent = 'Select Clients';
    header.appendChild(title);

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'rate-sheet-client-modal-close';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.innerHTML = '&times;';
    header.appendChild(closeButton);

    var body = document.createElement('div');
    body.className = 'rate-sheet-client-modal-body';

    var filterWrapper = document.createElement('div');
    filterWrapper.className = 'rate-sheet-client-filter-wrapper';

    var filterLabel = document.createElement('label');
    filterLabel.htmlFor = 'client-filter';
    filterLabel.textContent = 'Filter clients:';
    filterLabel.className = 'rate-sheet-client-filter-label';

    var filterInput = document.createElement('input');
    filterInput.type = 'search';
    filterInput.id = 'client-filter';
    filterInput.className = 'rate-sheet-client-filter-input';
    filterInput.placeholder = 'Type to filter clients...';

    filterWrapper.appendChild(filterLabel);
    filterWrapper.appendChild(filterInput);

    var selectedTagsWrapper = document.createElement('div');
    selectedTagsWrapper.className = 'rate-sheet-selected-clients-tags';
    selectedTagsWrapper.setAttribute('data-selected-tags', '');

    var clientListWrapper = document.createElement('div');
    clientListWrapper.className = 'rate-sheet-client-list-wrapper';

    var clientList = document.createElement('div');
    clientList.className = 'rate-sheet-client-list';
    clientList.setAttribute('data-client-list', '');

    clients.forEach(function (client) {
      var clientItem = document.createElement('div');
      clientItem.className = 'rate-sheet-client-item';
      clientItem.setAttribute('data-client-id', client.id);
      clientItem.setAttribute('data-client-name', client.label.toLowerCase());

      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.id = 'client-' + client.id;
      checkbox.value = client.id;
      checkbox.className = 'rate-sheet-client-checkbox';
      checkbox.checked = selectedClientIds.indexOf(String(client.id)) !== -1;

      var label = document.createElement('label');
      label.htmlFor = 'client-' + client.id;
      label.textContent = client.label;

      clientItem.appendChild(checkbox);
      clientItem.appendChild(label);
      clientList.appendChild(clientItem);
    });

    clientListWrapper.appendChild(clientList);

    body.appendChild(filterWrapper);
    body.appendChild(selectedTagsWrapper);
    body.appendChild(clientListWrapper);

    var footer = document.createElement('div');
    footer.className = 'rate-sheet-client-modal-footer';

    var cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'rate-sheet-client-modal-cancel';
    cancelButton.textContent = 'Cancel';

    var saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'rate-sheet-client-modal-save';
    saveButton.textContent = 'Save';

    footer.appendChild(cancelButton);
    footer.appendChild(saveButton);

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);

    return {
      overlay: overlay,
      modal: modal,
      filterInput: filterInput,
      clientList: clientList,
      selectedTagsWrapper: selectedTagsWrapper,
      closeButton: closeButton,
      cancelButton: cancelButton,
      saveButton: saveButton
    };
  }

  /**
   * Updates the selected clients tags display.
   *
   * @param {HTMLElement} tagsWrapper
   *   The tags wrapper element.
   * @param {Array} selectedClients
   *   Array of selected client objects.
   * @param {Function} onRemove
   *   Callback when a tag is removed.
   */
  function updateSelectedTags(tagsWrapper, selectedClients, onRemove) {
    tagsWrapper.innerHTML = '';

    if (selectedClients.length === 0) {
      var emptyMessage = document.createElement('p');
      emptyMessage.className = 'rate-sheet-no-clients-selected';
      emptyMessage.textContent = 'No clients selected';
      tagsWrapper.appendChild(emptyMessage);
      return;
    }

    selectedClients.forEach(function (client) {
      var tag = document.createElement('span');
      tag.className = 'rate-sheet-client-tag';
      tag.setAttribute('data-client-id', client.id);

      var tagLabel = document.createElement('span');
      tagLabel.className = 'rate-sheet-client-tag-label';
      tagLabel.textContent = client.label;

      var removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'rate-sheet-client-tag-remove';
      removeButton.setAttribute('aria-label', 'Remove ' + client.label);
      removeButton.innerHTML = '&times;';

      removeButton.addEventListener('click', function () {
        onRemove(client.id);
      });

      tag.appendChild(tagLabel);
      tag.appendChild(removeButton);
      tagsWrapper.appendChild(tag);
    });
  }

  /**
   * Filters the client list based on search query.
   *
   * @param {HTMLElement} clientList
   *   The client list element.
   * @param {string} query
   *   The search query.
   */
  function filterClientList(clientList, query) {
    var normalizedQuery = query.trim().toLowerCase();
    var items = clientList.querySelectorAll('.rate-sheet-client-item');

    items.forEach(function (item) {
      var clientName = item.getAttribute('data-client-name') || '';
      var matches = !normalizedQuery || clientName.indexOf(normalizedQuery) !== -1;

      item.style.display = matches ? '' : 'none';
    });
  }

  /**
   * Shows the client selection modal.
   *
   * @param {Array} clients
   *   Array of all available clients.
   * @param {Array} selectedClientIds
   *   Array of currently selected client IDs.
   * @param {Function} onSave
   *   Callback when save is clicked with selected client IDs.
   */
  function showClientModal(clients, selectedClientIds, onSave) {
    var modalElements = createClientModal(clients, selectedClientIds);
    document.body.appendChild(modalElements.overlay);

    var currentSelection = selectedClientIds.slice();

    function getSelectedClients() {
      return clients.filter(function (client) {
        return currentSelection.indexOf(String(client.id)) !== -1;
      });
    }

    function updateTags() {
      updateSelectedTags(modalElements.selectedTagsWrapper, getSelectedClients(), function (clientId) {
        var index = currentSelection.indexOf(String(clientId));
        if (index !== -1) {
          currentSelection.splice(index, 1);
        }

        var checkbox = modalElements.clientList.querySelector('input[value="' + clientId + '"]');
        if (checkbox) {
          checkbox.checked = false;
        }

        updateTags();
      });
    }

    updateTags();

    modalElements.filterInput.addEventListener('input', function () {
      filterClientList(modalElements.clientList, modalElements.filterInput.value);
    });

    modalElements.clientList.addEventListener('change', function (event) {
      if (event.target.type === 'checkbox') {
        var clientId = event.target.value;

        if (event.target.checked) {
          if (currentSelection.indexOf(clientId) === -1) {
            currentSelection.push(clientId);
          }
        } else {
          var index = currentSelection.indexOf(clientId);
          if (index !== -1) {
            currentSelection.splice(index, 1);
          }
        }

        updateTags();
      }
    });

    function closeModal() {
      if (modalElements.overlay.parentNode) {
        document.body.removeChild(modalElements.overlay);
      }
    }

    modalElements.closeButton.addEventListener('click', closeModal);
    modalElements.cancelButton.addEventListener('click', closeModal);

    modalElements.saveButton.addEventListener('click', function () {
      onSave(currentSelection);
      closeModal();
    });

    modalElements.overlay.addEventListener('click', function (event) {
      if (event.target === modalElements.overlay) {
        closeModal();
      }
    });

    window.setTimeout(function () {
      modalElements.filterInput.focus();
    }, 50);
  }

  Drupal.behaviors.rateSheetClients = {
    attach: function (context) {
      once('rate-sheet-clients', '[data-rate-sheet-add-client]', context).forEach(function (button) {
        var form = button.closest('form');
        if (!form) {
          return;
        }

        var clientsDataElement = form.querySelector('[data-rate-sheet-clients-data]');
        var selectedClientsInput = form.querySelector('[data-rate-sheet-selected-clients]');

        if (!clientsDataElement || !selectedClientsInput) {
          console.error('Required client data elements not found');
          return;
        }

        var clients = [];
        try {
          clients = JSON.parse(clientsDataElement.value || '[]');
        } catch (e) {
          console.error('Error parsing clients data:', e);
          return;
        }

        button.addEventListener('click', function (event) {
          event.preventDefault();

          var selectedClientIds = [];
          try {
            var selectedValue = selectedClientsInput.value || '[]';
            selectedClientIds = JSON.parse(selectedValue);
          } catch (e) {
            console.error('Error parsing selected clients:', e);
          }

          showClientModal(clients, selectedClientIds, function (newSelection) {
            selectedClientsInput.value = JSON.stringify(newSelection);

            // Update button text to show count
            var count = newSelection.length;
            var buttonText = count === 0 ? 'Add clients' : 'Clients selected (' + count + ')';
            button.textContent = buttonText;
          });
        });

        // Initialize button text
        try {
          var initialSelection = JSON.parse(selectedClientsInput.value || '[]');
          if (initialSelection.length > 0) {
            button.textContent = 'Clients selected (' + initialSelection.length + ')';
          }
        } catch (e) {
          // Ignore
        }
      });
    }
  };

})(Drupal, once);
