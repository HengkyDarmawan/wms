/* NexaDash — plugins/org-chart.html (d3-org-chart) */
(function ($) {
  'use strict';

  /* Data datar: hierarki dibentuk dari parentId oleh library.
     Avatar sengaja memakai inisial, bukan gambar lintas-origin, supaya
     exportImg() tidak gagal karena canvas ter-taint. */
  var SEED = [
    { id: '1', parentId: '', name: 'Aigars Silkalns', role: 'Chief Executive Officer', dept: 'Executive', location: 'Singapore', email: 'aigars@nexa.io' },
    { id: '2', parentId: '1', name: 'Sarah Chen', role: 'Head of Product', dept: 'Product', location: 'Singapore', email: 'sarah@nexa.io' },
    { id: '3', parentId: '1', name: 'Marcus Webb', role: 'VP Engineering', dept: 'Engineering', location: 'Berlin', email: 'marcus@nexa.io' },
    { id: '4', parentId: '1', name: 'Lena Iversen', role: 'Design Director', dept: 'Design', location: 'Austin', email: 'lena@nexa.io' },
    { id: '5', parentId: '1', name: 'Diego Alvarez', role: 'VP Revenue', dept: 'Sales', location: 'Toronto', email: 'diego@nexa.io' },

    { id: '6', parentId: '2', name: 'Nadia Rahman', role: 'Group PM, Billing', dept: 'Product', location: 'Jakarta', email: 'nadia@nexa.io' },
    { id: '7', parentId: '2', name: 'Ingrid Larsen', role: 'PM, Onboarding', dept: 'Product', location: 'Oslo', email: 'ingrid@nexa.io' },
    { id: '8', parentId: '6', name: 'Yusuf Kaya', role: 'Associate PM', dept: 'Product', location: 'Istanbul', email: 'yusuf@nexa.io' },

    { id: '9', parentId: '3', name: 'Chen Wei', role: 'Staff Engineer, Platform', dept: 'Engineering', location: 'London', email: 'chen@nexa.io' },
    { id: '10', parentId: '3', name: 'Ravi Menon', role: 'Engineering Manager', dept: 'Engineering', location: 'Bangalore', email: 'ravi@nexa.io' },
    { id: '11', parentId: '10', name: 'Piotr Nowak', role: 'Senior Engineer', dept: 'Engineering', location: 'Warsaw', email: 'piotr@nexa.io' },
    { id: '12', parentId: '10', name: 'Lucas Meyer', role: 'Engineer', dept: 'Engineering', location: 'Munich', email: 'lucas@nexa.io' },
    { id: '13', parentId: '9', name: 'Emma Wilson', role: 'Site Reliability Engineer', dept: 'Engineering', location: 'Vancouver', email: 'emma@nexa.io' },

    { id: '14', parentId: '4', name: 'Ava Novak', role: 'Product Designer', dept: 'Design', location: 'Jakarta', email: 'ava@nexa.io' },
    { id: '15', parentId: '4', name: 'Sofia Rossi', role: 'Design Systems Lead', dept: 'Design', location: 'Milan', email: 'sofia@nexa.io' },

    { id: '16', parentId: '5', name: 'Amina Diallo', role: 'Account Executive', dept: 'Sales', location: 'Dakar', email: 'amina@nexa.io' },
    { id: '17', parentId: '5', name: 'Olivia Brown', role: 'Sales Engineer', dept: 'Sales', location: 'London', email: 'olivia@nexa.io' },

    { id: '18', parentId: '1', name: 'Tom Baker', role: 'Head of Support', dept: 'Support', location: 'Seattle', email: 'tom@nexa.io' },
    { id: '19', parentId: '18', name: 'Hana Sato', role: 'Support Lead, APAC', dept: 'Support', location: 'Tokyo', email: 'hana@nexa.io' },
    { id: '20', parentId: '18', name: 'Maria Gomez', role: 'Support Specialist', dept: 'Support', location: 'Austin', email: 'maria@nexa.io' }
  ];

  var DEPT_COLOR = {
    Executive: '--nx-primary',
    Product: '--nx-chart-1',
    Engineering: '--nx-chart-2',
    Design: '--nx-chart-5',
    Sales: '--nx-chart-3',
    Support: '--nx-chart-4',
    Data: '--nx-info'
  };

  var chart = null;
  var data = SEED.map(function (d) { return $.extend({}, d); });
  var selectedId = null;
  var nextId = 100;

  function initials(name) {
    return name.split(' ').map(function (p) { return p[0]; }).slice(0, 2).join('').toUpperCase();
  }

  function esc(s) { return $('<span>').text(s == null ? '' : s).html(); }

  function nodeContent(d) {
    var color = nxCss(DEPT_COLOR[d.data.dept] || '--nx-primary');
    var reports = d.data._directSubordinates || 0;
    var total = d.data._totalSubordinates || 0;

    return '' +
      '<div class="oc-node" style="border-left-color:' + color + '">' +
      '  <div class="oc-node-top">' +
      '    <span class="oc-avatar" style="background:' + color + '22;color:' + color + '">' + esc(initials(d.data.name)) + '</span>' +
      '    <span class="oc-node-meta">' +
      '      <span class="oc-name">' + esc(d.data.name) + '</span>' +
      '      <span class="oc-role">' + esc(d.data.role) + '</span>' +
      '    </span>' +
      '  </div>' +
      '  <div class="oc-node-foot">' +
      '    <span class="oc-dept" style="background:' + color + '1f;color:' + color + '">' + esc(d.data.dept) + '</span>' +
      '    <span class="oc-loc">' + esc(d.data.location) + '</span>' +
      (reports ? '<span class="oc-count">' + reports + ' direct &middot; ' + total + ' total</span>' : '<span class="oc-count">Individual contributor</span>') +
      '  </div>' +
      '</div>';
  }

  function renderDepartments() {
    var counts = {};
    data.forEach(function (d) { counts[d.dept] = (counts[d.dept] || 0) + 1; });
    var max = Math.max.apply(null, Object.values(counts));
    $('#ocDeptList').html(Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; }).map(function (k) {
      var color = nxCss(DEPT_COLOR[k] || '--nx-primary');
      return '<div class="nx-progress-row">' +
        '<div class="nx-pr-head"><span class="nx-pr-label">' + esc(k) + '</span><span class="nx-pr-value">' + counts[k] + '</span></div>' +
        '<div class="progress progress-thin" role="progressbar" aria-label="' + esc(k) + '" aria-valuenow="' + counts[k] + '" aria-valuemin="0" aria-valuemax="' + max + '">' +
        '<div class="progress-bar" style="width:' + Math.round(counts[k] / max * 100) + '%;background:' + color + '"></div></div></div>';
    }).join(''));
  }

  function renderDetail(id) {
    var p = data.find(function (d) { return d.id === id; });
    if (!p) {
      $('#ocDetail').html('<p class="text-muted small mb-0">Click any node in the chart to see their details here.</p>');
      return;
    }
    var color = nxCss(DEPT_COLOR[p.dept] || '--nx-primary');
    var manager = data.find(function (d) { return d.id === p.parentId; });
    var reports = data.filter(function (d) { return d.parentId === p.id; });

    $('#ocDetail').html(
      '<div class="d-flex align-items-center gap-3 mb-3">' +
      '<span class="nx-avatar nx-avatar-lg" style="background:' + color + '22;color:' + color + '">' + esc(initials(p.name)) + '</span>' +
      '<div class="min-w-0"><div class="fw-semibold text-truncate">' + esc(p.name) + '</div>' +
      '<div class="small text-muted">' + esc(p.role) + '</div></div></div>' +
      '<dl class="row small mb-0">' +
      '<dt class="col-5 text-muted fw-normal">Department</dt><dd class="col-7">' + esc(p.dept) + '</dd>' +
      '<dt class="col-5 text-muted fw-normal">Location</dt><dd class="col-7">' + esc(p.location) + '</dd>' +
      '<dt class="col-5 text-muted fw-normal">Email</dt><dd class="col-7 text-truncate">' + esc(p.email) + '</dd>' +
      '<dt class="col-5 text-muted fw-normal">Reports to</dt><dd class="col-7">' + esc(manager ? manager.name : '—') + '</dd>' +
      '<dt class="col-5 text-muted fw-normal">Direct reports</dt><dd class="col-7">' + reports.length + '</dd>' +
      '</dl>' +
      (reports.length
        ? '<div class="mt-3 d-flex flex-wrap gap-1">' + reports.map(function (r) {
            return '<span class="badge badge-soft-secondary">' + esc(r.name) + '</span>';
          }).join('') + '</div>'
        : '')
    );
  }

  function build() {
    if (!window.d3 || !d3.OrgChart) return;
    chart = new d3.OrgChart()
      .container('#orgChart')
      .data(data)
      .nodeWidth(function () { return 248; })
      .nodeHeight(function () { return 118; })
      .childrenMargin(function () { return 56; })
      .compactMarginBetween(function () { return 26; })
      .compactMarginPair(function () { return 62; })
      .siblingsMargin(function () { return 28; })
      .neighbourMargin(function () { return 26; })
      .nodeContent(nodeContent)
      .linkUpdate(function (d, i, arr) {
        d3.select(this)
          .attr('stroke', nxCss('--nx-border'))
          .attr('stroke-width', 1.5);
      })
      .onNodeClick(function (node) {
        // d3-org-chart v3 memberi objek node; versi lain memberi id mentah.
        selectedId = (node && node.data && node.data.id) ? node.data.id : node;
        renderDetail(selectedId);
        chart.setHighlighted(selectedId).render();
      })
      .render();

    chart.fit();
  }

  function rebuild() {
    $('#orgChart').empty();
    build();
    renderDepartments();
  }

  document.addEventListener('nx:layout-ready', function () {
    build();
    renderDepartments();

    $('#ocExpand').on('click', function () { chart.expandAll().fit(); });
    $('#ocCollapse').on('click', function () { chart.collapseAll().fit(); });
    $('#ocZoomIn').on('click', function () { chart.zoomIn(); });
    $('#ocZoomOut').on('click', function () { chart.zoomOut(); });
    $('#ocFit').on('click', function () { chart.fit(); });

    $('.oc-layout').on('click', function () {
      $('.oc-layout').removeClass('active');
      $(this).addClass('active');
      chart.layout($(this).data('layout')).render().fit();
    });

    $('#ocCompact').on('change', function () {
      chart.compact(this.checked).render().fit();
    });

    $('#ocSearch').on('input', function () {
      var q = this.value.trim().toLowerCase();
      chart.clearHighlighting();
      if (!q) { chart.render().fit(); return; }
      var hits = data.filter(function (d) {
        return (d.name + ' ' + d.role + ' ' + d.dept).toLowerCase().indexOf(q) > -1;
      });
      hits.forEach(function (h) { chart.setUpToTheRootHighlighted(h.id); });
      chart.render();
      if (hits.length === 1) chart.setCentered(hits[0].id).render();
    });

    $('#ocExport').on('click', function () {
      chart.exportImg({ full: true, scale: 2, save: true, onLoad: function () {} });
    });

    $('#ocAdd').on('click', function () {
      var name = $('#ocNewName').val().trim();
      var role = $('#ocNewRole').val().trim() || 'Team member';
      if (!name) { $('#ocNewName').addClass('is-invalid'); return; }
      $('#ocNewName').removeClass('is-invalid');
      var parent = selectedId || '1';
      var node = {
        id: String(nextId++), parentId: parent, name: name, role: role,
        dept: $('#ocNewDept').val(), location: 'Remote',
        email: name.toLowerCase().replace(/[^a-z]+/g, '.') + '@nexa.io'
      };
      data.push(node);
      chart.addNode(node);
      renderDepartments();
      $('#ocNewName, #ocNewRole').val('');
    });

    $('#ocRemove').on('click', function () {
      if (!selectedId || selectedId === '1') return;
      var ids = [];
      (function collect(id) {
        ids.push(id);
        data.filter(function (d) { return d.parentId === id; }).forEach(function (c) { collect(c.id); });
      })(selectedId);
      data = data.filter(function (d) { return ids.indexOf(d.id) === -1; });
      chart.removeNode(selectedId);
      selectedId = null;
      renderDetail(null);
      renderDepartments();
    });

    $('#ocReset').on('click', function () {
      data = SEED.map(function (d) { return $.extend({}, d); });
      selectedId = null;
      renderDetail(null);
      $('#ocSearch').val('');
      $('#ocCompact').prop('checked', false);
      $('.oc-layout').removeClass('active').filter('[data-layout="top"]').addClass('active');
      rebuild();
    });

    $(window).on('resize', function () { if (chart) chart.fit(); });
  });

  // Warna node dibaca dari token CSS, jadi bangun ulang saat tema berganti.
  document.addEventListener('nx:theme-changed', function () {
    if (chart) rebuild();
  });
})(jQuery);
