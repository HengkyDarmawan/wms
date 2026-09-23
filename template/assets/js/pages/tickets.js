/* NexaDash — apps/tickets.html */
(function ($) {
  'use strict';

  var SUBJECTS = [
    'Billing retry loop on soft declines', 'SSO metadata rejected by IdP', 'CSV export missing rows',
    'Invite email never arrives', 'Timezone wrong on scheduled report', 'Webhook signature mismatch',
    'Dashboard slow on large workspaces', 'Cannot remove a seat', 'API returns 429 unexpectedly',
    'Dark mode contrast on charts', 'Two-factor codes rejected', 'Invoice PDF missing tax line',
    'Kanban card order resets', 'Search does not match partial words', 'Mobile menu traps focus',
    'Deleted user still appears in filters'
  ];
  var CUSTOMERS = [
    ['Chen Wei', 'Orbit Labs', 25], ['Piotr Nowak', 'Vertex GmbH', 57], ['Amina Diallo', 'Pulse Co.', 31],
    ['Hana Sato', 'Kumo Inc.', 20], ['Olivia Brown', 'Atlas UK', 44], ['Maria Gomez', 'Acme IO', 45],
    ['James Lee', 'Nova Dev', 13], ['Nadia Rahman', 'Lumen ID', 36]
  ];
  var AGENTS = [['Tom Baker', 15], ['Hana Sato', 20], ['Maria Gomez', 45], ['Diego Alvarez', 51]];
  var PRIORITY = [['Urgent', 'danger'], ['High', 'warning'], ['Normal', 'secondary'], ['Low', 'info']];
  var STATUS = [['Open', 'primary'], ['Pending', 'warning'], ['Resolved', 'success'], ['Closed', 'secondary']];
  var AGO = ['4m ago', '22m ago', '48m ago', '1h ago', '2h ago', '3h ago', 'Yesterday', '2 days ago'];

  var dt = null;

  function row(i) {
    var c = CUSTOMERS[i % CUSTOMERS.length];
    var a = AGENTS[i % AGENTS.length];
    var p = PRIORITY[i % 4 === 0 ? 0 : (i % 3 === 0 ? 1 : (i % 5 === 0 ? 3 : 2))];
    var s = STATUS[i % 7 === 0 ? 3 : (i % 5 === 0 ? 2 : (i % 3 === 0 ? 1 : 0))];
    return '<tr>' +
      '<td><input class="form-check-input tk-check" type="checkbox" aria-label="Select ticket"></td>' +
      '<td><a class="nx-link" href="apps-ticket-detail.html">#TCK-' + (2041 - i) + '</a>' +
      '<div class="small text-muted text-truncate" style="max-width:260px">' + SUBJECTS[i % SUBJECTS.length] + '</div></td>' +
      '<td><div class="nx-table-user"><span class="nx-avatar nx-avatar-md"><img src="https://i.pravatar.cc/56?img=' + c[2] + '" alt=""></span>' +
      '<div><div class="nx-tu-name">' + c[0] + '</div><div class="nx-tu-sub">' + c[1] + '</div></div></div></td>' +
      '<td><div class="d-flex align-items-center gap-2"><span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=' + a[1] + '" alt=""></span><span class="small">' + a[0] + '</span></div></td>' +
      '<td><span class="badge badge-soft-' + p[1] + '">' + p[0] + '</span></td>' +
      '<td class="small text-muted">' + AGO[i % AGO.length] + '</td>' +
      '<td><span class="badge badge-soft-' + s[1] + ' tk-status">' + s[0] + '</span></td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="apps-ticket-detail.html"><i class="bi bi-eye"></i>Open</a>' +
      '<button class="dropdown-item btn-resolve" type="button"><i class="bi bi-check2"></i>Mark resolved</button>' +
      '<button class="dropdown-item text-danger btn-del-ticket" type="button"><i class="bi bi-trash"></i>Delete</button></div></div></td></tr>';
  }

  function updateBulk() {
    var n = $('.tk-check:checked').length;
    $('#tkBulkCount').text(n + ' selected');
    $('#tkBulkBar').toggleClass('show', n > 0);
  }

  document.addEventListener('nx:layout-ready', function () {
    var html = '';
    for (var i = 0; i < 40; i++) html += row(i);
    $('#ticketsBody').html(html);

    dt = new DataTable('#ticketsDT', {
      pageLength: 10,
      order: [[1, 'desc']],
      columnDefs: [{ orderable: false, targets: [0, 7] }],
      layout: { topStart: 'pageLength', topEnd: null, bottomStart: 'info', bottomEnd: 'paging' }
    });

    $('#tkSearch').on('input', function () { dt.search(this.value).draw(); });
    $('#tkStatus').on('change', function () { dt.column(6).search(this.value).draw(); });
    $('#tkPriority').on('change', function () { dt.column(4).search(this.value).draw(); });
    $('#tkReset').on('click', function () {
      $('#tkSearch').val(''); $('#tkStatus').val(''); $('#tkPriority').val('');
      dt.search('').columns().search('').draw();
    });

    $('#tkCheckAll').on('change', function () {
      $('.tk-check').prop('checked', this.checked);
      updateBulk();
    });
    $('#ticketsDT').on('change', '.tk-check', updateBulk);

    function resolveRow($tr) {
      $tr.find('.tk-status').attr('class', 'badge badge-soft-success tk-status').text('Resolved');
    }
    $('#ticketsDT').on('click', '.btn-resolve', function () { resolveRow($(this).closest('tr')); });

    $('#tkBulkResolve').on('click', function () {
      $('.tk-check:checked').closest('tr').each(function () { resolveRow($(this)); });
      $('.tk-check, #tkCheckAll').prop('checked', false);
      updateBulk();
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tickets resolved', showConfirmButton: false, timer: 1800 });
    });

    $('#tkBulkDelete').on('click', function () {
      Swal.fire({ title: 'Delete selected tickets?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) {
          if (!r.isConfirmed) return;
          dt.rows($('.tk-check:checked').closest('tr')).remove().draw();
          $('#tkCheckAll').prop('checked', false);
          updateBulk();
        });
    });

    $('#ticketsDT').on('click', '.btn-del-ticket', function () {
      var r = $(this).closest('tr');
      Swal.fire({ title: 'Delete this ticket?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (res) { if (res.isConfirmed) dt.row(r).remove().draw(); });
    });
  });
})(jQuery);
