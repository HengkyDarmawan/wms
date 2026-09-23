/* NexaDash — dashboards/academy.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#enrolChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'area', height: 320 },
        series: [
          { name: 'Enrolments', data: [420, 468, 512, 556, 601, 648, 690, 742, 786, 834, 902, 968] },
          { name: 'Completions', data: [260, 296, 322, 368, 402, 441, 478, 512, 548, 592, 640, 688] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-4')],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.32, opacityTo: 0.02, stops: [0, 95] } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#trackRadial', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'radialBar', height: 300 },
        series: [82, 74, 61, 48],
        labels: ['Analytics', 'Data', 'Design', 'Security'],
        colors: nxChartColors().slice(0, 4),
        plotOptions: { radialBar: { hollow: { size: '32%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { value: { color: nxCss('--nx-text') } } } },
        legend: { show: true, position: 'bottom', fontSize: '12px' }
      });
    });
  });
})(jQuery);
