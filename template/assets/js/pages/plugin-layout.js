/* NexaDash — plugins/layout.html (Gridstack + Interact.js) */
(function ($) {
  'use strict';

  var grid = null;
  var seq = 0;

  var DEFAULT = [
    { x: 0, y: 0, w: 4, h: 3, id: 'kpi', title: 'Revenue', icon: 'bi-currency-dollar', color: 'primary', kind: 'kpi', value: '$48.2K', sub: '+12.4% vs last month' },
    { x: 4, y: 0, w: 5, h: 3, id: 'chart', title: 'Growth', icon: 'bi-graph-up', color: 'success', kind: 'chart' },
    { x: 9, y: 0, w: 3, h: 3, id: 'donut', title: 'Channels', icon: 'bi-pie-chart', color: 'info', kind: 'donut' },
    { x: 0, y: 3, w: 4, h: 3, id: 'kpi2', title: 'Active users', icon: 'bi-people', color: 'warning', kind: 'kpi', value: '12,847', sub: '+3.1% vs last month' },
    { x: 4, y: 3, w: 4, h: 3, id: 'list', title: 'Recent deployments', icon: 'bi-rocket-takeoff', color: 'danger', kind: 'list' },
    { x: 8, y: 3, w: 4, h: 3, id: 'kpi3', title: 'Uptime', icon: 'bi-shield-check', color: 'success', kind: 'kpi', value: '99.98%', sub: 'Last 30 days' }
  ];

  function widgetHtml(w) {
    var body = '';
    if (w.kind === 'kpi') {
      body = '<div class="nx-kpi-value" style="font-size:24px">' + w.value + '</div>' +
        '<div class="small text-muted">' + w.sub + '</div>';
    } else if (w.kind === 'chart') {
      body = '<div class="gs-chart" style="height:100%;min-height:80px"></div>';
    } else if (w.kind === 'donut') {
      body = '<div class="gs-donut" style="height:100%;min-height:80px"></div>';
    } else if (w.kind === 'list') {
      body = '<div class="small">' +
        '<div class="d-flex justify-content-between py-1"><span class="badge badge-soft-success">prod</span><span class="text-muted">v2.4.1</span></div>' +
        '<div class="d-flex justify-content-between py-1"><span class="badge badge-soft-warning">staging</span><span class="text-muted">v2.5.0-rc.2</span></div>' +
        '<div class="d-flex justify-content-between py-1"><span class="badge badge-soft-secondary">dev</span><span class="text-muted">v2.5.0-rc.3</span></div></div>';
    } else {
      body = '<div class="small text-muted">Drag my header to move me, or grab the bottom-right corner to resize.</div>';
    }

    return '<div class="card h-100 d-flex flex-column">' +
      '<div class="card-header gs-handle" style="cursor:move;padding:10px 14px">' +
      '<span class="nx-icon-sq ' + w.color + '" style="width:28px;height:28px;font-size:14px"><i class="bi ' + w.icon + '"></i></span>' +
      '<span class="fw-semibold small">' + w.title + '</span>' +
      '<button class="nx-icon-btn ms-auto gs-remove" type="button" style="width:28px;height:28px;font-size:14px" aria-label="Remove widget"><i class="bi bi-x-lg"></i></button>' +
      '</div>' +
      '<div class="card-body" style="padding:14px;overflow:hidden">' + body + '</div></div>';
  }

  function mountCharts() {
    // Chart di dalam widget dibuat ulang setiap layout berubah agar ukurannya pas.
    document.querySelectorAll('.gs-chart').forEach(function (el) {
      el.innerHTML = '';
      new ApexCharts(el, {
        chart: { type: 'area', height: '100%', sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: 'MRR', data: [28, 31, 30, 36, 38, 42, 45, 48] }],
        colors: [nxCss('--nx-chart-1')],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        tooltip: { theme: nxTheme() }
      }).render();
    });
    document.querySelectorAll('.gs-donut').forEach(function (el) {
      el.innerHTML = '';
      new ApexCharts(el, {
        chart: { type: 'donut', height: '100%', fontFamily: 'Inter, sans-serif' },
        series: [42, 28, 18, 12],
        labels: ['Direct', 'Affiliate', 'Market', 'Partner'],
        colors: nxChartColors().slice(0, 4),
        legend: { show: false },
        dataLabels: { enabled: false },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: { pie: { donut: { size: '70%' } } },
        tooltip: { theme: nxTheme() }
      }).render();
    });
  }

  function load(items) {
    grid.removeAll();
    items.forEach(function (w) {
      grid.addWidget({ x: w.x, y: w.y, w: w.w, h: w.h, content: widgetHtml(w) });
    });
    mountCharts();
  }

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Gridstack ===================== */
    grid = GridStack.init({
      cellHeight: 92,
      margin: 10,
      handle: '.gs-handle',
      float: false,
      column: 12,
      columnOpts: { breakpoints: [{ w: 768, c: 1 }, { w: 1200, c: 6 }] }
    }, '#gsGrid');

    load(DEFAULT);

    grid.on('resizestop', function () { setTimeout(mountCharts, 120); });
    grid.on('change', function () { $('#gsStatus').text('modified (not saved)'); });

    $('#gsGrid').on('click', '.gs-remove', function () {
      grid.removeWidget($(this).closest('.grid-stack-item')[0]);
    });

    $('#gsAdd').on('click', function () {
      seq++;
      grid.addWidget({
        w: 3, h: 2,
        content: widgetHtml({ title: 'Widget ' + seq, icon: 'bi-box', color: 'secondary', kind: 'text' })
      });
    });

    $('#gsSave').on('click', function () {
      localStorage.setItem('nx-gridstack', JSON.stringify(grid.save(true)));
      $('#gsStatus').text('saved just now');
    });

    $('#gsLoad').on('click', function () {
      var raw = localStorage.getItem('nx-gridstack');
      if (!raw) { $('#gsStatus').text('nothing saved yet'); return; }
      grid.removeAll();
      grid.load(JSON.parse(raw));
      mountCharts();
      $('#gsStatus').text('restored from localStorage');
    });

    $('#gsReset').on('click', function () {
      load(DEFAULT);
      $('#gsStatus').text('reset to default');
    });

    $('#gsLock').on('change', function () {
      grid.enableMove(!this.checked);
      grid.enableResize(!this.checked);
      $('#gsStatus').text(this.checked ? 'locked' : 'unlocked');
    });

    /* ===================== Interact.js ===================== */
    function position(el, x, y) {
      el.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
      el.setAttribute('data-x', x);
      el.setAttribute('data-y', y);
      $('#ixPos').text(Math.round(x) + ', ' + Math.round(y));
    }
    function resetBoxes() {
      document.querySelectorAll('.ix-box').forEach(function (el, i) {
        var defaults = [[24, 24], [240, 120], [120, 230]][i] || [0, 0];
        el.style.left = '0'; el.style.top = '0';
        position(el, defaults[0], defaults[1]);
      });
    }
    resetBoxes();

    function modifiers() {
      var mods = [interact.modifiers.restrictRect({ restriction: 'parent', endOnly: true })];
      if ($('#ixSnap').is(':checked')) {
        mods.push(interact.modifiers.snap({
          targets: [interact.snappers.grid({ x: 20, y: 20 })],
          range: Infinity,
          relativePoints: [{ x: 0, y: 0 }]
        }));
      }
      return mods;
    }

    function bindDrag() {
      interact('.ix-box').unset();
      interact('.ix-box').draggable({
        inertia: $('#ixInertia').is(':checked'),
        modifiers: modifiers(),
        listeners: {
          move: function (event) {
            var x = (parseFloat(event.target.getAttribute('data-x')) || 0) + event.dx;
            var y = (parseFloat(event.target.getAttribute('data-y')) || 0) + event.dy;
            position(event.target, x, y);
          }
        }
      });
      interact('.ix-resize').resizable({
        edges: { left: true, right: true, bottom: true, top: true },
        modifiers: [
          interact.modifiers.restrictSize({ min: { width: 140, height: 110 } }),
          interact.modifiers.restrictEdges({ outer: 'parent' })
        ],
        listeners: {
          move: function (event) {
            var x = (parseFloat(event.target.getAttribute('data-x')) || 0) + event.deltaRect.left;
            var y = (parseFloat(event.target.getAttribute('data-y')) || 0) + event.deltaRect.top;
            event.target.style.width = event.rect.width + 'px';
            event.target.style.height = event.rect.height + 'px';
            position(event.target, x, y);
          }
        }
      });
    }
    bindDrag();

    $('#ixSnap, #ixInertia').on('change', bindDrag);
    $('#ixReset').on('click', resetBoxes);
  });

  document.addEventListener('nx:theme-changed', function () {
    if (grid) setTimeout(mountCharts, 60);
  });
})(jQuery);
