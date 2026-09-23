/* NexaDash — chart-sparkline.html */
(function ($) {
  'use strict';

  function spark(id, data, colorVar, type, height) {
    nxMountChart(id, function () {
      if (type === 'radialBar') {
        return {
          chart: { type: 'radialBar', height: height || 100, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
          series: [data],
          colors: [nxCss(colorVar)],
          plotOptions: { radialBar: { hollow: { size: '58%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { name: { show: false }, value: { fontSize: '14px', fontWeight: 700, color: nxCss('--nx-text'), offsetY: 6 } } } }
        };
      }
      return {
        chart: { type: type || 'area', height: height || 60, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: 'Value', data: data }],
        colors: [nxCss(colorVar)],
        stroke: { curve: 'smooth', width: 2 },
        plotOptions: { bar: { borderRadius: 2, columnWidth: '58%' } },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        tooltip: { theme: nxTheme() }
      };
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    spark('#sp1', [420, 448, 431, 467, 452, 476, 483], '--nx-chart-1');
    spark('#sp2', [980, 1020, 1006, 1104, 1188, 1240, 1284], '--nx-chart-2');
    spark('#sp3', [3.1, 2.9, 3.0, 2.6, 2.4, 2.2, 2.1], '--nx-chart-3', 'line');
    spark('#sp4', [118, 121, 119, 124, 126, 127, 128], '--nx-chart-4', 'bar');

    spark('#spType1', [12, 18, 14, 22, 19, 26, 31], '--nx-chart-1', 'area', 80);
    spark('#spType2', [31, 26, 28, 22, 25, 19, 16], '--nx-chart-2', 'line', 80);
    spark('#spType3', [14, 22, 18, 28, 24, 32, 36], '--nx-chart-3', 'bar', 80);
    spark('#spType4', 74, '--nx-chart-4', 'radialBar', 100);

    spark('#spRow1', [162, 168, 171, 175, 178, 181, 184], '--nx-chart-1', 'line', 40);
    spark('#spRow2', [2840, 2910, 2980, 3040, 3120, 3180, 3218], '--nx-chart-4', 'line', 40);
    spark('#spRow3', [104, 98, 96, 92, 90, 88, 86], '--nx-chart-3', 'line', 40);
    spark('#spRow4', [186, 192, 198, 202, 208, 211, 214], '--nx-chart-2', 'line', 40);
  });
})(jQuery);
