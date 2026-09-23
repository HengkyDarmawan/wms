/* NexaDash — pages/landing.html */
(function ($) {
  'use strict';

  var MONTHS = ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'];

  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#lpRevenueChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'area', height: 300 },
        series: [
          { name: 'Revenue', data: [28.4, 30.1, 31.8, 33.2, 34.9, 36.4, 38.0, 40.2, 41.8, 43.5, 45.9, 48.2] },
          { name: 'Expenses', data: [18.2, 18.9, 19.4, 20.1, 20.8, 21.2, 22.0, 22.8, 23.1, 23.9, 24.6, 25.2] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-2')],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } },
        dataLabels: { enabled: false },
        xaxis: { categories: MONTHS, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return '$' + v.toFixed(0) + 'K'; } } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#lpTrafficChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 300 },
        series: [{ name: 'Sessions', data: [12.4, 13.1, 12.8, 14.2, 15.6, 15.1, 16.8, 18.2, 17.6, 19.4, 21.0, 22.8] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: MONTHS, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return v.toFixed(0) + 'K'; } } }
      });
    });
  });
})(jQuery);
