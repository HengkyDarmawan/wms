/* NexaDash — dashboards/logistics.html */
(function ($) {
  'use strict';

  var FLEET = [
    ['NX-TR-104', 52.52, 13.405, 'En route', 'Berlin → Warsaw'],
    ['NX-VN-221', -6.2088, 106.8456, 'En route', 'Jakarta city'],
    ['NX-TR-088', 30.2672, -97.7431, 'Loading', 'Austin → Dallas'],
    ['NX-VN-310', 35.6762, 139.6503, 'Returned', 'Tokyo metro'],
    ['NX-TR-142', 41.0082, 28.9784, 'Maintenance', 'Istanbul → Ankara'],
    ['NX-VN-455', 1.2897, 103.8501, 'En route', 'Singapore port'],
    ['NX-TR-207', 51.5072, -0.1276, 'En route', 'London ring'],
    ['NX-VN-119', 43.6532, -79.3832, 'Loading', 'Toronto north']
  ];

  var STATUS_COLOR = { 'En route': '--nx-success', 'Loading': '--nx-warning', 'Returned': '--nx-secondary', 'Maintenance': '--nx-danger' };

  document.addEventListener('nx:layout-ready', function () {
    var map = L.map('fleetMap', { scrollWheelZoom: false }).setView([25, 40], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    FLEET.forEach(function (v) {
      var color = nxCss(STATUS_COLOR[v[3]]);
      L.circleMarker([v[1], v[2]], {
        radius: 8, color: color, weight: 2, fillColor: color, fillOpacity: 0.55
      }).addTo(map).bindPopup('<strong>' + v[0] + '</strong><br>' + v[4] + '<br>' + v[3]);
    });
    setTimeout(function () { map.invalidateSize(); }, 300);

    nxMountChart('#fleetDonut', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'donut', height: 230 },
        series: [42, 6, 5, 3],
        labels: ['En route', 'Loading', 'Idle', 'Maintenance'],
        colors: [nxCss('--nx-success'), nxCss('--nx-warning'), nxCss('--nx-secondary'), nxCss('--nx-danger')],
        dataLabels: { enabled: false },
        legend: { position: 'bottom', fontSize: '12px' },
        stroke: { colors: [nxCss('--nx-card-bg')] },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, value: { color: nxCss('--nx-text'), fontWeight: 800 }, total: { show: true, label: 'Vehicles', color: nxCss('--nx-text-muted'), formatter: function () { return '56'; } } } } } }
      });
    });

    nxMountChart('#deliveryChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'bar', height: 280 },
        series: [{ name: 'Deliveries', data: [12, 28, 64, 98, 126, 141, 132, 118, 96, 74, 48, 22] }],
        colors: [nxCss('--nx-chart-2')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: '58%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: ['6a','7a','8a','9a','10a','11a','12p','1p','2p','3p','4p','5p'], axisBorder: { show: false }, axisTicks: { show: false } }
      });
    });
  });
})(jQuery);
