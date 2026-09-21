(function (Drupal, once) {
  'use strict';

  function initDemandPartnersFilter(wrapper) {
    var input = wrapper.querySelector('[data-customer-demand-partners-filter]');
    var select = wrapper.querySelector('[data-customer-demand-partners-select]');

    if (!input || !select) {
      return;
    }

    input.addEventListener('input', function () {
      var query = input.value.trim().toLowerCase();
      var options = Array.prototype.slice.call(select.options);

      options.forEach(function (option) {
        var label = (option.text || '').toLowerCase();
        var matched = !query || label.indexOf(query) !== -1;

        option.hidden = !matched;

        if (!matched && option.selected) {
          option.hidden = false;
        }
      });
    });
  }

  Drupal.behaviors.customerManagementForm = {
    attach: function (context) {
      once('customer-management-demand-partners-filter', '.customer-management-demand-partners-section', context).forEach(initDemandPartnersFilter);
    }
  };
})(Drupal, once);
