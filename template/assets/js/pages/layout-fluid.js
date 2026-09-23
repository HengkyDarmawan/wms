/* NexaDash — layouts/fluid.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#lfChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 340 },
        series: [
          { name: 'eu-west-1', data: [18.4, 18.9, 19.6, 20.2, 21.0, 21.4, 22.1, 22.8, 23.2, 23.9, 24.4, 25.1] },
          { name: 'us-east-1', data: [24.9, 25.4, 26.1, 26.8, 27.2, 27.9, 28.4, 29.1, 29.6, 30.2, 30.8, 31.4] },
          { name: 'ap-southeast-1', data: [7.2, 7.6, 8.1, 8.4, 8.8, 9.2, 9.6, 10.1, 10.4, 10.9, 11.4, 11.8] }
        ],
        colors: nxChartColors().slice(0, 3),
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: function (v) { return v.toFixed(0) + 'M'; } } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });
  });
})(jQuery);
