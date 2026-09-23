/* NexaDash — pages/projects.html */
(function ($) {
  'use strict';

  var STATUS = { 'On track': 'success', 'At risk': 'warning', 'Blocked': 'danger', 'Done': 'secondary', 'Planning': 'info' };

  var PROJECTS = [
    { name: 'Billing reliability', team: 'Platform Engineering', icon: 'bi-credit-card', color: 'primary', progress: 68, status: 'On track', due: 'Oct 2', budget: 68, spend: '$164K', of: '$240K', members: [68, 25, 12], total: 6, tasks: '34/49' },
    { name: 'Self-serve onboarding', team: 'Core Product', icon: 'bi-magic', color: 'warning', progress: 42, status: 'At risk', due: 'Oct 18', budget: 54, spend: '$92K', of: '$170K', members: [47, 32, 5], total: 8, tasks: '18/42' },
    { name: 'Analytics surface', team: 'Data & Analytics', icon: 'bi-graph-up', color: 'info', progress: 12, status: 'Planning', due: 'Dec 1', budget: 8, spend: '$18K', of: '$220K', members: [24, 52], total: 5, tasks: '4/38' },
    { name: 'Design system v3', team: 'Design Systems', icon: 'bi-palette', color: 'success', progress: 100, status: 'Done', due: 'Shipped', budget: 96, spend: '$118K', of: '$122K', members: [32, 26, 5], total: 6, tasks: '52/52' },
    { name: 'SSO & SCIM', team: 'Platform Engineering', icon: 'bi-shield-lock', color: 'danger', progress: 26, status: 'Blocked', due: 'Nov 9', budget: 31, spend: '$48K', of: '$154K', members: [25, 57], total: 4, tasks: '9/34' },
    { name: 'APAC data residency', team: 'Platform Engineering', icon: 'bi-globe-asia-australia', color: 'info', progress: 84, status: 'On track', due: 'Oct 10', budget: 78, spend: '$132K', of: '$168K', members: [25, 20, 68], total: 7, tasks: '41/49' },
    { name: 'Support deflection', team: 'Customer Support', icon: 'bi-life-preserver', color: 'success', progress: 57, status: 'On track', due: 'Nov 1', budget: 44, spend: '$26K', of: '$60K', members: [15, 20, 45], total: 5, tasks: '21/37' },
    { name: 'Pricing experiment', team: 'Revenue', icon: 'bi-tags', color: 'warning', progress: 38, status: 'At risk', due: 'Oct 24', budget: 61, spend: '$34K', of: '$56K', members: [51, 31], total: 3, tasks: '11/29' }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function card(p) {
    return '<div class="col-xl-4 col-md-6 pr-item" data-status="' + p.status + '"><div class="card h-100 nx-card-lift"><div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-start gap-3 mb-3">' +
      '<span class="nx-icon-sq ' + p.color + '"><i class="bi ' + p.icon + '"></i></span>' +
      '<div class="min-w-0"><h3 class="h5 mb-0 text-truncate">' + esc(p.name) + '</h3><div class="small text-muted">' + esc(p.team) + '</div></div>' +
      '<span class="badge badge-soft-' + STATUS[p.status] + ' ms-auto">' + p.status + '</span></div>' +
      '<div class="mb-2 d-flex justify-content-between small"><span class="text-muted">Progress</span><span class="fw-semibold">' + p.progress + '%</span></div>' +
      '<div class="progress progress-thin mb-3" role="progressbar" aria-label="Progress" aria-valuenow="' + p.progress + '" aria-valuemin="0" aria-valuemax="100">' +
      '<div class="progress-bar' + (p.progress === 100 ? ' bg-success' : (p.status === 'Blocked' ? ' bg-danger' : (p.status === 'At risk' ? ' bg-warning' : ''))) + '" style="width:' + p.progress + '%"></div></div>' +
      '<div class="row g-2 small text-muted mb-3">' +
      '<div class="col-6"><i class="bi bi-list-check me-1"></i>' + p.tasks + ' tasks</div>' +
      '<div class="col-6"><i class="bi bi-calendar3 me-1"></i>' + p.due + '</div>' +
      '<div class="col-6"><i class="bi bi-cash me-1"></i>' + p.spend + ' of ' + p.of + '</div>' +
      '<div class="col-6"><i class="bi bi-people me-1"></i>' + p.total + ' people</div>' +
      '</div>' +
      '<div class="mt-auto d-flex align-items-center gap-2">' +
      '<span class="nx-avatar-group">' + p.members.map(function (m) {
        return '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=' + m + '" alt=""></span>';
      }).join('') + (p.total > p.members.length ? '<span class="nx-avatar nx-avatar-sm">+' + (p.total - p.members.length) + '</span>' : '') + '</span>' +
      '<a class="btn btn-sm btn-soft-primary ms-auto" href="apps-kanban.html">Open board</a>' +
      '</div></div></div></div>';
  }

  function row(p) {
    return '<tr class="pr-item" data-status="' + p.status + '">' +
      '<td><div class="d-flex align-items-center gap-3"><span class="nx-icon-sq ' + p.color + '"><i class="bi ' + p.icon + '"></i></span>' +
      '<div><div class="fw-semibold">' + esc(p.name) + '</div><div class="small text-muted">' + p.tasks + ' tasks</div></div></div></td>' +
      '<td class="small">' + esc(p.team) + '</td>' +
      '<td style="min-width:150px"><div class="progress progress-thin" role="progressbar" aria-label="Progress" aria-valuenow="' + p.progress + '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:' + p.progress + '%"></div></div><span class="small text-muted">' + p.progress + '%</span></td>' +
      '<td class="small nx-num">' + p.spend + ' / ' + p.of + '</td>' +
      '<td class="small">' + p.due + '</td>' +
      '<td><span class="badge badge-soft-' + STATUS[p.status] + '">' + p.status + '</span></td></tr>';
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#prGrid').html(PROJECTS.map(card).join(''));
    $('#prTableBody').html(PROJECTS.map(row).join(''));

    var status = 'all';
    function filter() {
      var q = ($('#prSearch').val() || '').toLowerCase();
      $('.pr-item').each(function () {
        var ok = (status === 'all' || $(this).data('status') === status) &&
          $(this).text().toLowerCase().indexOf(q) > -1;
        $(this).toggle(ok);
      });
    }
    $('#prSearch').on('input', filter);
    $('#prFilters').on('click', 'button', function () {
      $('#prFilters button').removeClass('btn-soft-primary active').addClass('btn-soft-secondary');
      $(this).removeClass('btn-soft-secondary').addClass('btn-soft-primary active');
      status = $(this).data('s');
      filter();
    });

    $('#prGridBtn').on('click', function () {
      $(this).addClass('active'); $('#prTableBtn').removeClass('active');
      $('#prGrid').removeClass('d-none'); $('#prTable').addClass('d-none');
    });
    $('#prTableBtn').on('click', function () {
      $(this).addClass('active'); $('#prGridBtn').removeClass('active');
      $('#prTable').removeClass('d-none'); $('#prGrid').addClass('d-none');
    });
  });
})(jQuery);
