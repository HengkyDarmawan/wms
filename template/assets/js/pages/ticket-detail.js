/* NexaDash — apps/ticket-detail.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {
    var quill = new Quill('#tdEditor', {
      theme: 'snow',
      placeholder: 'Write your reply…',
      modules: { toolbar: [['bold', 'italic'], [{ list: 'bullet' }, { list: 'ordered' }], ['link', 'code-block'], ['clean']] }
    });

    function appendMessage(internal) {
      var text = quill.getText().trim();
      if (!text) { Swal.fire({ icon: 'info', title: 'Write something first', timer: 1400, showConfirmButton: false }); return; }
      var bg = internal ? 'var(--nx-warning-subtle)' : 'var(--nx-primary-subtle)';
      var tag = internal
        ? '<span class="badge badge-soft-warning">Internal note</span>'
        : '<span class="badge badge-soft-primary">Agent</span>';
      var $item = $('<li class="nx-timeline-item">' +
        '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
        '<div class="nx-tl-body"><div class="d-flex align-items-center gap-2"><strong>Aigars S.</strong>' + tag +
        '<span class="nx-tl-time ms-auto">Just now</span></div>' +
        '<div class="p-3 rounded mt-2 msg" style="background:' + bg + '"></div></div></li>');
      $item.find('.msg').text(text);
      $('#tdThread').append($item);
      quill.setText('');
      if (!internal) $('#tdStatus').attr('class', 'badge badge-soft-primary').text('Waiting on customer');
    }

    $('#tdSend').on('click', function () { appendMessage(false); });
    $('#tdNote').on('click', function () { appendMessage(true); });

    $('#tdResolve').on('click', function () {
      Swal.fire({
        title: 'Resolve this ticket?',
        text: 'The customer will be asked to rate the conversation.',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Resolve', confirmButtonColor: nxCss('--nx-success')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        $('#tdStatus').attr('class', 'badge badge-soft-success').text('Resolved');
        $('#tdThread').append('<li class="nx-timeline-item">' +
          '<span class="nx-tl-dot" style="color:var(--nx-success);background:var(--nx-success-subtle)"><i class="bi bi-check-lg"></i></span>' +
          '<div class="nx-tl-body"><div class="nx-tl-text"><strong>Aigars S.</strong> resolved this ticket</div>' +
          '<div class="nx-tl-time">Just now</div></div></li>');
      });
    });

    $('#tdAssignee').on('change', function () {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Assigned to ' + this.value, showConfirmButton: false, timer: 1800 });
    });
  });
})(jQuery);
