/* NexaDash — apps/event-detail.html */
(function ($) {
  'use strict';

  var PRICE = 490;
  var map = null;

  document.addEventListener('nx:layout-ready', function () {
    function total() {
      var q = parseInt($('#evQty').val(), 10) || 1;
      $('#evTotal').text('$' + (q * PRICE).toLocaleString('en-US', { minimumFractionDigits: 2 }));
    }
    $('#evPlus').on('click', function () { $('#evQty').val(Math.min(20, +$('#evQty').val() + 1)); total(); });
    $('#evMinus').on('click', function () { $('#evQty').val(Math.max(1, +$('#evQty').val() - 1)); total(); });
    total();

    $('#evRegister').on('click', function () {
      Swal.fire({
        icon: 'success', title: 'You are registered',
        text: $('#evQty').val() + ' ticket(s) for NexaCon 2026',
        confirmButtonColor: nxCss('--nx-primary')
      });
    });
    $('#evSave').on('click', function () {
      var saved = $(this).hasClass('btn-soft-primary');
      $(this).toggleClass('btn-soft-secondary btn-soft-primary')
        .html(saved ? '<i class="bi bi-bookmark me-1"></i>Save for later' : '<i class="bi bi-bookmark-fill me-1"></i>Saved');
    });

    // Peta venue baru diukur setelah tabnya terlihat.
    $('[data-bs-target="#evVenue"]').on('shown.bs.tab', function () {
      if (!map) {
        map = L.map('venueMap', { scrollWheelZoom: false }).setView([1.2834, 103.8607], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        L.marker([1.2834, 103.8607]).addTo(map).bindPopup('<strong>Marina Bay Sands</strong><br>Expo &amp; Convention Centre, Level 4').openPopup();
      }
      map.invalidateSize();
    });
  });
})(jQuery);
