/* NexaDash — page-users.html */
(function ($) {
  'use strict';

  var USERS = [
    ['Sarah Chen', 'sarah@nexa.io', 47, 'Admin', 'danger', 'Product', 'Active', 'online', '2 minutes ago'],
    ['Marcus Webb', 'marcus@nexa.io', 68, 'Editor', 'warning', 'Engineering', 'Active', 'online', '26 minutes ago'],
    ['Lena Iversen', 'lena@nexa.io', 32, 'Editor', 'warning', 'Design', 'Active', 'away', '1 hour ago'],
    ['Tom Baker', 'tom@nexa.io', 15, 'Viewer', 'secondary', 'Support', 'Active', 'offline', '3 hours ago'],
    ['Ava Novak', 'ava@nexa.io', 5, 'Editor', 'warning', 'Design', 'Active', 'online', '5 hours ago'],
    ['Chen Wei', 'chen@nexa.io', 25, 'Admin', 'danger', 'Engineering', 'Active', 'offline', 'Yesterday'],
    ['Amina Diallo', 'amina@nexa.io', 31, 'Viewer', 'secondary', 'Sales', 'Pending', 'offline', 'Never'],
    ['Piotr Nowak', 'piotr@nexa.io', 57, 'Editor', 'warning', 'Data', 'Active', 'offline', 'Yesterday'],
    ['Hana Sato', 'hana@nexa.io', 20, 'Viewer', 'secondary', 'Support', 'Active', 'away', '2 days ago'],
    ['Lucas Meyer', 'lucas@nexa.io', 8, 'Viewer', 'secondary', 'Sales', 'Suspended', 'offline', '2 weeks ago'],
    ['Nadia Rahman', 'nadia@nexa.io', 36, 'Editor', 'warning', 'Product', 'Active', 'online', '4 hours ago'],
    ['Olivia Brown', 'olivia@nexa.io', 44, 'Admin', 'danger', 'Engineering', 'Active', 'offline', '3 days ago'],
    ['Diego Alvarez', 'diego@nexa.io', 51, 'Viewer', 'secondary', 'Support', 'Pending', 'offline', 'Never'],
    ['Emma Wilson', 'emma@nexa.io', 24, 'Editor', 'warning', 'Data', 'Active', 'away', '6 hours ago'],
    ['Yusuf Kaya', 'yusuf@nexa.io', 60, 'Viewer', 'secondary', 'Sales', 'Suspended', 'offline', '1 month ago'],
    ['Sofia Rossi', 'sofia@nexa.io', 26, 'Editor', 'warning', 'Design', 'Active', 'online', '30 minutes ago'],
    ['Ravi Menon', 'ravi@nexa.io', 52, 'Editor', 'warning', 'Engineering', 'Active', 'offline', 'Yesterday'],
    ['Ingrid Larsen', 'ingrid@nexa.io', 28, 'Viewer', 'secondary', 'Product', 'Pending', 'offline', 'Never']
  ];

  var STATUS_COLOR = { Active: 'success', Pending: 'warning', Suspended: 'danger' };

  function row(u) {
    return '<tr>' +
      '<td><div class="nx-table-user"><span class="nx-avatar-status ' + u[7] + '"><span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + u[2] + '" alt=""></span></span>' +
      '<div><div class="nx-tu-name">' + u[0] + '</div><div class="nx-tu-sub">' + u[1] + '</div></div></div></td>' +
      '<td><span class="badge badge-soft-' + u[4] + '">' + u[3] + '</span></td>' +
      '<td>' + u[5] + '</td>' +
      '<td><span class="badge badge-soft-' + STATUS_COLOR[u[6]] + '">' + u[6] + '</span></td>' +
      '<td class="small text-muted">' + u[8] + '</td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Row actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end">' +
      '<a class="dropdown-item" href="page-profile.html"><i class="bi bi-person"></i>View profile</a>' +
      '<button class="dropdown-item" type="button"><i class="bi bi-shield"></i>Change role</button>' +
      '<button class="dropdown-item" type="button"><i class="bi bi-envelope"></i>Resend invite</button>' +
      '<div class="dropdown-divider"></div>' +
      '<button class="dropdown-item text-danger btn-remove" type="button"><i class="bi bi-person-dash"></i>Remove user</button>' +
      '</div></div></td></tr>';
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#usersBody').html(USERS.map(row).join(''));

    var dt = new DataTable('#usersDT', {
      pageLength: 10,
      columnDefs: [{ orderable: false, targets: 5 }],
      layout: { topStart: 'pageLength', topEnd: { search: { placeholder: 'Search users…' } }, bottomStart: 'info', bottomEnd: 'paging' }
    });

    $('#invRole, #invDept').select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#inviteModal'), minimumResultsForSearch: -1 });

    $('#btnSendInvite').on('click', function () {
      var email = $('#invEmail').val().trim();
      if (!email) { $('#invEmail').addClass('is-invalid'); return; }
      $('#invEmail').removeClass('is-invalid');
      var role = $('#invRole').val();
      var colors = { Admin: 'danger', Editor: 'warning', Viewer: 'secondary' };
      dt.row.add($(row([email.split('@')[0], email, 30, role, colors[role], $('#invDept').val(), 'Pending', 'offline', 'Never']))).draw();
      bootstrap.Modal.getInstance(document.getElementById('inviteModal')).hide();
      $('#invEmail').val('');
      Swal.fire({ icon: 'success', title: 'Invitation sent', text: email, timer: 1800, showConfirmButton: false });
    });

    $('#usersDT').on('click', '.btn-remove', function () {
      var r = $(this).closest('tr');
      Swal.fire({
        title: 'Remove this user?', text: 'They lose access to the workspace immediately.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Remove', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (res) { if (res.isConfirmed) dt.row(r).remove().draw(); });
    });
  });
})(jQuery);
