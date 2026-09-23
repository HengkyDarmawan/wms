/* NexaDash — commerce-customers.html */
(function ($) {
  'use strict';

  var CUSTOMERS = [
    ['Maria Gomez', 'maria@acme.io', 45, '+1 512 555 0142', 18, 9420, true, 'Mar 4, 2024'],
    ['James Lee', 'james@nova.dev', 13, '+1 415 555 0198', 6, 1180, true, 'Jul 22, 2024'],
    ['Chen Wei', 'chen@orbit.app', 25, '+65 8123 4455', 31, 48200, true, 'Jan 9, 2023'],
    ['Amina Diallo', 'amina@pulse.co', 31, '+221 77 555 0113', 12, 3640, false, 'Nov 2, 2024'],
    ['Piotr Nowak', 'piotr@vertex.pl', 57, '+48 601 555 022', 9, 2610, true, 'Feb 18, 2025'],
    ['Hana Sato', 'hana@kumo.jp', 20, '+81 90 5555 8842', 22, 12840, true, 'Aug 30, 2023'],
    ['Lucas Meyer', 'lucas@delta.de', 8, '+49 170 5550 118', 4, 640, false, 'Apr 12, 2025'],
    ['Nadia Rahman', 'nadia@lumen.id', 36, '+62 812 5555 118', 15, 5920, true, 'Jun 7, 2024'],
    ['Olivia Brown', 'olivia@atlas.uk', 44, '+44 7700 900118', 27, 16400, true, 'Oct 15, 2023'],
    ['Diego Alvarez', 'diego@sol.mx', 51, '+52 55 5555 0188', 7, 1920, true, 'May 3, 2025'],
    ['Emma Wilson', 'emma@north.ca', 24, '+1 604 555 0164', 11, 4280, true, 'Dec 1, 2024'],
    ['Yusuf Kaya', 'yusuf@bora.tr', 60, '+90 532 555 0142', 3, 480, false, 'Mar 28, 2025'],
    ['Sofia Rossi', 'sofia@vela.it', 26, '+39 333 555 0177', 19, 8760, true, 'Sep 19, 2023'],
    ['Ravi Menon', 'ravi@indra.in', 52, '+91 98765 55012', 24, 11240, true, 'Feb 2, 2024'],
    ['Ingrid Larsen', 'ingrid@fjord.no', 28, '+47 400 55 118', 8, 2340, true, 'Jul 8, 2025'],
    ['Tom Baker', 'tom@relay.io', 15, '+1 206 555 0139', 5, 890, false, 'Aug 14, 2025']
  ];

  function row(c) {
    return '<tr>' +
      '<td><div class="nx-table-user"><span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + c[2] + '" alt=""></span>' +
      '<div><div class="nx-tu-name">' + c[0] + '</div><div class="nx-tu-sub">' + c[1] + '</div></div></div></td>' +
      '<td class="nx-num small">' + c[3] + '</td>' +
      '<td class="nx-num">' + c[4] + '</td>' +
      '<td class="nx-num fw-semibold">$' + c[5].toLocaleString() + '</td>' +
      '<td><div class="form-check form-switch mb-0"><input class="form-check-input status-switch" type="checkbox" role="switch" ' + (c[6] ? 'checked' : '') + ' aria-label="Active status">' +
      '<span class="small ms-1 status-text">' + (c[6] ? 'Active' : 'Inactive') + '</span></div></td>' +
      '<td class="small text-muted">' + c[7] + '</td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Row actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="page-profile.html"><i class="bi bi-person"></i>View profile</a>' +
      '<a class="dropdown-item" href="apps-mail.html"><i class="bi bi-envelope"></i>Send email</a>' +
      '<button class="dropdown-item text-danger btn-del" type="button"><i class="bi bi-trash"></i>Delete</button></div></div></td></tr>';
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#customersBody').html(CUSTOMERS.map(row).join(''));

    var dt = new DataTable('#customersDT', {
      pageLength: 10,
      columnDefs: [{ orderable: false, targets: [4, 6] }],
      layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' }
    });

    $('#customersDT').on('change', '.status-switch', function () {
      $(this).siblings('.status-text').text(this.checked ? 'Active' : 'Inactive');
    });

    $('#customersDT').on('click', '.btn-del', function () {
      var row = $(this).closest('tr');
      Swal.fire({ title: 'Delete this customer?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) { if (r.isConfirmed) dt.row(row).remove().draw(); });
    });
  });
})(jQuery);
