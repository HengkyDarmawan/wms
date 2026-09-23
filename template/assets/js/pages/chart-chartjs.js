/* NexaDash — chart-chartjs.html */
(function ($) {
  'use strict';

  var charts = [];
  var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'];

  function build() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];

    var C = nxChartColors();
    var grid = nxCss('--nx-border');
    var text = nxCss('--nx-text-muted');
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = text;

    var axes = { x: { grid: { color: grid }, ticks: { color: text } }, y: { grid: { color: grid }, ticks: { color: text } } };
    var legend = { legend: { labels: { color: text, usePointStyle: true, boxWidth: 8 } } };

    charts.push(new Chart(document.getElementById('cjsLine'), {
      type: 'line',
      data: {
        labels: MONTHS,
        datasets: [
          { label: 'Revenue', data: [31, 40, 28, 51, 42, 65, 70, 78], borderColor: C[0], backgroundColor: C[0] + '33', tension: 0.4, fill: true, pointRadius: 0, borderWidth: 3 },
          { label: 'Expenses', data: [22, 27, 21, 34, 30, 42, 46, 50], borderColor: C[1], backgroundColor: 'transparent', tension: 0.4, pointRadius: 0, borderWidth: 3 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: legend, scales: axes }
    }));

    charts.push(new Chart(document.getElementById('cjsBar'), {
      type: 'bar',
      data: { labels: MONTHS, datasets: [{ label: 'Orders', data: [186, 204, 198, 232, 248, 261, 275, 292], backgroundColor: C[0], borderRadius: 6, maxBarThickness: 28 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: legend, scales: axes }
    }));

    charts.push(new Chart(document.getElementById('cjsDoughnut'), {
      type: 'doughnut',
      data: { labels: ['Direct', 'Affiliate', 'Marketplace', 'Partners'], datasets: [{ data: [42, 28, 18, 12], backgroundColor: C.slice(0, 4), borderColor: nxCss('--nx-card-bg'), borderWidth: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 8 } } } }
    }));

    charts.push(new Chart(document.getElementById('cjsPolar'), {
      type: 'polarArea',
      data: { labels: ['Design', 'Backend', 'Frontend', 'QA', 'Ops'], datasets: [{ data: [24, 18, 22, 12, 9], backgroundColor: C.map(function (c) { return c + 'cc'; }) }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 8 } } }, scales: { r: { grid: { color: grid }, ticks: { color: text, backdropColor: 'transparent' } } } }
    }));

    charts.push(new Chart(document.getElementById('cjsRadar'), {
      type: 'radar',
      data: {
        labels: ['Speed', 'Quality', 'Support', 'Docs', 'Pricing', 'UX'],
        datasets: [
          { label: 'Team A', data: [80, 68, 74, 62, 88, 71], borderColor: C[0], backgroundColor: C[0] + '33', borderWidth: 2, pointRadius: 3 },
          { label: 'Team B', data: [62, 78, 58, 84, 66, 80], borderColor: C[1], backgroundColor: C[1] + '33', borderWidth: 2, pointRadius: 3 }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 8 } } }, scales: { r: { grid: { color: grid }, angleLines: { color: grid }, pointLabels: { color: text }, ticks: { color: text, backdropColor: 'transparent' } } } }
    }));

    charts.push(new Chart(document.getElementById('cjsBubble'), {
      type: 'bubble',
      data: {
        datasets: [
          { label: 'Cohort A', data: Array.from({ length: 12 }, function () { return { x: +(Math.random() * 20).toFixed(1), y: +(Math.random() * 60 + 10).toFixed(1), r: Math.round(Math.random() * 16 + 4) }; }), backgroundColor: C[0] + 'aa' },
          { label: 'Cohort B', data: Array.from({ length: 12 }, function () { return { x: +(Math.random() * 20).toFixed(1), y: +(Math.random() * 60 + 10).toFixed(1), r: Math.round(Math.random() * 16 + 4) }; }), backgroundColor: C[1] + 'aa' }
        ]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: legend, scales: axes }
    }));
  }

  document.addEventListener('nx:layout-ready', build);
  document.addEventListener('nx:theme-changed', build);
})(jQuery);
