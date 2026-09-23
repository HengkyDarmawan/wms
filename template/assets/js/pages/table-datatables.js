/* NexaDash — table-datatables.html */
(function ($) {
  'use strict';

  var NAMES = ['Sarah Chen', 'Marcus Webb', 'Lena Iversen', 'Tom Baker', 'Ava Novak', 'Chen Wei', 'Amina Diallo',
    'Piotr Nowak', 'Hana Sato', 'Lucas Meyer', 'Nadia Rahman', 'Olivia Brown', 'Diego Alvarez', 'Emma Wilson',
    'Yusuf Kaya', 'Sofia Rossi', 'Ravi Menon', 'Ingrid Larsen', 'James Lee', 'Maria Gomez'];
  var POSITIONS = ['Product Manager', 'Staff Engineer', 'Design Lead', 'Support Lead', 'Data Analyst', 'Account Executive', 'QA Engineer', 'DevOps Engineer'];
  var DEPTS = ['Product', 'Engineering', 'Design', 'Support', 'Data', 'Sales'];
  var CITIES = ['Singapore', 'Berlin', 'Austin', 'Tokyo', 'Jakarta', 'London', 'Toronto', 'São Paulo'];
  var STATUS = [['Active', 'success'], ['On leave', 'warning'], ['Probation', 'info'], ['Contract', 'secondary']];

  function rows() {
    var out = '';
    for (var i = 0; i < 40; i++) {
      var st = STATUS[i % 4];
      var salary = 62000 + (i % 9) * 8400;
      var year = 2019 + (i % 7);
      out += '<tr>' +
        '<td><input class="form-check-input dt-check" type="checkbox" aria-label="Select row"></td>' +
        '<td><div class="nx-table-user"><span class="nx-avatar nx-avatar-md"><img src="https://i.pravatar.cc/56?img=' + ((i * 7) % 70 + 1) + '" alt=""></span>' +
        '<div><div class="nx-tu-name">' + NAMES[i % NAMES.length] + '</div>' +
        '<div class="nx-tu-sub">' + NAMES[i % NAMES.length].toLowerCase().replace(/[^a-z]/g, '.') + '@nexa.io</div></div></div></td>' +
        '<td>' + POSITIONS[i % POSITIONS.length] + '</td>' +
        '<td>' + DEPTS[i % DEPTS.length] + '</td>' +
        '<td>' + CITIES[i % CITIES.length] + '</td>' +
        '<td class="small text-muted">' + ['Jan', 'Mar', 'May', 'Jul', 'Sep', 'Nov'][i % 6] + ' ' + (i % 28 + 1) + ', ' + year + '</td>' +
        '<td class="nx-num fw-semibold">$' + salary.toLocaleString() + '</td>' +
        '<td><span class="badge badge-soft-' + st[1] + '">' + st[0] + '</span></td></tr>';
    }
    return out;
  }

  function updateBulk() {
    var n = $('.dt-check:checked').length;
    $('#dtBulkCount').text(n + ' selected');
    $('#dtBulkBar').toggleClass('show', n > 0);
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#dtBody').html(rows());

    var dt = new DataTable('#fullDT', {
      pageLength: 10,
      lengthMenu: [5, 10, 25, 50],
      order: [[1, 'asc']],
      columnDefs: [{ orderable: false, targets: 0 }],
      layout: {
        topStart: 'pageLength',
        topEnd: { search: { placeholder: 'Search employees…' } },
        top2End: { buttons: ['copy', 'csv', 'excel', 'print', 'colvis'] },
        bottomStart: 'info',
        bottomEnd: 'paging'
      }
    });

    new DataTable('#miniDT', {
      paging: false,
      info: false,
      layout: { topStart: null, topEnd: { search: { placeholder: 'Filter regions…' } } }
    });

    $('#dtCheckAll').on('change', function () {
      $('.dt-check').prop('checked', this.checked);
      updateBulk();
    });
    $('#fullDT').on('change', '.dt-check', updateBulk);

    $('#dtBulkDelete').on('click', function () {
      Swal.fire({
        title: 'Delete selected employees?', icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        dt.rows($('.dt-check:checked').closest('tr')).remove().draw();
        $('#dtCheckAll').prop('checked', false);
        updateBulk();
      });
    });
  });
})(jQuery);
