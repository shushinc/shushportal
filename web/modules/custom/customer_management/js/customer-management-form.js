(function (Drupal, once) {
  'use strict';

  function createDemandPartnerModal(demandPartners, selectedIds) {
    var overlay = document.createElement('div');
    overlay.className = 'rate-sheet-client-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'customer-demand-partner-modal-title');

    var modal = document.createElement('div');
    modal.className = 'rate-sheet-client-modal customer-demand-partner-modal';

    var header = document.createElement('div');
    header.className = 'rate-sheet-client-modal-header';

    var title = document.createElement('h2');
    title.id = 'customer-demand-partner-modal-title';
    title.textContent = 'Select Demand Partners';
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
    filterLabel.htmlFor = 'customer-demand-partner-filter';
    filterLabel.textContent = 'Filter demand partners:';
    filterLabel.className = 'rate-sheet-client-filter-label';

    var filterInput = document.createElement('input');
    filterInput.type = 'search';
    filterInput.id = 'customer-demand-partner-filter';
    filterInput.className = 'rate-sheet-client-filter-input';
    filterInput.placeholder = 'Type to filter demand partners...';

    filterWrapper.appendChild(filterLabel);
    filterWrapper.appendChild(filterInput);

    var selectedTagsWrapper = document.createElement('div');
    selectedTagsWrapper.className = 'rate-sheet-selected-clients-tags';
    selectedTagsWrapper.setAttribute('data-selected-tags', '');

    var clientListWrapper = document.createElement('div');
    clientListWrapper.className = 'rate-sheet-client-list-wrapper';

    var clientList = document.createElement('div');
    clientList.className = 'rate-sheet-client-list';
    clientList.setAttribute('data-demand-partner-list', '');

    demandPartners.forEach(function (partner) {
      var item = document.createElement('div');
      item.className = 'rate-sheet-client-item';
      item.setAttribute('data-demand-partner-id', partner.id);
      item.setAttribute('data-demand-partner-name', (partner.label || '').toLowerCase());

      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.id = 'customer-demand-partner-' + partner.id;
      checkbox.value = partner.id;
      checkbox.className = 'rate-sheet-client-checkbox';
      checkbox.checked = selectedIds.indexOf(String(partner.id)) !== -1;

      var label = document.createElement('label');
      label.htmlFor = 'customer-demand-partner-' + partner.id;
      label.textContent = partner.label;

      item.appendChild(checkbox);
      item.appendChild(label);
      clientList.appendChild(item);
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
      filterInput: filterInput,
      selectedTagsWrapper: selectedTagsWrapper,
      clientList: clientList,
      closeButton: closeButton,
      cancelButton: cancelButton,
      saveButton: saveButton
    };
  }

  function parseJson(value, fallback) {
    try {
      return JSON.parse(value || '');
    }
    catch (error) {
      return fallback;
    }
  }

  function normalizeSelection(value) {
    if (!Array.isArray(value)) {
      return [];
    }

    return value
      .map(function (item) {
        return String(item);
      })
      .filter(function (item) {
        return item !== '';
      });
  }

  function updateSelectedTags(tagsWrapper, selectedPartners, onRemove) {
    tagsWrapper.innerHTML = '';

    if (!selectedPartners.length) {
      var emptyMessage = document.createElement('p');
      emptyMessage.className = 'rate-sheet-no-clients-selected';
      emptyMessage.textContent = 'No demand partners selected';
      tagsWrapper.appendChild(emptyMessage);
      return;
    }

    selectedPartners.forEach(function (partner) {
      var tag = document.createElement('span');
      tag.className = 'rate-sheet-client-tag';
      tag.setAttribute('data-demand-partner-id', partner.id);

      var tagLabel = document.createElement('span');
      tagLabel.className = 'rate-sheet-client-tag-label';
      tagLabel.textContent = partner.label;
      tag.appendChild(tagLabel);

      var removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'rate-sheet-client-tag-remove';
      removeButton.setAttribute('aria-label', 'Remove ' + partner.label);
      removeButton.innerHTML = '&times;';
      removeButton.addEventListener('click', function () {
        onRemove(partner.id);
      });

      tag.appendChild(removeButton);
      tagsWrapper.appendChild(tag);
    });
  }

  function filterDemandPartnerList(list, query) {
    var normalizedQuery = query.trim().toLowerCase();
    var items = list.querySelectorAll('.rate-sheet-client-item');

    items.forEach(function (item) {
      var partnerName = item.getAttribute('data-demand-partner-name') || '';
      var matches = !normalizedQuery || partnerName.indexOf(normalizedQuery) !== -1;
      item.style.display = matches ? '' : 'none';
    });
  }

  function initDemandPartnerSelector(button) {
    var form = button.closest('form');
    if (!form) {
      return;
    }

    var demandPartnersDataElement = form.querySelector('[data-customer-demand-partners-data]');
    var selectedInput = form.querySelector('[data-customer-selected-demand-partners]');
    var selectedDataElement = form.querySelector('[data-customer-selected-demand-partners-data]');
    var selectedTagsWrapper = form.querySelector('[data-customer-selected-demand-partners-tags]');

    if (!demandPartnersDataElement || !selectedInput || !selectedDataElement || !selectedTagsWrapper) {
      return;
    }

    var demandPartners = parseJson(demandPartnersDataElement.value, []);
    var demandPartnersMap = {};

    demandPartners.forEach(function (partner) {
      demandPartnersMap[String(partner.id)] = partner;
    });

    function getSelectedIds() {
      return normalizeSelection(selectedInput.value ? [selectedInput.value].flat() : []);
    }

    function readHiddenValues() {
      var value = selectedInput.value;

      if (Array.isArray(value)) {
        return normalizeSelection(value);
      }

      if (typeof value === 'string' && value.indexOf(',') !== -1) {
        return normalizeSelection(value.split(','));
      }

      if (typeof value === 'string' && value !== '') {
        return [value];
      }

      var selectedData = parseJson(selectedDataElement.value, []);
      if (Array.isArray(selectedData) && selectedData.length) {
        return normalizeSelection(selectedData.map(function (item) {
          return item.id;
        }));
      }

      return [];
    }

    function writeSelection(selectedIds) {
      selectedInput.value = selectedIds.join(',');
      selectedDataElement.value = JSON.stringify(selectedIds.map(function (id) {
        return demandPartnersMap[id];
      }).filter(Boolean));
      updateDisplay(selectedIds);
    }

    function getSelectedPartners(selectedIds) {
      return selectedIds.map(function (id) {
        return demandPartnersMap[id];
      }).filter(Boolean);
    }

    function updateButtonText(selectedIds) {
      button.textContent = selectedIds.length === 0 ? 'Add Demand Partners' : 'Demand Partners Selected (' + selectedIds.length + ')';
    }

    function updateDisplay(selectedIds) {
      updateButtonText(selectedIds);
      updateSelectedTags(selectedTagsWrapper, getSelectedPartners(selectedIds), function (partnerId) {
        var currentIds = readHiddenValues().filter(function (id) {
          return id !== String(partnerId);
        });
        writeSelection(currentIds);
      });
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();

      var initialSelection = readHiddenValues();
      var modalElements = createDemandPartnerModal(demandPartners, initialSelection);
      document.body.appendChild(modalElements.overlay);

      var currentSelection = initialSelection.slice();

      function syncSelectedTags() {
        updateSelectedTags(modalElements.selectedTagsWrapper, getSelectedPartners(currentSelection), function (partnerId) {
          currentSelection = currentSelection.filter(function (id) {
            return id !== String(partnerId);
          });

          var checkbox = modalElements.clientList.querySelector('input[value="' + partnerId + '"]');
          if (checkbox) {
            checkbox.checked = false;
          }

          syncSelectedTags();
        });
      }

      function closeModal() {
        if (modalElements.overlay.parentNode) {
          modalElements.overlay.parentNode.removeChild(modalElements.overlay);
        }
      }

      syncSelectedTags();

      modalElements.filterInput.addEventListener('input', function () {
        filterDemandPartnerList(modalElements.clientList, modalElements.filterInput.value);
      });

      modalElements.clientList.addEventListener('change', function (event) {
        if (event.target.type !== 'checkbox') {
          return;
        }

        var partnerId = String(event.target.value);

        if (event.target.checked) {
          if (currentSelection.indexOf(partnerId) === -1) {
            currentSelection.push(partnerId);
          }
        }
        else {
          currentSelection = currentSelection.filter(function (id) {
            return id !== partnerId;
          });
        }

        syncSelectedTags();
      });

      modalElements.closeButton.addEventListener('click', closeModal);
      modalElements.cancelButton.addEventListener('click', closeModal);

      modalElements.saveButton.addEventListener('click', function () {
        writeSelection(currentSelection);
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
    });

    updateDisplay(readHiddenValues());
  }

  Drupal.behaviors.customerManagementForm = {
    attach: function (context) {
      once('customer-management-demand-partner-selector', '[data-customer-add-demand-partner]', context).forEach(initDemandPartnerSelector);
    }
  };
})(Drupal, once);
