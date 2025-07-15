(function ($, drupalSettings) {
  // change currency symbol based on currency
  $('#edit-currencies').change(function() {
    location.href = location.origin + location.pathname + '?cur=' + $(this).val();
  });

  // limit the users to select only 2.
  $('.users-check').change(function() {
    if ($('.users-check:checked').length == 2){
      $(".users-check:not(:checked)").attr("disabled", true);
    }else {
      $(".users-check:not(:checked)").removeAttr('disabled');
    }
  });
  
})(jQuery, drupalSettings);