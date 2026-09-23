/* NexaDash — ui-toasts-notifications.html */
(function ($) {
  'use strict';

  var VARIANTS = {
    success: ['success', 'bi-check-lg', 'Saved', 'Your changes have been saved.'],
    danger: ['danger', 'bi-x-lg', 'Failed', 'We could not process your request.'],
    warning: ['warning', 'bi-exclamation-triangle', 'Heads up', 'Your trial ends in three days.'],
    info: ['info', 'bi-info-lg', 'Did you know?', 'You can press ⌘K to search anything.']
  };

  function push(html, autohide) {
    var $t = $(html);
    $('#toastContainer').append($t);
    new bootstrap.Toast($t[0], { autohide: autohide !== false, delay: 4000 }).show();
    $t.on('hidden.bs.toast', function () { $t.remove(); });
  }

  function simple(v) {
    var d = VARIANTS[v];
    return '<div class="toast" role="alert" aria-live="assertive" aria-atomic="true"><div class="toast-header">' +
      '<span class="nx-icon-sq ' + d[0] + ' me-2" style="width:24px;height:24px;font-size:13px"><i class="bi ' + d[1] + '"></i></span>' +
      '<strong class="me-auto">' + d[2] + '</strong><small class="text-muted">just now</small>' +
      '<button class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>' +
      '<div class="toast-body">' + d[3] + '</div></div>';
  }

  document.addEventListener('nx:layout-ready', function () {
    $('.btn-pos').on('click', function () {
      $('.btn-pos').removeClass('active');
      $(this).addClass('active');
      $('#toastContainer').attr('class', 'toast-container position-fixed p-3 ' + $(this).data('pos'));
    });

    $('#fireToast').on('click', function () { push(simple('info')); });

    $('.btn-toast').on('click', function () {
      var v = $(this).data('variant');
      if (VARIANTS[v]) return push(simple(v));
      if (v === 'avatar') {
        return push('<div class="toast" role="alert"><div class="toast-body d-flex align-items-center gap-3">' +
          '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=68" alt=""></span>' +
          '<div class="flex-grow-1"><strong class="d-block">Marcus Webb</strong><span class="small text-muted">Requested your review on PR #482</span></div>' +
          '<button class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div></div>');
      }
      if (v === 'action') {
        return push('<div class="toast" role="alert"><div class="toast-body">' +
          '<p class="mb-2">The item was moved to trash.</p>' +
          '<div class="d-flex gap-2"><button class="btn btn-sm btn-primary" type="button">Undo</button>' +
          '<button class="btn btn-sm btn-soft-secondary" data-bs-dismiss="toast" type="button">Dismiss</button></div></div></div>');
      }
      if (v === 'manual') {
        return push('<div class="toast" role="alert"><div class="toast-header">' +
          '<span class="nx-icon-sq secondary me-2" style="width:24px;height:24px;font-size:13px"><i class="bi bi-pin-angle"></i></span>' +
          '<strong class="me-auto">Sticky toast</strong><button class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>' +
          '<div class="toast-body">This one stays until you close it.</div></div>', false);
      }
    });

    $('#swSuccess').on('click', function () {
      Swal.fire({ icon: 'success', title: 'All done', text: 'Your workspace has been created.', confirmButtonColor: nxCss('--nx-primary') });
    });
    $('#swError').on('click', function () {
      Swal.fire({ icon: 'error', title: 'Something went wrong', text: 'The server returned a 500 error.', confirmButtonColor: nxCss('--nx-primary') });
    });
    $('#swConfirm').on('click', function () {
      Swal.fire({
        title: 'Delete this project?', text: 'All 42 tasks inside it will be removed.', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Deleted', timer: 1300, showConfirmButton: false }); });
    });
    $('#swInput').on('click', function () {
      Swal.fire({
        title: 'Rename workspace', input: 'text', inputValue: 'Nexa Product',
        showCancelButton: true, confirmButtonText: 'Rename', confirmButtonColor: nxCss('--nx-primary')
      }).then(function (r) { if (r.isConfirmed && r.value) Swal.fire({ icon: 'success', title: 'Renamed to ' + r.value, timer: 1500, showConfirmButton: false }); });
    });
    $('#swToast').on('click', function () {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied to clipboard', showConfirmButton: false, timer: 2200 });
    });
  });
})(jQuery);
