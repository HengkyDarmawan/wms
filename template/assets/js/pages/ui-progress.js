/* NexaDash — ui-progress-spinners.html (circular progress) */
(function ($) {
  'use strict';

  function circ(id, value, colorVar) {
    nxMountChart(id, function () {
      return {
        chart: { type: 'radialBar', height: 130, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [value],
        colors: [nxCss(colorVar)],
        plotOptions: {
          radialBar: {
            hollow: { size: '58%' },
            track: { background: nxCss('--nx-secondary-subtle') },
            dataLabels: { name: { show: false }, value: { fontSize: '16px', fontWeight: 700, color: nxCss('--nx-text'), offsetY: 6 } }
          }
        }
      };
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    circ('#circ1', 82, '--nx-chart-1');
    circ('#circ2', 64, '--nx-chart-4');
    circ('#circ3', 38, '--nx-chart-3');
  });
})(jQuery);
