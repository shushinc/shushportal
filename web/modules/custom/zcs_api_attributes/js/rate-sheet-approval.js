(function ($, drupalSettings) {
    $('.select-status').change(function() {
      var $url = location.origin + location.pathname
      if ($(this).val() > 0) {
          $url += '?status=' + $(this).val();
      }
      location.href = $url;
    });
  })(jQuery, drupalSettings);