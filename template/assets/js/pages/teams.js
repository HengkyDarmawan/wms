/* NexaDash — pages/teams.html */
(function ($) {
  'use strict';

  var TEAMS = [
    { name: 'Core Product', icon: 'bi-kanban', color: 'primary', desc: 'Owns the roadmap, discovery and everything customers see first.',
      lead: 'Sarah Chen', leadImg: 47, members: [47, 36, 28, 60], total: 8, projects: 6, open: 14, tags: ['product', 'discovery'] },
    { name: 'Platform Engineering', icon: 'bi-hdd-stack', color: 'info', desc: 'Billing, identity and the services everything else is built on.',
      lead: 'Marcus Webb', leadImg: 68, members: [68, 25, 52, 57, 8], total: 12, projects: 9, open: 34, tags: ['backend', 'infra'] },
    { name: 'Design Systems', icon: 'bi-palette', color: 'warning', desc: 'Tokens, components and the review that keeps them consistent.',
      lead: 'Lena Iversen', leadImg: 32, members: [32, 5, 26], total: 6, projects: 4, open: 9, tags: ['design', 'a11y'] },
    { name: 'Customer Support', icon: 'bi-headset', color: 'success', desc: 'First response, escalation and the voice of the customer internally.',
      lead: 'Tom Baker', leadImg: 15, members: [15, 20, 45, 51], total: 9, projects: 2, open: 128, tags: ['support', 'apac'] },
    { name: 'Data & Analytics', icon: 'bi-graph-up', color: 'secondary', desc: 'The warehouse, the metric definitions and the arguments about both.',
      lead: 'Emma Wilson', leadImg: 24, members: [24, 52], total: 5, projects: 3, open: 11, tags: ['data', 'sql'] },
    { name: 'Revenue', icon: 'bi-cash-coin', color: 'danger', desc: 'Pipeline, renewals and the uncomfortable questions about churn.',
      lead: 'Diego Alvarez', leadImg: 51, members: [51, 31, 44], total: 7, projects: 2, open: 6, tags: ['sales', 'crm'] }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function card(t) {
    return '<div class="col-xl-4 col-md-6 tm-item"><div class="card h-100 nx-card-lift"><div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-start gap-3 mb-3">' +
      '<span class="nx-icon-sq ' + t.color + '"><i class="bi ' + t.icon + '"></i></span>' +
      '<div class="min-w-0"><h3 class="h5 mb-0 text-truncate">' + esc(t.name) + '</h3>' +
      '<div class="small text-muted">' + t.total + ' members</div></div>' +
      '<div class="dropdown ms-auto"><button class="nx-icon-btn" data-bs-toggle="dropdown" type="button" aria-label="Team actions"><i class="bi bi-three-dots-vertical"></i></button>' +
      '<div class="dropdown-menu dropdown-menu-end"><button class="dropdown-item" type="button"><i class="bi bi-pencil"></i>Rename</button>' +
      '<button class="dropdown-item" type="button"><i class="bi bi-person-plus"></i>Add member</button>' +
      '<button class="dropdown-item text-danger tm-del" type="button"><i class="bi bi-trash"></i>Delete team</button></div></div>' +
      '</div>' +
      '<p class="small text-muted flex-grow-1">' + esc(t.desc) + '</p>' +
      '<div class="d-flex flex-wrap gap-1 mb-3">' + t.tags.map(function (g) {
        return '<span class="badge badge-soft-secondary">#' + esc(g) + '</span>';
      }).join('') + '</div>' +
      '<div class="d-flex align-items-center gap-2 mb-3">' +
      '<span class="nx-avatar-group">' + t.members.map(function (m) {
        return '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=' + m + '" alt=""></span>';
      }).join('') + (t.total > t.members.length ? '<span class="nx-avatar nx-avatar-sm">+' + (t.total - t.members.length) + '</span>' : '') + '</span>' +
      '<span class="small text-muted ms-auto">Lead: ' + esc(t.lead) + '</span></div>' +
      '<div class="row g-0 text-center border-top pt-3">' +
      '<div class="col-6 border-end"><div class="fw-bold">' + t.projects + '</div><div class="small text-muted">Projects</div></div>' +
      '<div class="col-6"><div class="fw-bold">' + t.open + '</div><div class="small text-muted">Open items</div></div>' +
      '</div></div></div></div>';
  }

  document.addEventListener('nx:layout-ready', function () {
    function render() {
      $('#tmGrid').html(TEAMS.map(card).join(''));
      $('#tmCount').text(TEAMS.length);
    }
    render();

    $('#tmSearch').on('input', function () {
      var q = this.value.toLowerCase();
      var shown = 0;
      $('.tm-item').each(function () {
        var ok = $(this).text().toLowerCase().indexOf(q) > -1;
        $(this).toggle(ok);
        if (ok) shown++;
      });
      $('#tmCount').text(shown);
    });

    var seq = 0;
    $('#tmAdd').on('click', function () {
      seq++;
      TEAMS.unshift({
        name: 'New team ' + seq, icon: 'bi-people', color: 'secondary',
        desc: 'No description yet.', lead: 'Aigars S.', leadImg: 12,
        members: [12], total: 1, projects: 0, open: 0, tags: ['new']
      });
      render();
    });

    $('#tmGrid').on('click', '.tm-del', function () {
      var idx = $('.tm-item').index($(this).closest('.tm-item'));
      TEAMS.splice(idx, 1);
      render();
    });
  });
})(jQuery);
