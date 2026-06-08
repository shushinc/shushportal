(function (Drupal, once) {
  'use strict';

  function createRejectModal() {
    var overlay = document.createElement('div');
    overlay.className = 'rate-sheet-reject-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'reject-modal-title');

    var modal = document.createElement('div');
    modal.className = 'rate-sheet-reject-modal';

    var header = document.createElement('div');
    header.className = 'rate-sheet-reject-modal-header';

    var title = document.createElement('h2');
    title.id = 'reject-modal-title';
    title.textContent = 'Reject Rate Sheet';
    header.appendChild(title);

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'rate-sheet-reject-modal-close';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.innerHTML = '&times;';
    header.appendChild(closeButton);

    var body = document.createElement('div');
    body.className = 'rate-sheet-reject-modal-body';

    var label = document.createElement('label');
    label.htmlFor = 'reject-comment';
    label.textContent = 'Please provide a reason for rejecting this rate sheet:';
    label.className = 'rate-sheet-reject-modal-label';

    var textarea = document.createElement('textarea');
    textarea.id = 'reject-comment';
    textarea.name = 'reject_comment';
    textarea.className = 'rate-sheet-reject-modal-textarea';
    textarea.rows = 6;
    textarea.required = true;
    textarea.setAttribute('aria-required', 'true');

    var errorMessage = document.createElement('div');
    errorMessage.className = 'rate-sheet-reject-modal-error';
    errorMessage.hidden = true;
    errorMessage.setAttribute('role', 'alert');
    errorMessage.textContent = 'Please provide a reason for rejection.';

    body.appendChild(label);
    body.appendChild(textarea);
    body.appendChild(errorMessage);

    var footer = document.createElement('div');
    footer.className = 'rate-sheet-reject-modal-footer';

    var cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'rate-sheet-reject-modal-cancel';
    cancelButton.textContent = 'Cancel';

    var submitButton = document.createElement('button');
    submitButton.type = 'button';
    submitButton.className = 'rate-sheet-reject-modal-submit';
    submitButton.textContent = 'Submit Rejection';

    footer.appendChild(cancelButton);
    footer.appendChild(submitButton);

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);

    return {
      overlay: overlay,
      modal: modal,
      textarea: textarea,
      errorMessage: errorMessage,
      closeButton: closeButton,
      cancelButton: cancelButton,
      submitButton: submitButton
    };
  }

  function showRejectModal(form, submitButton) {
    var modalElements = createRejectModal();
    document.body.appendChild(modalElements.overlay);

    modalElements.textarea.focus();

    function closeModal() {
      if (modalElements.overlay.parentNode) {
        document.body.removeChild(modalElements.overlay);
      }
    }

    function validateAndSubmit() {
      var comment = modalElements.textarea.value.trim();

      if (!comment) {
        modalElements.errorMessage.hidden = false;
        modalElements.textarea.focus();
        modalElements.textarea.setAttribute('aria-invalid', 'true');
        return;
      }

      // Find the hidden reject_comment field that Drupal created
      var rejectCommentField = form.querySelector('input[data-reject-comment-field]');
      if (!rejectCommentField) {
        // Fallback: try to find by name
        rejectCommentField = form.querySelector('input[name="reject_comment"]');
      }

      if (rejectCommentField) {
        rejectCommentField.value = comment;
      } else {
        // Last resort: create the input
        var commentInput = document.createElement('input');
        commentInput.type = 'hidden';
        commentInput.name = 'reject_comment';
        commentInput.value = comment;
        form.appendChild(commentInput);
      }

      closeModal();

      // Mark that we're submitting to prevent re-triggering the modal
      submitButton.setAttribute('data-reject-modal-submitting', 'true');

      // Submit the form directly
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    }

    modalElements.closeButton.addEventListener('click', closeModal);
    modalElements.cancelButton.addEventListener('click', closeModal);
    modalElements.submitButton.addEventListener('click', validateAndSubmit);

    modalElements.textarea.addEventListener('input', function () {
      if (modalElements.textarea.value.trim()) {
        modalElements.errorMessage.hidden = true;
        modalElements.textarea.removeAttribute('aria-invalid');
      }
    });

    modalElements.overlay.addEventListener('click', function (event) {
      if (event.target === modalElements.overlay) {
        closeModal();
      }
    });

    modalElements.textarea.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeModal();
      }
    });
  }

  Drupal.behaviors.rateSheetRejectModal = {
    attach: function (context) {
      once('rate-sheet-reject-modal', '.rate-sheet-review-submit', context).forEach(function (submitButton) {
        var form = submitButton.closest('form');
        var statusSelect = form ? form.querySelector('.rate-sheet-review-status-select') : null;

        if (!form || !statusSelect) {
          return;
        }

        submitButton.addEventListener('click', function (event) {
          // Check if we're already submitting (after modal was filled)
          if (submitButton.getAttribute('data-reject-modal-submitting') === 'true') {
            submitButton.removeAttribute('data-reject-modal-submitting');
            return;
          }

          var selectedStatus = statusSelect.value;

          // Status 3 = Reject
          if (selectedStatus === '3') {
            event.preventDefault();
            event.stopPropagation();
            showRejectModal(form, submitButton);
          }
        });
      });
    }
  };

})(Drupal, once);
