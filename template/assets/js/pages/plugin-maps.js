/* NexaDash — plugins/maps.html (Leaflet + markercluster + heat) */
(function ($) {
  'use strict';

  var LOCATIONS = [
    ['Singapore HQ', 1.2897, 103.8501, 'Asia Pacific', 184],
    ['Jakarta', -6.2088, 106.8456, 'Asia Pacific', 126],
    ['Tokyo', 35.6762, 139.6503, 'Asia Pacific', 98],
    ['Berlin', 52.52, 13.405, 'Europe', 142],
    ['London', 51.5072, -0.1276, 'Europe', 211],
    ['Warsaw', 52.2297, 21.0122, 'Europe', 64],
    ['Austin', 30.2672, -97.7431, 'Americas', 158],
    ['Toronto', 43.6532, -79.3832, 'Americas', 87],
    ['São Paulo', -23.5558, -46.6396, 'Americas', 73]
  ];

  /* Hanya penyedia tile yang benar-benar bebas kunci API. Carto Basemaps kini
     mencetak watermark "API key required", jadi sengaja tidak dipakai. */
  function tiles() {
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    });
  }
  function tilesHumanitarian() {
    return L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors, Humanitarian OSM Team'
    });
  }
  function tilesTopo() {
    return L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17,
      attribution: '&copy; OpenStreetMap contributors, SRTM &mdash; style &copy; OpenTopoMap'
    });
  }
  // Peta sekunder memakai basemap standar yang sama supaya tampil konsisten.
  var tilesLight = tiles;

  // Titik acak tapi deterministik di sekitar tiap kota, untuk cluster & heatmap.
  function scatter() {
    var pts = [];
    var seed = 7;
    var rnd = function () { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
    LOCATIONS.forEach(function (l) {
      for (var i = 0; i < 55; i++) {
        pts.push([l[1] + (rnd() - 0.5) * 8, l[2] + (rnd() - 0.5) * 8, rnd()]);
      }
    });
    return pts;
  }

  document.addEventListener('nx:layout-ready', function () {

    /* ===== Peta utama ===== */
    var main = L.map('mapMain', { scrollWheelZoom: false }).setView([20, 40], 2);
    var osm = tiles().addTo(main);
    L.control.layers({
      'OpenStreetMap': osm,
      'Humanitarian': tilesHumanitarian(),
      'OpenTopoMap': tilesTopo()
    }).addTo(main);

    var markers = [];
    LOCATIONS.forEach(function (l, i) {
      var m = L.marker([l[1], l[2]]).addTo(main)
        .bindPopup('<strong>' + l[0] + '</strong><br>' + l[3] + '<br>' + l[4] + ' customers');
      markers.push(m);
    });

    // Daftar lokasi di samping peta.
    $('#locList').html(LOCATIONS.map(function (l, i) {
      return '<div class="nx-row-item loc-item" role="button" data-i="' + i + '">' +
        '<span class="nx-icon-sq primary"><i class="bi bi-geo-alt"></i></span>' +
        '<div class="nx-row-main"><div class="nx-row-title">' + l[0] + '</div>' +
        '<div class="nx-row-sub">' + l[3] + '</div></div>' +
        '<span class="badge badge-soft-secondary">' + l[4] + '</span></div>';
    }).join(''));
    $('#locCount').text(LOCATIONS.length);

    $('#locList').on('click', '.loc-item', function () {
      var i = +$(this).data('i');
      main.flyTo([LOCATIONS[i][1], LOCATIONS[i][2]], 6, { duration: 0.8 });
      markers[i].openPopup();
    });

    /* ===== Clustering ===== */
    var cluster = L.map('mapCluster', { scrollWheelZoom: false }).setView([20, 40], 2);
    tilesLight().addTo(cluster);
    var group = L.markerClusterGroup({ chunkedLoading: true });
    scatter().forEach(function (p) { group.addLayer(L.marker([p[0], p[1]])); });
    cluster.addLayer(group);

    /* ===== Heatmap ===== */
    var heat = L.map('mapHeat', { scrollWheelZoom: false }).setView([20, 40], 2);
    tilesLight().addTo(heat);
    L.heatLayer(scatter(), { radius: 22, blur: 18, maxZoom: 6 }).addTo(heat);

    /* ===== Bentuk & wilayah ===== */
    var shapes = L.map('mapShapes', { scrollWheelZoom: false }).setView([48, 10], 4);
    tilesLight().addTo(shapes);
    L.polygon([[54.9, 5.9], [54.9, 15.0], [47.3, 15.0], [47.3, 5.9]], {
      color: nxCss('--nx-primary'), weight: 2, fillOpacity: 0.15
    }).addTo(shapes).bindTooltip('DACH region — 142 customers');
    L.circle([51.5072, -0.1276], {
      radius: 220000, color: nxCss('--nx-success'), weight: 2, fillOpacity: 0.15
    }).addTo(shapes).bindTooltip('Greater London — 211 customers');
    L.polyline([[52.52, 13.405], [52.2297, 21.0122], [51.5072, -0.1276]], {
      color: nxCss('--nx-warning'), weight: 3, dashArray: '6 6'
    }).addTo(shapes).bindTooltip('Field team route');

    /* ===== Pilih titik ===== */
    var picker = L.map('mapPicker', { scrollWheelZoom: false }).setView([1.2897, 103.8501], 11);
    tilesLight().addTo(picker);
    var pin = null;
    picker.on('click', function (e) {
      if (pin) picker.removeLayer(pin);
      pin = L.marker(e.latlng).addTo(picker);
      $('#pickLat').text(e.latlng.lat.toFixed(5));
      $('#pickLng').text(e.latlng.lng.toFixed(5));
    });
    $('#pickReset').on('click', function () {
      if (pin) { picker.removeLayer(pin); pin = null; }
      $('#pickLat, #pickLng').text('—');
    });

    // Leaflet mengukur kontainer saat dibuat; paksa hitung ulang setelah layout stabil.
    setTimeout(function () {
      [main, cluster, heat, shapes, picker].forEach(function (m) { m.invalidateSize(); });
    }, 300);
  });
})(jQuery);
