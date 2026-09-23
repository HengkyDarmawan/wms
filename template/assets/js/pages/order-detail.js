/* NexaDash — commerce/order-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('#odNoteAdd').on('click', function () {
      var text = $('#odNoteInput').val().trim();
      if (!text) return;
      var $row = $('<div class="d-flex gap-2 mb-3">' +
        '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
        '<div class="p-2 px-3 rounded flex-grow-1" style="background:var(--nx-body-bg)">' +
        '<div class="small fw-semibold">Aigars S. <span class="text-muted fw-normal">&middot; just now</span></div>' +
        '<div class="small note-text"></div></div></div>');
      $row.find('.note-text').text(text);
      $('#odNotes').append($row);
      $('#odNoteInput').val('');
    });
    $('#odNoteInput').on('keydown', function (e) { if (e.key === 'Enter') $('#odNoteAdd').trigger('click'); });

    $('#odStatus').on('change', function () {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Status set to ' + this.value, showConfirmButton: false, timer: 1800 });
    });

    $('#odRefund').on('click', function () {
      Swal.fire({
        title: 'Issue a refund?',
        input: 'text', inputValue: '514.51', inputLabel: 'Amount in USD',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Refund', confirmButtonColor: nxCss('--nx-warning')
      }).then(function (r) {
        if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Refund issued', text: '$' + r.value, timer: 1800, showConfirmButton: false });
      });
    });

    $('#odResend').on('click', function () {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Confirmation email sent', showConfirmButton: false, timer: 1800 });
    });
    $('#odPrint').on('click', function () { window.print(); });
    $('#odTrack').on('click', function () {
      Swal.fire({ icon: 'info', title: 'NX884201993SG', text: 'Delivered September 11 at 14:02, signed for by M. Gomez.', confirmButtonColor: nxCss('--nx-primary') });
    });
  });
})(jQuery);
