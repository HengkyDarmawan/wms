/* NexaDash — commerce-orders.html */
(function ($) {
  'use strict';

  var dt = null;

  function rowHtml(o) {
    return '<tr data-status="' + o.status + '">' +
      '<td><input class="form-check-input row-check" type="checkbox" aria-label="Select order"></td>' +
      '<td><a href="#" class="nx-link open-detail">#' + o.id + '</a></td>' +
      '<td><div class="nx-table-user"><span class="nx-avatar nx-avatar-md"><img src="https://i.pravatar.cc/56?img=' + o.avatar + '" alt=""></span>' +
      '<div><div class="nx-tu-name">' + o.name + '</div><div class="nx-tu-sub">' + o.email + '</div></div></div></td>' +
      '<td>' + o.product + '</td>' +
      '<td>' + o.date + '</td>' +
      '<td class="nx-num fw-semibold">$' + o.amount.toLocaleString('en-US', { minimumFractionDigits: 2 }) + '</td>' +
      '<td class="small text-muted">' + o.payment + '</td>' +
      '<td><span class="badge badge-soft-' + o.statusColor + '">' + o.status + '</span></td>' +
      '<td class="text-end"><div class="dropdown"><button class="btn btn-icon btn-sm btn-soft-secondary" data-bs-toggle="dropdown" aria-label="Row actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><button class="dropdown-item open-detail" type="button"><i class="bi bi-eye"></i>View</button>' +
      '<a class="dropdown-item" href="commerce-invoice-detail.html"><i class="bi bi-file-earmark-text"></i>Invoice</a>' +
      '<button class="dropdown-item text-danger btn-del" type="button"><i class="bi bi-trash"></i>Delete</button></div></div></td></tr>';
  }

  function updateBulk() {
    var n = $('.row-check:checked').length;
    $('#bulkCount').text(n + ' selected');
    $('#bulkBar').toggleClass('show', n > 0);
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#ordersBody').html(window.NX_ORDERS.map(rowHtml).join(''));

    dt = new DataTable('#ordersDT', {
      pageLength: 10,
      order: [[1, 'desc']],
      columnDefs: [{ orderable: false, targets: [0, 8] }],
      layout: {
        topStart: 'pageLength',
        topEnd: { buttons: ['copy', 'csv', 'print', 'colvis'] },
        bottomStart: 'info',
        bottomEnd: 'paging'
      }
    });

    flatpickr('#fltDate', { mode: 'range', dateFormat: 'M j, Y' });

    $('#fltSearch').on('input', function () { dt.search(this.value).draw(); });
    $('#fltStatus').on('change', function () { dt.column(7).search(this.value).draw(); });
    $('#fltReset').on('click', function () {
      $('#fltSearch').val(''); $('#fltStatus').val(''); $('#fltDate').val('');
      dt.search('').columns().search('').draw();
    });

    $('#checkAll').on('change', function () {
      $('.row-check').prop('checked', this.checked);
      updateBulk();
    });
    $('#ordersDT').on('change', '.row-check', updateBulk);

    $('#bulkDelete').on('click', function () {
      Swal.fire({
        title: 'Delete selected orders?', text: 'This cannot be undone.', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        dt.rows($('.row-check:checked').closest('tr')).remove().draw();
        $('#checkAll').prop('checked', false);
        updateBulk();
      });
    });

    $('#ordersDT').on('click', '.btn-del', function () {
      var row = $(this).closest('tr');
      Swal.fire({ title: 'Delete this order?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) { if (r.isConfirmed) dt.row(row).remove().draw(); });
    });

    $('#ordersDT').on('click', '.open-detail', function (e) {
      e.preventDefault();
      var id = $(this).closest('tr').find('td:eq(1)').text().trim();
      $('#orderDetailTitle').text('Order ' + id);
      new bootstrap.Offcanvas(document.getElementById('orderDetail')).show();
    });
  });
})(jQuery);
