/* NexaDash — apps-mail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    // Editor compose.
    var quill = new Quill('#composeEditor', {
      theme: 'snow',
      placeholder: 'Write your message&hellip;',
      modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'blockquote'], ['clean']] }
    });

    // Pilih email (mark read + isi reading pane).
    $('#mailList').on('click', '.nx-mail-item', function (e) {
      if ($(e.target).is('input, .nx-mi-star')) return;
      $('.nx-mail-item').removeClass('selected');
      $(this).addClass('selected').removeClass('unread');
      $('#readSubject').text($(this).find('.nx-mi-subject').text());
      $('#readFrom').text($(this).find('.nx-mi-from').text());
    });

    // Star toggle.
    $('#mailList').on('click', '.nx-mi-star', function (e) {
      e.stopPropagation();
      $(this).toggleClass('starred bi-star bi-star-fill');
    });

    // Delete.
    $('#btnDeleteMail').on('click', function () {
      Swal.fire({
        title: 'Move to trash?', icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Move to trash', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (r.isConfirmed) $('.nx-mail-item.selected').fadeOut(200, function () { $(this).remove(); });
      });
    });

    // Kirim.
    $('#btnSendMail').on('click', function () {
      bootstrap.Modal.getInstance(document.getElementById('composeModal')).hide();
      quill.setText('');
      Swal.fire({ icon: 'success', title: 'Message sent', timer: 1600, showConfirmButton: false });
    });
  });
})(jQuery);
