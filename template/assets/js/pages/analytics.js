/* NexaDash — dashboard-analytics.html */
(function ($) {
  'use strict';

  function heatData() {
    var days = ['Sun', 'Sat', 'Fri', 'Thu', 'Wed', 'Tue', 'Mon'];
    return days.map(function (d, di) {
      return {
        name: d,
        data: Array.from({ length: 12 }, function (_, h) {
          var hour = h * 2;
          var peak = (hour > 8 && hour < 20) ? 60 : 15;
          var weekend = (d === 'Sat' || d === 'Sun') ? 0.6 : 1;
          return { x: (hour < 10 ? '0' : '') + hour + ':00', y: Math.round((peak + Math.sin((h + di) / 2) * 25 + di * 3) * weekend) };
        })
      };
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    flatpickr('#anaRange', { mode: 'range', dateFormat: 'M j, Y', defaultDate: [new Date(Date.now() - 29 * 864e5), new Date()] });

    nxMountChart('#trafficChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 320 },
        series: [
          { name: 'Sessions', data: [12400, 13100, 12800, 14200, 15600, 15100, 16800, 18200, 17600, 19400, 21000, 22800] },
          { name: 'Users', data: [8200, 8800, 8500, 9400, 10300, 10100, 11200, 12000, 11700, 12900, 14100, 15200] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-2')],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['W1','W2','W3','W4','W5','W6','W7','W8','W9','W10','W11','W12'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' },
        annotations: {
          xaxis: [{
            x: 'W7',
            borderColor: nxCss('--nx-chart-3'),
            strokeDashArray: 4,
            label: { text: 'Campaign launch', style: { background: nxCss('--nx-chart-3'), color: '#fff', fontSize: '11px' } }
          }]
        }
      });
    });

    nxMountChart('#devicesChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'radialBar', height: 240 },
        series: [62, 31, 7],
        labels: ['Mobile', 'Desktop', 'Tablet'],
        colors: nxChartColors().slice(0, 3),
        plotOptions: {
          radialBar: {
            hollow: { size: '38%' },
            track: { background: nxCss('--nx-secondary-subtle') },
            dataLabels: { name: { fontSize: '12px' }, value: { fontSize: '16px', fontWeight: 700, color: nxCss('--nx-text') } }
          }
        },
        legend: { show: true, position: 'bottom', fontSize: '12px' }
      });
    });

    nxMountChart('#topPagesChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 320 },
        series: [{ name: 'Pageviews', data: [42180, 31240, 24810, 19620, 14380, 10920] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['/pricing', '/docs/quickstart', '/blog/flux-2-0', '/changelog', '/integrations', '/careers'] }
      });
    });

    nxMountChart('#hoursHeatmap', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'heatmap', height: 320 },
        series: heatData(),
        colors: [nxCss('--nx-chart-1')],
        dataLabels: { enabled: false },
        plotOptions: { heatmap: { radius: 4, enableShades: true, shadeIntensity: 0.6 } },
        xaxis: { axisBorder: { show: false }, axisTicks: { show: false } }
      });
    });
  });
})(jQuery);
