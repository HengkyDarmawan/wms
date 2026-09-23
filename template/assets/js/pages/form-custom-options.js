/* NexaDash — forms/custom-options.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('input[name="coPlan"]').on('change', function () { $('#coPlanOut').text(this.value); });
    $('input[name="coRole"]').on('change', function () { $('#coRoleOut').text(this.value); });

    function addons() {
      var total = 0;
      $('.co-addon:checked').each(function () { total += +$(this).data('price'); });
      $('#coAddonTotal').text('$' + total.toLocaleString());
    }
    $('.co-addon').on('change', addons);
    addons();

    // Swatch warna: label perlu status aktif sendiri karena input-nya tersembunyi.
    $('#coSwatches input').on('change', function () {
      $('#coSwatches label').removeClass('active');
      $('label[for="' + this.id + '"]').addClass('active');
    });
  });
})(jQuery);
