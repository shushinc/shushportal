(function ($, drupalSettings) {
    $('.select-client').change(function () {
      var $url = location.origin + location.pathname
      if ($(this).val() > 0) {
          $url += '?client=' + $(this).val();
      }
      location.href = $url;
    });
  })(jQuery, drupalSettings);
  