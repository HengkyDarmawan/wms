/* NexaDash — dashboard-crm.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    // Funnel: bar horizontal bertingkat.
    nxMountChart('#funnelChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 320 },
        series: [{ name: 'Leads', data: [1284, 892, 546, 318, 186] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '70%', distributed: true } },
        dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 600 } },
        legend: { show: false },
        xaxis: { categories: ['Captured', 'Qualified', 'Proposal', 'Negotiation', 'Won'] }
      });
    });

    nxMountChart('#stageDonut', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'donut', height: 300 },
        series: [34, 28, 22, 16],
        labels: ['Qualified', 'Proposal', 'Negotiation', 'Closed Won'],
        colors: nxChartColors().slice(0, 4),
        dataLabels: { enabled: false },
        legend: { position: 'bottom' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: {
          pie: { donut: { size: '74%', labels: { show: true, total: { show: true, label: 'Deals', color: nxCss('--nx-text-muted'), formatter: function () { return '592'; } }, value: { color: nxCss('--nx-text'), fontWeight: 800 } } } }
        }
      });
    });

    // Checklist: strike-through + counter.
    $('#taskList').on('change', 'input[type="checkbox"]', function () {
      $(this).next('label').toggleClass('text-decoration-line-through text-muted', this.checked);
      var left = $('#taskList input:not(:checked)').length;
      $('#taskCount').text(left + ' left');
    });
  });
})(jQuery);
