/* NexaDash — dashboards/support.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#volumeChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 320, stacked: false },
        series: [
          { name: 'Created', data: [58, 64, 52, 71, 66, 41, 32, 69, 74, 61, 58, 77, 63, 54] },
          { name: 'Resolved', data: [51, 60, 58, 66, 70, 46, 38, 62, 71, 68, 61, 72, 69, 64] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-4')],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '62%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: Array.from({ length: 14 }, function (_, i) { return 'D' + (i + 1); }), axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#channelChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'donut', height: 300 },
        series: [46, 28, 16, 10],
        labels: ['Email', 'Live chat', 'In-app', 'Phone'],
        colors: nxChartColors().slice(0, 4),
        dataLabels: { enabled: false },
        legend: { position: 'bottom' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: { pie: { donut: { size: '74%', labels: { show: true, value: { color: nxCss('--nx-text'), fontWeight: 800, formatter: function (v) { return v + '%'; } }, total: { show: true, label: 'Tickets', color: nxCss('--nx-text-muted'), formatter: function () { return '128'; } } } } } }
      });
    });
  });
})(jQuery);
