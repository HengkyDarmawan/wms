/* NexaDash — ui-modals.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('#btnSwalConfirm').on('click', function () {
      Swal.fire({
        title: 'Are you sure?',
        text: 'This will archive the project for everyone on the team.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, archive it',
        confirmButtonColor: nxCss('--nx-primary')
      }).then(function (r) {
        if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Archived', timer: 1400, showConfirmButton: false });
      });
    });
  });
})(jQuery);
