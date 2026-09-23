/* NexaDash — page-activity.html */
(function ($) {
  'use strict';

  function apply() {
    var types = $('.act-filter:checked').map(function () { return this.value; }).get();
    var q = ($('#actSearch').val() || '').toLowerCase();
    $('.act-item').each(function () {
      var ok = types.indexOf($(this).data('type')) > -1 &&
        (!q || $(this).text().toLowerCase().indexOf(q) > -1);
      $(this).toggle(ok);
    });
    // Sembunyikan header hari tanpa item.
    $('.nx-activity-day').each(function () {
      var $list = $(this).next('.nx-timeline');
      var any = $list.find('.act-item:visible').length > 0;
      $(this).toggle(any);
      $list.toggle(any);
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    $('.act-filter').on('change', apply);
    $('#actSearch').on('input', apply);
  });
})(jQuery);
