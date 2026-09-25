(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Normalize selected Demand Partner IDs.
   */
  function normalizeIds(values) {
    var ids = [];

    (values || []).forEach(function (value) {
      var id = String(value).trim();

      if (id && ids.indexOf(id) === -1) {
        ids.push(id);
      }
    });

    return ids;
  }

  /**
   * Create a Demand Partner tag.
   */
  function createTag(partner, onRemove) {
    var tag = document.createElement('span');
    tag.className = 'customer-management-demand-partner-tag';

    var label = document.createElement('span');
    label.className = 'customer-management-demand-partner-tag-label';
    label.textContent = partner.label;

    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'customer-management-demand-partner-tag-remove';
    remove.innerHTML = '&times;';
    remove.setAttribute(
      'aria-label',
      'Remove ' + partner.label
    );

    remove.addEventListener('click', function () {
      onRemove(String(partner.id));
    });

    tag.appendChild(label);
    tag.appendChild(remove);

    return tag;
  }

  /**
   * Create the Demand Partner modal.
   */
  function createModal(demandPartners, selectedIds, onSave) {
    
    demandPartners = Array.isArray(demandPartners) ? demandPartners : [];

    selectedIds = Array.isArray(selectedIds) ? selectedIds : [];
    
    var currentSelection = selectedIds.slice();

    var overlay = document.createElement('div');
    overlay.className = 'rate-sheet-client-modal-overlay';

    var modal = document.createElement('div');
    modal.className = 'rate-sheet-client-modal customer-management-demand-partner-modal';

    /*
     * Header.
     */
    var header = document.createElement('div');
    header.className = 'rate-sheet-client-modal-header';

    var title = document.createElement('h2');
    title.textContent = 'Select Demand Partners';

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'rate-sheet-client-modal-close';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', 'Close');

    header.appendChild(title);
    header.appendChild(closeButton);

    /*
     * Body.
     */
    var body = document.createElement('div');
    body.className = 'rate-sheet-client-modal-body';

    var filterWrapper = document.createElement('div');
    filterWrapper.className = 'rate-sheet-client-filter-wrapper';

    var filterLabel = document.createElement('label');
    filterLabel.className = 'rate-sheet-client-filter-label';
    filterLabel.textContent = 'Filter demand partners:';

    var filterInput = document.createElement('input');
    filterInput.type = 'search';
    filterInput.className = 'rate-sheet-client-filter-input';
    filterInput.placeholder = 'Type to filter demand partners...';

    filterWrapper.appendChild(filterLabel);
    filterWrapper.appendChild(filterInput);

    /*
     * Selected tags inside modal.
     */
    var selectedTags = document.createElement('div');
    selectedTags.className = 'rate-sheet-selected-clients-tags';

    /*
     * List.
     */
    var listWrapper = document.createElement('div');
    listWrapper.className = 'rate-sheet-client-list-wrapper';

    var list = document.createElement('div');
    list.className = 'rate-sheet-client-list';

    listWrapper.appendChild(list);

    body.appendChild(filterWrapper);
    body.appendChild(selectedTags);
    body.appendChild(listWrapper);

    /*
     * Footer.
     */
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

    /**
     * Close modal.
     */
    function closeModal() {
      document.removeEventListener('keydown', handleEscape);

      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
    }

    /**
     * Escape handler.
     */
    function handleEscape(event) {
      if (event.key === 'Escape') {
        closeModal();
      }
    }

    /**
     * Find partner by ID.
     */
    function getPartner(id) {
      return demandPartners.find(function (partner) {
        return String(partner.id) === String(id);
      });
    }

    /**
     * Render selected tags inside modal.
     */
    function renderSelectedTags() {
      selectedTags.innerHTML = '';

      if (!currentSelection.length) {
        var empty = document.createElement('div');
        empty.className = 'rate-sheet-no-clients';
        empty.textContent = 'No demand partners selected';

        selectedTags.appendChild(empty);
        return;
      }

      currentSelection.forEach(function (id) {
        var partner = getPartner(id);

        if (!partner) {
          return;
        }

        selectedTags.appendChild(
          createTag(partner, function (partnerId) {
            currentSelection = currentSelection.filter(
              function (selectedId) {
                return selectedId !== partnerId;
              }
            );

            renderList(filterInput.value);
            renderSelectedTags();
          })
        );
      });
    }

    /**
     * Render available Demand Partners.
     */
    function renderList(filter) {
      var search = (filter || '').trim().toLowerCase();

      list.innerHTML = '';

      var matches = demandPartners.filter(function (partner) {
        return !search ||
          String(partner.label)
            .toLowerCase()
            .indexOf(search) !== -1;
      });

      if (!matches.length) {
        var empty = document.createElement('div');
        empty.className = 'rate-sheet-no-clients';
        empty.textContent = 'No demand partners found.';

        list.appendChild(empty);
        return;
      }

      matches.forEach(function (partner) {
        var partnerId = String(partner.id);

        var item = document.createElement('div');
        item.className = 'rate-sheet-client-item';

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'rate-sheet-client-checkbox';
        checkbox.value = partnerId;
        checkbox.id = 'customer-demand-partner-' + partnerId;

        checkbox.checked =
          currentSelection.indexOf(partnerId) !== -1;

        var label = document.createElement('label');
        label.htmlFor = checkbox.id;
        label.textContent = partner.label;

        checkbox.addEventListener('change', function () {
          if (checkbox.checked) {
            if (currentSelection.indexOf(partnerId) === -1) {
              currentSelection.push(partnerId);
            }
          }
          else {
            currentSelection = currentSelection.filter(
              function (id) {
                return id !== partnerId;
              }
            );
          }

          renderSelectedTags();
        });

        item.appendChild(checkbox);
        item.appendChild(label);

        list.appendChild(item);
      });
    }

    filterInput.addEventListener('input', function () {
      renderList(filterInput.value);
    });

    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);

    saveButton.addEventListener('click', function () {
      onSave(normalizeIds(currentSelection));
      closeModal();
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeModal();
      }
    });

    document.addEventListener('keydown', handleEscape);

    document.body.appendChild(overlay);

    renderList('');
    renderSelectedTags();

    setTimeout(function () {
      filterInput.focus();
    }, 0);
  }

  /**
   * Initialize Customer Demand Partners selector.
   */
  function initialize(container) {
    var button = container.querySelector(
      '[data-customer-add-demand-partners]'
    );

    var selectedWrapper = container.querySelector(
      '[data-customer-selected-demand-partners]'
    );

    var form = container.closest('form');

    if (!button || !selectedWrapper || !form) {
      return;
    }

    var hiddenInput = form.querySelector(
      '[data-customer-demand-partners-input]'
    );

    if (!hiddenInput) {
      console.error(
        'Customer Management: demand partners hidden input not found.'
      );
      return;
    }

    var demandPartnersDataInput = form.querySelector(
      '[data-customer-demand-partners-data]'
    );

    var selectedDemandPartnersDataInput = form.querySelector(
      '[data-customer-selected-demand-partners-data]'
    );

    if (!demandPartnersDataInput) {
      console.error(
        'Customer Management: demand partners data input not found.'
      );
      return;
    }

    var demandPartners = [];
    var selectedIds = [];

    try {
      demandPartners = JSON.parse(
        demandPartnersDataInput.value || '[]'
      );

      /*
      * Always guarantee an array.
      */
      if (!Array.isArray(demandPartners)) {
        demandPartners = [];
      }

      /*
      * The submitted hidden field is our primary source of truth.
      */
      selectedIds = normalizeIds(
        (hiddenInput.value || '').split(',')
      );

      /*
      * Fallback to the selected partner metadata when necessary.
      */
      if (
        !selectedIds.length &&
        selectedDemandPartnersDataInput &&
        selectedDemandPartnersDataInput.value
      ) {
        var selectedPartners = JSON.parse(
          selectedDemandPartnersDataInput.value
        );

        if (Array.isArray(selectedPartners)) {
          selectedIds = normalizeIds(
            selectedPartners.map(function (partner) {
              return partner.id;
            })
          );
        }
      }
    }
    catch (error) {
      console.error(
        'Customer Management: unable to parse Demand Partner data.',
        error
      );
      return;
    }

    /*
     * Hidden field is the actual submitted value.
     */
    hiddenInput.value = selectedIds.join(',');

    /**
     * Find Demand Partner.
     */
    function getPartner(id) {
      return demandPartners.find(function (partner) {
        return String(partner.id) === String(id);
      });
    }

    /**
     * Synchronize the Drupal hidden field.
     */
    function syncHiddenInput() {
      hiddenInput.value = selectedIds.join(',');
    }

    /**
     * Render selected Demand Partners underneath the button.
     */
    function renderSelectedPartners() {
      selectedWrapper.innerHTML = '';

      if (!selectedIds.length) {
        return;
      }

      selectedIds.forEach(function (id) {
        var partner = getPartner(id);

        if (!partner) {
          return;
        }

        selectedWrapper.appendChild(
          createTag(partner, function (partnerId) {
            selectedIds = selectedIds.filter(
              function (selectedId) {
                return selectedId !== partnerId;
              }
            );

            syncHiddenInput();
            renderSelectedPartners();
          })
        );
      });
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();

      createModal(
        demandPartners,
        selectedIds,
        function (newSelection) {
          selectedIds = newSelection;

          syncHiddenInput();
          renderSelectedPartners();
        }
      );
    });

    renderSelectedPartners();
  }


  /**
   * Shows the new customer credentials modal.
   * @param {*} credentials 
   */
  function showNewCustomerCredentialsModal(credentials) {
    if (!credentials || !credentials.hashedKey) {
      return;
    }

    var overlay = document.createElement('div');
    overlay.className = 'customer-credentials-modal-overlay';

    var modal = document.createElement('div');
    modal.className = 'customer-credentials-modal';

    var header = document.createElement('div');
    header.className = 'customer-credentials-modal-header';

    var title = document.createElement('h2');
    title.textContent = Drupal.t('Customer credentials');

    header.appendChild(title);

    var body = document.createElement('div');
    body.className = 'customer-credentials-modal-body';

    var warning = document.createElement('div');
    warning.className = 'customer-credentials-warning';

    var warningTitle = document.createElement('strong');
    warningTitle.textContent = Drupal.t('Save this hashed key now');

    var warningText = document.createElement('p');
    warningText.textContent = Drupal.t(
      'This hashed key will only be shown once. Copy it and store it in a secure location before closing this window.'
    );

    warning.appendChild(warningTitle);
    warning.appendChild(warningText);

    body.appendChild(warning);

    if (credentials.clientId) {
      var clientIdGroup = document.createElement('div');
      clientIdGroup.className = 'customer-credentials-field';

      var clientIdLabel = document.createElement('label');
      clientIdLabel.textContent = Drupal.t('Client ID');

      var clientIdValue = document.createElement('div');
      clientIdValue.className = 'customer-credentials-value';
      clientIdValue.textContent = credentials.clientId;

      clientIdGroup.appendChild(clientIdLabel);
      clientIdGroup.appendChild(clientIdValue);

      body.appendChild(clientIdGroup);
    }

    var hashGroup = document.createElement('div');
    hashGroup.className = 'customer-credentials-field';

    var hashLabel = document.createElement('label');
    hashLabel.textContent = Drupal.t('Hashed Key');

    var hashWrapper = document.createElement('div');
    hashWrapper.className = 'customer-credentials-hash-wrapper';

    var hashValue = document.createElement('code');
    hashValue.className = 'customer-credentials-hash';
    hashValue.textContent = credentials.hashedKey;

    var copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.className = 'customer-credentials-copy';
    copyButton.setAttribute(
      'aria-label',
      Drupal.t('Copy hashed key')
    );
    copyButton.setAttribute(
      'title',
      Drupal.t('Copy hashed key')
    );

    // Simple copy icon.
    copyButton.innerHTML =
      '<span aria-hidden="true">⧉</span>';

    var copyStatusWrapper = document.createElement('div');
    copyStatusWrapper.className = 'customer-credentials-copy-status-wrapper';
    var copyStatus = document.createElement('span');
    copyStatus.className = 'customer-credentials-copy-status';
    copyStatus.setAttribute('aria-live', 'polite');
    copyStatusWrapper.appendChild(copyStatus);

    copyButton.addEventListener('click', function () {
      navigator.clipboard.writeText(credentials.hashedKey)
        .then(function () {
          copyStatus.textContent = Drupal.t('Copied!');
          copyStatusWrapper.style.display = 'block';
          setTimeout(function () {
            copyStatus.textContent = '';
            copyStatusWrapper.style.display = 'none';
          }, 2000);
        })
        .catch(function () {
          copyStatus.textContent = Drupal.t('Unable to copy.');
        });
    });

    hashWrapper.appendChild(hashValue);
    hashWrapper.appendChild(copyButton);

    hashGroup.appendChild(hashLabel);
    hashGroup.appendChild(hashWrapper);
    // hashGroup.appendChild(copyStatus);
    hashGroup.appendChild(copyStatusWrapper);

    body.appendChild(hashGroup);

    var footer = document.createElement('div');
    footer.className = 'customer-credentials-modal-footer';

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'button btn-primary button--primary';
    closeButton.textContent = Drupal.t(
      'I have saved the hashed key'
    );

    footer.appendChild(closeButton);

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    closeButton.addEventListener('click', function () {
      overlay.remove();
    });
  }


  Drupal.behaviors.customerManagementDemandPartners = {
    attach: function (context) {
      once(
        'customer-management-demand-partners',
        '[data-customer-demand-partners-container]',
        context
      ).forEach(function (container) {
        initialize(container);
      });

      once('customer-management-new-credentials', 'body', context).forEach(function () {
        if (drupalSettings.customerManagement && drupalSettings.customerManagement.newCustomerCredentials) {
          showNewCustomerCredentialsModal(drupalSettings.customerManagement.newCustomerCredentials);
        }
      });
    }
  };

})(Drupal, once, drupalSettings);