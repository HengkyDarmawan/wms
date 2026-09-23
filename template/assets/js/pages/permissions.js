/* NexaDash — pages/permissions.html */
(function ($) {
  'use strict';

  var ROLES = ['Owner', 'Admin', 'Editor', 'Analyst', 'Support', 'Viewer'];

  /* Tiap baris: [grup, nama, deskripsi, izin per peran sesuai urutan ROLES] */
  var MATRIX = [
    ['Dashboards', 'View dashboards', 'See any published dashboard', [1, 1, 1, 1, 1, 1]],
    ['Dashboards', 'Create dashboards', 'Add new dashboards to the workspace', [1, 1, 1, 1, 0, 0]],
    ['Dashboards', 'Edit any dashboard', 'Change dashboards owned by other people', [1, 1, 0, 0, 0, 0]],
    ['Dashboards', 'Delete dashboards', 'Permanently remove a dashboard', [1, 1, 0, 0, 0, 0]],
    ['Dashboards', 'Export data', 'Download CSV, XLSX or PDF exports', [1, 1, 1, 1, 1, 0]],

    ['Commerce', 'View orders', 'Open the order list and details', [1, 1, 1, 1, 1, 0]],
    ['Commerce', 'Edit orders', 'Change status, items or addresses', [1, 1, 1, 0, 1, 0]],
    ['Commerce', 'Issue refunds', 'Return money to a customer', [1, 1, 0, 0, 0, 0]],
    ['Commerce', 'Manage products', 'Create, edit and archive products', [1, 1, 1, 0, 0, 0]],
    ['Commerce', 'Moderate reviews', 'Publish, flag or delete customer reviews', [1, 1, 1, 0, 1, 0]],

    ['Users', 'View members', 'See who belongs to the workspace', [1, 1, 1, 1, 1, 1]],
    ['Users', 'Invite members', 'Send invitations to new people', [1, 1, 0, 0, 0, 0]],
    ['Users', 'Assign roles', 'Change what other people can do', [1, 1, 0, 0, 0, 0]],
    ['Users', 'Remove members', 'Revoke workspace access', [1, 0, 0, 0, 0, 0]],

    ['Billing', 'View invoices', 'Open billing history and receipts', [1, 0, 0, 0, 0, 0]],
    ['Billing', 'Change plan', 'Upgrade, downgrade or cancel', [1, 0, 0, 0, 0, 0]],
    ['Billing', 'Manage payment methods', 'Add or remove cards and bank details', [1, 0, 0, 0, 0, 0]],

    ['Settings', 'Edit workspace settings', 'Name, branding, regional defaults', [1, 1, 0, 0, 0, 0]],
    ['Settings', 'Manage integrations', 'Connect or disconnect third-party accounts', [1, 1, 0, 0, 0, 0]],
    ['Settings', 'View audit log', 'Read the record of every write action', [1, 1, 0, 1, 0, 0]],

    ['API', 'View API keys', 'See which keys exist and when they were used', [1, 1, 0, 0, 0, 0]],
    ['API', 'Rotate API keys', 'Regenerate a key, invalidating the old one', [1, 1, 0, 0, 0, 0]],
    ['API', 'Manage webhooks', 'Add endpoints and choose which events fire', [1, 1, 1, 0, 0, 0]]
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function render() {
    var html = '';
    var group = null;
    MATRIX.forEach(function (row, i) {
      if (row[0] !== group) {
        group = row[0];
        html += '<tr class="pm-group"><td colspan="7" class="fw-semibold" style="background:var(--nx-body-bg)">' + esc(group) + '</td></tr>';
      }
      html += '<tr class="pm-row" data-i="' + i + '"><td><div class="fw-semibold">' + esc(row[1]) + '</div>' +
        '<div class="small text-muted">' + esc(row[2]) + '</div></td>' +
        row[3].map(function (v, c) {
          var owner = c === 0;
          return '<td class="text-center" data-role="' + ROLES[c] + '">' +
            (owner
              ? '<i class="bi bi-lock text-muted" title="Owners always have every permission"></i>'
              : '<input class="form-check-input pm-check" type="checkbox"' + (v ? ' checked' : '') +
                ' data-role="' + ROLES[c] + '" aria-label="' + esc(row[1]) + ' for ' + ROLES[c] + '">') +
            '</td>';
        }).join('') + '</tr>';
    });
    $('#pmBody').html(html);
    count();
  }

  function count() {
    $('#pmGranted').text($('.pm-check:checked').length + 22);
    $('#pmTotal').text($('.pm-check').length + 22);
  }

  document.addEventListener('nx:layout-ready', function () {
    render();

    $('#pmSearch').on('input', function () {
      var q = this.value.toLowerCase();
      $('.pm-row').each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
      });
      // Sembunyikan header grup yang tidak lagi punya baris terlihat.
      $('.pm-group').each(function () {
        $(this).toggle($(this).nextUntil('.pm-group', '.pm-row').filter(':visible').length > 0);
      });
    });

    $('#pmRole').on('change', function () {
      var role = this.value;
      $('#pmTable td[data-role], #pmTable th').css('background', '');
      if (!role) return;
      var idx = ROLES.indexOf(role) + 1;
      $('#pmTable tr').each(function () {
        $(this).children().eq(idx).css('background', 'var(--nx-primary-subtle)');
      });
    });

    $('#pmBody').on('change', '.pm-check', function () {
      count();
      $('#pmSave').prop('disabled', false);
    });

    $('#pmReset').on('click', function () {
      render();
      $('#pmSave').prop('disabled', true);
      $('#pmSearch').val('').trigger('input');
      $('#pmRole').val('').trigger('change');
    });

    $('#pmSave').on('click', function () {
      var $b = $(this);
      $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving&hellip;');
      setTimeout(function () {
        $b.html('<i class="bi bi-check2 me-1"></i>Save matrix');
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Permission matrix saved', showConfirmButton: false, timer: 1800 });
      }, 1100);
    });
  });
})(jQuery);
