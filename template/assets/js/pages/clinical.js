/* NexaDash — dashboard-clinical.html (gaya Ember) */
(function ($) {
  'use strict';

  var series = [];
  var t = 0;
  var vitalsChart = null;

  function nextPoint() {
    // Simulasi detak jantung 70-86 bpm dengan variasi halus.
    t += 1;
    return Math.round(78 + Math.sin(t / 4) * 5 + (Math.random() * 4 - 2));
  }

  function seedSeries() {
    series = [];
    for (var i = 0; i < 40; i++) series.push({ x: new Date().getTime() - (40 - i) * 2000, y: nextPoint() });
  }

  document.addEventListener('nx:layout-ready', function () {
    seedSeries();

    vitalsChart = nxMountChart('#vitalsChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 300, animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 900 } } },
        series: [{ name: 'Heart rate', data: series.slice() }],
        colors: [nxCss('--nx-danger')],
        stroke: { curve: 'smooth', width: 2.5 },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        xaxis: { type: 'datetime', labels: { datetimeUTC: false, format: 'HH:mm:ss' }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { min: 60, max: 100, tickAmount: 4, labels: { formatter: function (v) { return Math.round(v) + ' bpm'; } } },
        annotations: {
          yaxis: [
            { y: 60, y2: 100, borderColor: 'transparent', fillColor: nxCss('--nx-success'), opacity: 0.05 }
          ]
        }
      });
    });

    // Update realtime tiap 2 detik.
    setInterval(function () {
      var el = document.querySelector('#vitalsChart');
      if (!el) return;
      var y = nextPoint();
      series.push({ x: new Date().getTime(), y: y });
      if (series.length > 40) series.shift();
      $('#vitalHR').text(y + ' bpm');
      if (window.ApexCharts) ApexCharts.exec(el.id, 'updateSeries', [{ data: series.slice() }], true);
    }, 2000);

    nxMountChart('#deptChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 220 },
        series: [{ name: 'Patients', data: [42, 31, 27, 18, 14] }],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '52%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: { categories: ['Emerg.', 'Cardio', 'Pedia', 'Ortho', 'Neuro'], axisBorder: { show: false }, axisTicks: { show: false } }
      });
    });
  });
})(jQuery);
