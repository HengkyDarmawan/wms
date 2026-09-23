/* NexaDash — commerce-invoice-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('#btnPrint').on('click', function () { window.print(); });
    $('#btnDownload').on('click', function () {
      Swal.fire({ icon: 'success', title: 'Invoice downloaded', text: 'INV-2026-0042.pdf', timer: 1600, showConfirmButton: false });
    });
  });
})(jQuery);
