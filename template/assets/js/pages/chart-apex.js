/* NexaDash — chart-apex.html: seluruh tipe chart, theme-aware. */
(function ($) {
  'use strict';

  var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

  function base(extra) { return $.extend(true, nxApexBase(), extra); }

  function candleData() {
    var out = [], price = 120;
    for (var i = 0; i < 30; i++) {
      var open = price;
      var close = +(open + (Math.random() * 8 - 4)).toFixed(2);
      var high = +(Math.max(open, close) + Math.random() * 3).toFixed(2);
      var low = +(Math.min(open, close) - Math.random() * 3).toFixed(2);
      out.push({ x: new Date(2026, 7, i + 1), y: [open, high, low, close] });
      price = close;
    }
    return out;
  }

  function spark(id, data, colorVar, type) {
    nxMountChart(id, function () {
      return {
        chart: { type: type || 'area', height: 60, sparkline: { enabled: true }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: 'Value', data: data }],
        colors: [nxCss(colorVar)],
        stroke: { curve: 'smooth', width: 2 },
        plotOptions: { bar: { borderRadius: 2, columnWidth: '60%' } },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        tooltip: { theme: nxTheme() }
      };
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#apexLine', function () {
      return base({
        chart: { type: 'line', height: 300 },
        series: [{ name: 'This year', data: [31, 40, 28, 51, 42, 65, 70, 62, 78, 74, 86, 92] }, { name: 'Last year', data: [22, 29, 24, 38, 34, 48, 52, 50, 58, 56, 62, 68] }],
        colors: nxChartColors().slice(0, 2),
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: { categories: MONTHS, axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#apexArea', function () {
      return base({
        chart: { type: 'area', height: 300 },
        series: [{ name: 'Revenue', data: [28, 34, 31, 42, 48, 54, 61, 68, 72, 79, 84, 92] }],
        colors: [nxCss('--nx-chart-1')],
        stroke: { curve: 'smooth', width: 2.5 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.02, stops: [0, 95] } },
        dataLabels: { enabled: false },
        xaxis: { categories: MONTHS, axisBorder: { show: false }, axisTicks: { show: false } }
      });
    });

    nxMountChart('#apexBar', function () {
      return base({
        chart: { type: 'bar', height: 300 },
        series: [{ name: 'Orders', data: [186, 204, 198, 232, 248, 261, 275, 292, 284, 301, 318, 322] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: MONTHS, axisBorder: { show: false }, axisTicks: { show: false } }
      });
    });

    nxMountChart('#apexBarH', function () {
      return base({
        chart: { type: 'bar', height: 300 },
        series: [{ name: 'Users', data: [4200, 3800, 3100, 2400, 1800, 1200] }],
        colors: [nxCss('--nx-chart-2')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '62%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Chrome', 'Safari', 'Edge', 'Firefox', 'Opera', 'Other'] }
      });
    });

    nxMountChart('#apexStacked', function () {
      return base({
        chart: { type: 'bar', height: 300, stacked: true },
        series: [
          { name: 'Direct', data: [44, 55, 41, 67, 22, 43] },
          { name: 'Affiliate', data: [13, 23, 20, 8, 13, 27] },
          { name: 'Partners', data: [11, 17, 15, 15, 21, 14] }
        ],
        colors: nxChartColors().slice(0, 3),
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#apexMixed', function () {
      return base({
        chart: { type: 'line', height: 300 },
        series: [
          { name: 'Sales', type: 'column', data: [23, 31, 28, 42, 38, 49, 55, 58] },
          { name: 'Target', type: 'line', data: [30, 32, 34, 40, 42, 46, 52, 56] }
        ],
        colors: [nxCss('--nx-chart-1'), nxCss('--nx-chart-3')],
        stroke: { width: [0, 3], curve: 'smooth' },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '46%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#apexDonut', function () {
      return base({
        chart: { type: 'donut', height: 300 },
        series: [42, 28, 18, 12],
        labels: ['Direct', 'Affiliate', 'Marketplace', 'Partners'],
        colors: nxChartColors().slice(0, 4),
        dataLabels: { enabled: false },
        legend: { position: 'bottom' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: { pie: { donut: { size: '72%', labels: { show: true, value: { color: nxCss('--nx-text') }, total: { show: true, color: nxCss('--nx-text-muted') } } } } }
      });
    });

    nxMountChart('#apexPie', function () {
      return base({
        chart: { type: 'pie', height: 300 },
        series: [38, 26, 21, 15],
        labels: ['Pro', 'Starter', 'Enterprise', 'Trial'],
        colors: nxChartColors().slice(0, 4),
        legend: { position: 'bottom' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        dataLabels: { style: { fontSize: '11px', fontWeight: 600 } }
      });
    });

    nxMountChart('#apexRadial', function () {
      return base({
        chart: { type: 'radialBar', height: 300 },
        series: [72],
        labels: ['Completion'],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { radialBar: { hollow: { size: '64%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { name: { color: nxCss('--nx-text-muted'), fontSize: '13px' }, value: { color: nxCss('--nx-text'), fontSize: '28px', fontWeight: 800 } } } }
      });
    });

    nxMountChart('#apexRadialMulti', function () {
      return base({
        chart: { type: 'radialBar', height: 300 },
        series: [82, 64, 48, 31],
        labels: ['Design', 'Backend', 'Frontend', 'QA'],
        colors: nxChartColors().slice(0, 4),
        plotOptions: { radialBar: { hollow: { size: '32%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { value: { color: nxCss('--nx-text') } } } },
        legend: { show: true, position: 'bottom', fontSize: '12px' }
      });
    });

    nxMountChart('#apexGauge', function () {
      return base({
        chart: { type: 'radialBar', height: 300, offsetY: -10 },
        series: [68],
        labels: ['Capacity'],
        colors: [nxCss('--nx-chart-4')],
        plotOptions: {
          radialBar: {
            startAngle: -135, endAngle: 135,
            hollow: { size: '62%' },
            track: { background: nxCss('--nx-secondary-subtle'), strokeWidth: '100%' },
            dataLabels: { name: { offsetY: 20, color: nxCss('--nx-text-muted'), fontSize: '13px' }, value: { offsetY: -14, color: nxCss('--nx-text'), fontSize: '30px', fontWeight: 800 } }
          }
        }
      });
    });

    nxMountChart('#apexRadar', function () {
      return base({
        chart: { type: 'radar', height: 300 },
        series: [{ name: 'Team A', data: [80, 68, 74, 62, 88, 71] }, { name: 'Team B', data: [62, 78, 58, 84, 66, 80] }],
        colors: nxChartColors().slice(0, 2),
        labels: ['Speed', 'Quality', 'Support', 'Docs', 'Pricing', 'UX'],
        stroke: { width: 2 },
        fill: { opacity: 0.15 },
        markers: { size: 3 },
        legend: { position: 'bottom' }
      });
    });

    nxMountChart('#apexHeatmap', function () {
      var days = ['Sun','Sat','Fri','Thu','Wed','Tue','Mon'];
      return base({
        chart: { type: 'heatmap', height: 320 },
        series: days.map(function (d, di) {
          return { name: d, data: Array.from({ length: 10 }, function (_, i) { return { x: 'W' + (i + 1), y: Math.round(20 + Math.sin((i + di) / 2) * 30 + di * 4) }; }) };
        }),
        colors: [nxCss('--nx-chart-1')],
        dataLabels: { enabled: false },
        plotOptions: { heatmap: { radius: 4, enableShades: true, shadeIntensity: 0.6 } }
      });
    });

    nxMountChart('#apexScatter', function () {
      return base({
        chart: { type: 'scatter', height: 320, zoom: { enabled: true, type: 'xy' } },
        series: [
          { name: 'Cohort A', data: Array.from({ length: 30 }, function () { return [+(Math.random() * 10).toFixed(1), +(Math.random() * 60 + 20).toFixed(1)]; }) },
          { name: 'Cohort B', data: Array.from({ length: 30 }, function () { return [+(Math.random() * 10).toFixed(1), +(Math.random() * 60 + 10).toFixed(1)]; }) }
        ],
        colors: nxChartColors().slice(0, 2),
        markers: { size: 5 },
        legend: { position: 'top', horizontalAlign: 'right' }
      });
    });

    nxMountChart('#apexCandle', function () {
      return base({
        chart: { type: 'candlestick', height: 340 },
        series: [{ data: candleData() }],
        xaxis: { type: 'datetime' },
        yaxis: { tooltip: { enabled: true } },
        plotOptions: { candlestick: { colors: { upward: nxCss('--nx-success'), downward: nxCss('--nx-danger') } } }
      });
    });

    spark('#sparkA', [12, 14, 13, 17, 16, 19, 21], '--nx-chart-1');
    spark('#sparkB', [820, 910, 870, 1020, 1180, 1240, 1284], '--nx-chart-2', 'bar');
    spark('#sparkC', [3.1, 2.9, 3.0, 2.6, 2.4, 2.2, 2.1], '--nx-chart-3');
    spark('#sparkD', [36, 38, 40, 42, 44, 46, 48], '--nx-chart-4');
  });
})(jQuery);
