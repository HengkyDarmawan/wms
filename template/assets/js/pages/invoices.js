/* NexaDash — commerce-invoices.html */
(function ($) {
  'use strict';

  var CLIENTS = [
    ['Orbit Labs', 'chen@orbit.app', 25], ['Vertex GmbH', 'piotr@vertex.pl', 57],
    ['Kumo Inc.', 'hana@kumo.jp', 20], ['Pulse Co.', 'amina@pulse.co', 31],
    ['Lumen ID', 'nadia@lumen.id', 36], ['Atlas UK', 'olivia@atlas.uk', 44],
    ['Nova Dev', 'james@nova.dev', 13], ['Acme IO', 'maria@acme.io', 45]
  ];
  var STATUS = [['Paid', 'success'], ['Due', 'warning'], ['Overdue', 'danger'], ['Draft', 'secondary']];

  function row(i) {
    var c = CLIENTS[i % CLIENTS.length];
    var st = STATUS[i % 9 === 0 ? 3 : (i % 7 === 0 ? 2 : (i % 3 === 0 ? 1 : 0))];
    var amount = [2400, 588, 1290, 4820, 960, 12400, 340, 7600][i % 8];
    var issued = 'Sep ' + Math.max(1, 11 - i) + ', 2026';
    var due = 'Sep ' + Math.min(30, 25 - i + 5) + ', 2026';
    return '<tr><td><a href="commerce-invoice-detail.html" class="nx-link">#INV-2026-' + (42 - i < 10 ? '00' : '0') + (42 - i) + '</a></td>' +
      '<td><div class="nx-table-user"><span class="nx-avatar nx-avatar-md"><img src="https://i.pravatar.cc/56?img=' + c[2] + '" alt=""></span>' +
      '<div><div class="nx-tu-name">' + c[0] + '</div><div class="nx-tu-sub">' + c[1] + '</div></div></div></td>' +
      '<td class="small">' + issued + '</td><td class="small">' + due + '</td>' +
      '<td class="nx-num fw-semibold">$' + amount.toLocaleString() + '.00</td>' +
      '<td><span class="badge badge-soft-' + st[1] + '">' + st[0] + '</span></td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Row actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="commerce-invoice-detail.html"><i class="bi bi-eye"></i>View</a>' +
      '<a class="dropdown-item" href="commerce-invoice-detail.html"><i class="bi bi-download"></i>Download</a>' +
      '<a class="dropdown-item" href="apps-mail.html"><i class="bi bi-send"></i>Send reminder</a>' +
      '<button class="dropdown-item text-danger btn-del" type="button"><i class="bi bi-trash"></i>Delete</button></div></div></td></tr>';
  }

  document.addEventListener('nx:layout-ready', function () {
    var rows = '';
    for (var i = 0; i < 24; i++) rows += row(i);
    $('#invoicesBody').html(rows);

    var dt = new DataTable('#invoicesDT', {
      pageLength: 10,
      columnDefs: [{ orderable: false, targets: [6] }],
      layout: { topStart: 'pageLength', topEnd: { buttons: ['csv', 'print'] }, bottomStart: 'info', bottomEnd: 'paging' }
    });

    $('#invoicesDT').on('click', '.btn-del', function () {
      var r = $(this).closest('tr');
      Swal.fire({ title: 'Delete this invoice?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (res) { if (res.isConfirmed) dt.row(r).remove().draw(); });
    });
  });
})(jQuery);
