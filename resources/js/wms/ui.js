/* ============================================================
   Perilaku kecil yang di template NexaDash hidup per halaman
   (template/assets/js/pages/*.js). Memakai delegasi event supaya
   juga berlaku untuk markup yang dirender ulang Livewire.
   ============================================================ */

(function ($) {
  'use strict';

  // Tampilkan/sembunyikan password — dari template/assets/js/pages/auth.js.
  $(document).on('click', '.btn-toggle-pw', function () {
    var $input = $($(this).data('target'));
    if (!$input.length) return;

    var tampil = $input.attr('type') === 'password';
    $input.attr('type', tampil ? 'text' : 'password');
    $(this)
      .html('<i class="bi ' + (tampil ? 'bi-eye-slash' : 'bi-eye') + '"></i>')
      .attr('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password')
      .attr('aria-pressed', tampil ? 'true' : 'false');
  });
})(window.jQuery);
