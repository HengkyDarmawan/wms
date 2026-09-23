/* NexaDash — page-profile.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#profileChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 260 },
        series: [
          { name: 'Commits', data: [42, 58, 36, 64, 71, 55, 82, 68, 91, 74, 86, 96] },
          { name: 'Reviews', data: [18, 24, 16, 28, 31, 22, 36, 29, 41, 33, 38, 44] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-2')],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '58%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#profileRadial', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'radialBar', height: 210 },
        series: [76],
        labels: ['Q3 goal'],
        colors: [nxCss('--nx-chart-4')],
        plotOptions: { radialBar: { hollow: { size: '62%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { name: { fontSize: '12px', color: nxCss('--nx-text-muted'), offsetY: 18 }, value: { fontSize: '26px', fontWeight: 800, color: nxCss('--nx-text'), offsetY: -12 } } } }
      });
    });
  });
})(jQuery);
