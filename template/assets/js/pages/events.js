/* NexaDash — apps/events.html */
(function ($) {
  'use strict';

  var TYPE_COLOR = { Conference: 'primary', Webinar: 'info', Workshop: 'warning', Internal: 'secondary' };

  var EVENTS = [
    { title: 'NexaCon 2026', type: 'Conference', date: 'Oct 14–16, 2026', place: 'Marina Bay Sands, Singapore', seats: 2400, sold: 1860, price: '$490', icon: 'bi-mic', grad: 'linear-gradient(135deg,#6366f1,#312e81)' },
    { title: 'Analytics Deep Dive', type: 'Webinar', date: 'Sep 24, 2026 · 15:00 SGT', place: 'Online', seats: 5000, sold: 2140, price: 'Free', icon: 'bi-camera-video', grad: 'linear-gradient(135deg,#0ea5e9,#0369a1)' },
    { title: 'Design Systems Workshop', type: 'Workshop', date: 'Oct 2, 2026 · 09:00', place: 'Berlin office', seats: 40, sold: 38, price: '$120', icon: 'bi-palette', grad: 'linear-gradient(135deg,#f59e0b,#b45309)' },
    { title: 'Team Offsite', type: 'Internal', date: 'Sep 24–26, 2026', place: 'Bintan, Indonesia', seats: 120, sold: 104, price: '—', icon: 'bi-people', grad: 'linear-gradient(135deg,#10b981,#047857)' },
    { title: 'Billing API Office Hours', type: 'Webinar', date: 'Oct 8, 2026 · 17:00 CET', place: 'Online', seats: 500, sold: 186, price: 'Free', icon: 'bi-credit-card', grad: 'linear-gradient(135deg,#22d3ee,#0891b2)' },
    { title: 'Security & Compliance Briefing', type: 'Internal', date: 'Oct 21, 2026 · 11:00', place: 'Singapore HQ', seats: 80, sold: 24, price: '—', icon: 'bi-shield-lock', grad: 'linear-gradient(135deg,#f472b6,#be185d)' }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function card(e) {
    var pct = Math.round(e.sold / e.seats * 100);
    return '<div class="col-xl-4 col-md-6 ev-item" data-type="' + e.type + '">' +
      '<div class="card h-100 nx-card-lift">' +
      '<div class="nx-course-cover" style="background:' + e.grad + '"><i class="bi ' + e.icon + '"></i></div>' +
      '<div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-center gap-2 mb-2">' +
      '<span class="badge badge-soft-' + TYPE_COLOR[e.type] + '">' + esc(e.type) + '</span>' +
      '<span class="ms-auto fw-semibold">' + esc(e.price) + '</span></div>' +
      '<h3 class="h5 mb-1">' + esc(e.title) + '</h3>' +
      '<p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i>' + esc(e.date) + '<br>' +
      '<i class="bi bi-geo-alt me-1"></i>' + esc(e.place) + '</p>' +
      '<div class="mt-auto">' +
      '<div class="progress progress-thin mb-2" role="progressbar" aria-label="Seats" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
      '<div class="progress-bar' + (pct > 90 ? ' bg-danger' : '') + '" style="width:' + pct + '%"></div></div>' +
      '<div class="d-flex justify-content-between small text-muted mb-3"><span>' + e.sold.toLocaleString() + ' registered</span><span>' + e.seats.toLocaleString() + ' seats</span></div>' +
      '<a class="btn btn-soft-primary w-100" href="apps-event-detail.html">View event</a>' +
      '</div></div></div></div>';
  }

  document.addEventListener('nx:layout-ready', function () {
    var type = 'all';

    function render() {
      var q = ($('#evSearch').val() || '').toLowerCase();
      var list = EVENTS.filter(function (e) {
        return (type === 'all' || e.type === type) &&
          (e.title + ' ' + e.place + ' ' + e.type).toLowerCase().indexOf(q) > -1;
      });
      $('#evGrid').html(list.length ? list.map(card).join('')
        : '<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-calendar-x d-block fs-3 mb-2"></i>No events match that filter.</div></div></div>');
      $('#evCount').text(list.length);
    }

    render();
    $('#evSearch').on('input', render);
    $('#evFilters').on('click', 'button', function () {
      $('#evFilters button').removeClass('btn-soft-primary active').addClass('btn-soft-secondary');
      $(this).removeClass('btn-soft-secondary').addClass('btn-soft-primary active');
      type = $(this).data('type');
      render();
    });
  });
})(jQuery);
