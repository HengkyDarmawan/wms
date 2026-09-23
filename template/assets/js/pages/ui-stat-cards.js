/* NexaDash — ui/stat-cards.html */
(function ($) {
  'use strict';

  function spark(id, data, colorVar) {
    nxMountChart(id, function () {
      return {
        chart: { type: 'area', height: 46, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: '', data: data }],
        colors: [nxCss(colorVar)],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        tooltip: { enabled: false }
      };
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    spark('#scSpark1', [162, 168, 171, 175, 178, 181, 184], '--nx-chart-1');
    spark('#scSpark2', [2840, 2910, 2980, 3040, 3120, 3180, 3218], '--nx-chart-4');
    spark('#scSpark3', [104, 98, 96, 92, 90, 88, 86], '--nx-chart-3');
    spark('#scSpark4', [186, 192, 198, 202, 208, 211, 214], '--nx-chart-2');

    nxMountChart('#scRadial', function () {
      return {
        chart: { type: 'radialBar', height: 120, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [69],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: {
          radialBar: {
            hollow: { size: '58%' },
            track: { background: nxCss('--nx-secondary-subtle') },
            dataLabels: { name: { show: false }, value: { fontSize: '16px', fontWeight: 700, color: nxCss('--nx-text'), offsetY: 6 } }
          }
        }
      };
    });

    nxMountChart('#scBar', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 220 },
        series: [{ name: 'Revenue', data: [42, 28, 18, 12, 9] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '62%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: { categories: ['Direct', 'Affiliate', 'Marketplace', 'Partners', 'Other'] }
      });
    });
  });
})(jQuery);
