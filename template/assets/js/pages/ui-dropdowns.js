/* NexaDash — ui-dropdowns.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('#ddSearch').on('input', function () {
      var q = this.value.toLowerCase();
      $('#ddPeople .dropdown-item').each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
      });
    });
  });
})(jQuery);
