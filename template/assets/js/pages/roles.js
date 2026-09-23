/* NexaDash — pages/roles.html */
(function ($) {
  'use strict';

  var AREAS = ['Dashboards', 'Commerce', 'Users', 'Billing', 'Settings', 'API'];

  var ROLES = [
    { name: 'Owner', color: 'danger', desc: 'Full access including billing and workspace deletion.', users: [12, 47], count: 2, perms: 18 },
    { name: 'Admin', color: 'warning', desc: 'Everything except billing and deleting the workspace.', users: [68, 25, 44], count: 5, perms: 15 },
    { name: 'Editor', color: 'primary', desc: 'Create and edit content; cannot manage people.', users: [32, 5, 36, 52], count: 14, perms: 10 },
    { name: 'Analyst', color: 'info', desc: 'Read everything, export data, build dashboards.', users: [24, 26], count: 9, perms: 8 },
    { name: 'Support', color: 'success', desc: 'Work tickets and view customer records.', users: [15, 20, 45], count: 6, perms: 7 },
    { name: 'Viewer', color: 'secondary', desc: 'Read-only access to published dashboards.', users: [13, 51, 28, 57], count: 12, perms: 3 }
  ];

  var USERS = [
    ['Sarah Chen', 'sarah@nexa.io', 47, 'Owner', 'danger', 'Product', '2 minutes ago'],
    ['Marcus Webb', 'marcus@nexa.io', 68, 'Admin', 'warning', 'Engineering', '26 minutes ago'],
    ['Lena Iversen', 'lena@nexa.io', 32, 'Editor', 'primary', 'Design', '1 hour ago'],
    ['Tom Baker', 'tom@nexa.io', 15, 'Support', 'success', 'Support', '3 hours ago'],
    ['Emma Wilson', 'emma@nexa.io', 24, 'Analyst', 'info', 'Data', '6 hours ago'],
    ['James Lee', 'james@nexa.io', 13, 'Viewer', 'secondary', 'Sales', 'Yesterday'],
    ['Chen Wei', 'chen@nexa.io', 25, 'Admin', 'warning', 'Engineering', 'Yesterday'],
    ['Ava Novak', 'ava@nexa.io', 5, 'Editor', 'primary', 'Design', '5 hours ago']
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function roleCard(r) {
    return '<div class="col-xl-4 col-md-6"><div class="card h-100 nx-card-lift"><div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-start gap-2 mb-2">' +
      '<span class="badge badge-soft-' + r.color + '">' + esc(r.name) + '</span>' +
      '<span class="small text-muted ms-auto">' + r.count + ' user' + (r.count === 1 ? '' : 's') + '</span></div>' +
      '<p class="small text-muted flex-grow-1">' + esc(r.desc) + '</p>' +
      '<div class="d-flex align-items-center gap-2 mb-3">' +
      '<span class="nx-avatar-group">' + r.users.map(function (u) {
        return '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=' + u + '" alt=""></span>';
      }).join('') + (r.count > r.users.length ? '<span class="nx-avatar nx-avatar-sm">+' + (r.count - r.users.length) + '</span>' : '') + '</span>' +
      '<span class="small text-muted ms-auto">' + r.perms + ' permissions</span></div>' +
      '<div class="d-flex gap-2">' +
      '<button class="btn btn-sm btn-soft-primary flex-grow-1 role-edit" data-name="' + esc(r.name) + '" type="button">Edit role</button>' +
      '<button class="btn btn-sm btn-soft-secondary role-clone" type="button" aria-label="Duplicate"><i class="bi bi-copy"></i></button>' +
      '</div></div></div></div>';
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#roleGrid').html(ROLES.map(roleCard).join('') +
      '<div class="col-xl-4 col-md-6"><div class="card h-100 nx-card-lift" style="border-style:dashed" role="button" id="roleAdd">' +
      '<div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">' +
      '<span class="nx-icon-sq secondary mb-3" style="width:52px;height:52px;font-size:22px"><i class="bi bi-plus-lg"></i></span>' +
      '<h3 class="h6 mb-1">Add a new role</h3><p class="small text-muted mb-0">Start from scratch or duplicate an existing one.</p>' +
      '</div></div></div>');

    $('#roleUsers').html(USERS.map(function (u) {
      return '<tr><td><div class="nx-table-user"><span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + u[2] + '" alt=""></span>' +
        '<div><div class="nx-tu-name">' + u[0] + '</div><div class="nx-tu-sub">' + u[1] + '</div></div></div></td>' +
        '<td><span class="badge badge-soft-' + u[4] + '">' + u[3] + '</span></td>' +
        '<td>' + u[5] + '</td><td class="small text-muted">' + u[6] + '</td>' +
        '<td class="text-end"><a class="btn btn-xs btn-soft-primary" href="page-user-detail.html">View</a></td></tr>';
    }).join(''));

    $('#rlPerms').html(AREAS.map(function (a, i) {
      return '<tr><td>' + a + '</td>' +
        '<td class="text-center"><input class="form-check-input" type="checkbox" checked aria-label="Read ' + a + '"></td>' +
        '<td class="text-center"><input class="form-check-input" type="checkbox"' + (i < 2 ? ' checked' : '') + ' aria-label="Write ' + a + '"></td>' +
        '<td class="text-center"><input class="form-check-input" type="checkbox" aria-label="Delete ' + a + '"></td></tr>';
    }).join(''));

    $('#roleAdd, .role-clone').on('click', function () {
      $('#roleModalTitle').text('Add role');
      $('#rlName, #rlDesc').val('');
      new bootstrap.Modal(document.getElementById('roleModal')).show();
    });

    $('#roleGrid').on('click', '.role-edit', function () {
      $('#roleModalTitle').text('Edit “' + $(this).data('name') + '”');
      $('#rlName').val($(this).data('name'));
      new bootstrap.Modal(document.getElementById('roleModal')).show();
    });

    $('#rlSave').on('click', function () {
      var name = $('#rlName').val().trim();
      if (!name) { $('#rlName').addClass('is-invalid'); return; }
      $('#rlName').removeClass('is-invalid');
      bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
      Swal.fire({ icon: 'success', title: 'Role saved', text: name, timer: 1600, showConfirmButton: false });
    });
  });
})(jQuery);
