/* NexaDash — layouts/horizontal.html
   Bar horizontal dibangun dari menu sidebar yang sama supaya tidak ada
   daftar navigasi kedua yang harus dijaga tetap sinkron. */
(function ($) {
  'use strict';

  var TOP = [
    ['index.html', 'bi-grid-1x2', 'Dashboard'],
    ['dashboard-analytics.html', 'bi-graph-up', 'Analytics'],
    ['dashboard-ecommerce.html', 'bi-bag', 'eCommerce'],
    ['commerce-orders.html', 'bi-receipt', 'Orders'],
    ['apps-mail.html', 'bi-envelope', 'Mail'],
    ['apps-kanban.html', 'bi-kanban', 'Kanban'],
    ['plugin-index.html', 'bi-puzzle', 'Plugins'],
    ['page-users.html', 'bi-people-fill', 'Users'],
    ['page-settings.html', 'bi-gear', 'Settings']
  ];

  document.addEventListener('nx:layout-ready', function () {
    var root = nxRoot();
    var routes = window.NX_ROUTES || {};
    var here = window.location.pathname;

    var items = TOP.map(function (t) {
      var href = root + (routes[t[0]] || t[0]);
      var active = new URL(href, window.location.href).pathname === here;
      return '<li><a class="' + (active ? 'active' : '') + '" href="' + href + '">' +
        '<i class="bi ' + t[1] + '"></i>' + t[2] + '</a></li>';
    }).join('');

    var $bar = $('<nav class="nx-hnav" aria-label="Primary"><ul class="nx-hnav-list">' + items +
      '<li class="ms-auto"><a href="' + root + (routes['page-help-center.html'] || 'page-help-center.html') + '">' +
      '<i class="bi bi-book"></i>Docs</a></li></ul></nav>');

    $('.nx-header').after($bar);

    nxMountChart('#lhChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'area', height: 320 },
        series: [{ name: 'Revenue', data: [28.4, 30.1, 31.8, 33.2, 34.9, 36.4, 38.0, 40.2, 41.8, 43.5, 45.9, 48.2] }],
        colors: [nxCss('--nx-chart-1')],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return '$' + v.toFixed(0) + 'K'; } } }
      });
    });
  });
})(jQuery);
