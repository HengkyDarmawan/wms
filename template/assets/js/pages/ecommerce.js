/* NexaDash — dashboard-ecommerce.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#ordersRevenueChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 330, stacked: false },
        series: [
          { name: 'Orders', type: 'column', data: [186, 204, 198, 232, 248, 261, 275, 292, 284, 301, 318, 322] },
          { name: 'Revenue', type: 'line', data: [72, 79, 76, 88, 94, 99, 104, 112, 108, 116, 124, 128] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-3')],
        stroke: { width: [0, 3], curve: 'smooth' },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '48%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: [
          { title: { text: 'Orders', style: { fontWeight: 500 } } },
          { opposite: true, title: { text: 'Revenue ($K)', style: { fontWeight: 500 } } }
        ],
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });
  });
})(jQuery);
