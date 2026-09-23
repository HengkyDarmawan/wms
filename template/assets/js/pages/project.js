/* NexaDash — dashboards/project.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#burndownChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 320 },
        series: [
          { name: 'Remaining', data: [96, 92, 86, 81, 78, 70, 66, 61, 55, 52] },
          { name: 'Ideal', data: [96, 86, 77, 67, 58, 48, 38, 29, 19, 10] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-text-muted')],
        stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 6] },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: { categories: ['D1','D2','D3','D4','D5','D6','D7','D8','D9','D10'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { title: { text: 'Story points', style: { fontWeight: 500 } } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#statusDonut', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'donut', height: 300 },
        series: [34, 12, 8, 3],
        labels: ['Done', 'In progress', 'Review', 'Blocked'],
        colors: [nxCss('--nx-chart-4'), nxCss('--nx-chart-1'), nxCss('--nx-chart-3'), nxCss('--nx-danger')],
        dataLabels: { enabled: false },
        legend: { position: 'bottom' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: { pie: { donut: { size: '74%', labels: { show: true, value: { color: nxCss('--nx-text'), fontWeight: 800 }, total: { show: true, label: 'Issues', color: nxCss('--nx-text-muted') } } } } }
      });
    });
  });
})(jQuery);
