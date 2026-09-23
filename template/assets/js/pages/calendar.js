/* NexaDash — apps-calendar.html */
(function ($) {
  'use strict';

  var calendar = null;
  var CAT_COLOR = { meeting: '--nx-primary', deadline: '--nx-danger', holiday: '--nx-success' };

  function d(day) {
    var now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), day).toISOString().slice(0, 10);
  }

  var EVENTS = [
    { title: 'Sprint planning', start: d(2), category: 'meeting' },
    { title: 'Design review', start: d(4), category: 'meeting' },
    { title: 'Q3 report due', start: d(6), category: 'deadline' },
    { title: 'All-hands', start: d(9), category: 'meeting' },
    { title: 'Release v2.4.1', start: d(11), category: 'deadline' },
    { title: 'Customer QBR — Pulse Co.', start: d(14), category: 'meeting' },
    { title: 'Company holiday', start: d(17), category: 'holiday' },
    { title: 'Security audit', start: d(19), end: d(21), category: 'deadline' },
    { title: 'Team offsite', start: d(24), end: d(26), category: 'holiday' },
    { title: 'Board meeting', start: d(28), category: 'meeting' }
  ];

  function colored(ev) {
    var c = nxCss(CAT_COLOR[ev.category] || '--nx-primary');
    return $.extend({}, ev, { backgroundColor: c, borderColor: c, textColor: '#fff' });
  }

  function renderUpcoming() {
    var html = EVENTS.slice(0, 5).map(function (ev) {
      var c = nxCss(CAT_COLOR[ev.category]);
      return '<div class="nx-row-item"><span class="nx-dot-label" style="background:' + c + '"></span>' +
        '<div class="nx-row-main"><div class="nx-row-title">' + $('<span>').text(ev.title).html() + '</div>' +
        '<div class="nx-row-sub">' + new Date(ev.start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + '</div></div></div>';
    }).join('');
    $('#upcomingList').html(html);
  }

  function build() {
    var el = document.getElementById('calendar');
    if (calendar) calendar.destroy();
    calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      height: 760,
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
      editable: true,
      dayMaxEvents: 3,
      events: EVENTS.map(colored),
      dateClick: function (info) {
        $('#evDate').val(info.dateStr);
        new bootstrap.Modal(document.getElementById('eventModal')).show();
      },
      eventClick: function (info) {
        Swal.fire({ title: info.event.title, text: info.event.start.toDateString(), icon: 'info', confirmButtonColor: nxCss('--nx-primary') });
      }
    });
    calendar.render();
  }

  document.addEventListener('nx:layout-ready', function () {
    build();
    renderUpcoming();

    flatpickr('#evDate', { dateFormat: 'Y-m-d', defaultDate: new Date() });
    $('#evCat').select2({ theme: 'bootstrap-5', dropdownParent: $('#eventModal'), minimumResultsForSearch: -1 });

    $('#btnSaveEvent').on('click', function () {
      var title = $('#evTitle').val().trim() || 'Untitled event';
      var cat = $('#evCat').val();
      var date = $('#evDate').val();
      EVENTS.push({ title: title, start: date, category: cat });
      calendar.addEvent(colored({ title: title, start: date, category: cat }));
      renderUpcoming();
      bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
      $('#evTitle').val('');
    });

    // Filter kategori.
    $('.cal-filter').on('change', function () {
      var active = $('.cal-filter:checked').map(function () { return this.value; }).get();
      calendar.removeAllEvents();
      EVENTS.filter(function (e) { return active.indexOf(e.category) > -1; }).forEach(function (e) { calendar.addEvent(colored(e)); });
    });
  });

  document.addEventListener('nx:theme-changed', function () {
    if (calendar) { build(); renderUpcoming(); }
  });
})(jQuery);
