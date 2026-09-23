/* NexaDash — apps/courses.html */
(function ($) {
  'use strict';

  var TRACK = {
    Analytics: { color: 'primary', grad: 'linear-gradient(135deg,#6366f1,#312e81)', icon: 'bi-bar-chart' },
    Data: { color: 'success', grad: 'linear-gradient(135deg,#10b981,#047857)', icon: 'bi-database' },
    Design: { color: 'warning', grad: 'linear-gradient(135deg,#f59e0b,#b45309)', icon: 'bi-palette' },
    Security: { color: 'info', grad: 'linear-gradient(135deg,#0ea5e9,#0369a1)', icon: 'bi-shield-lock' }
  };

  var COURSES = [
    { title: 'Building Dashboards That People Trust', track: 'Analytics', level: 'Intermediate', hours: 6.7, lessons: 12, students: 1284, rating: 5, tutor: 'Sarah Chen', img: 47, progress: 64, enrolled: true, age: 2 },
    { title: 'SQL for Product Analysts', track: 'Data', level: 'Beginner', hours: 8.2, lessons: 18, students: 986, rating: 4.5, tutor: 'Emma Wilson', img: 24, progress: 0, enrolled: false, age: 5 },
    { title: 'Design Systems from Scratch', track: 'Design', level: 'Intermediate', hours: 5.4, lessons: 10, students: 742, rating: 4, tutor: 'Lena Iversen', img: 32, progress: 22, enrolled: true, age: 1 },
    { title: 'Security Basics for Engineers', track: 'Security', level: 'Beginner', hours: 4.1, lessons: 9, students: 618, rating: 3.5, tutor: 'Olivia Brown', img: 44, progress: 0, enrolled: false, age: 8 },
    { title: 'Advanced Chart Design', track: 'Analytics', level: 'Advanced', hours: 3.8, lessons: 7, students: 412, rating: 5, tutor: 'Ava Novak', img: 5, progress: 100, enrolled: true, age: 4 },
    { title: 'Warehouse Modelling in Practice', track: 'Data', level: 'Advanced', hours: 11.5, lessons: 24, students: 386, rating: 4.5, tutor: 'Ravi Menon', img: 52, progress: 0, enrolled: false, age: 3 },
    { title: 'Accessible Interfaces', track: 'Design', level: 'Intermediate', hours: 4.6, lessons: 11, students: 528, rating: 4.5, tutor: 'Sofia Rossi', img: 26, progress: 8, enrolled: true, age: 6 },
    { title: 'Threat Modelling Workshops', track: 'Security', level: 'Advanced', hours: 6.0, lessons: 8, students: 214, rating: 4, tutor: 'Piotr Nowak', img: 57, progress: 0, enrolled: false, age: 7 },
    { title: 'Metrics That Survive a Reorg', track: 'Analytics', level: 'Beginner', hours: 2.9, lessons: 6, students: 894, rating: 4, tutor: 'Nadia Rahman', img: 36, progress: 0, enrolled: false, age: 9 }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function stars(n) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
      out += n >= i ? '<i class="bi bi-star-fill"></i>' : (n >= i - 0.5 ? '<i class="bi bi-star-half"></i>' : '<i class="bi bi-star"></i>');
    }
    return '<span class="nx-stars sm">' + out + '</span>';
  }

  function card(c) {
    var t = TRACK[c.track];
    return '<div class="col-xl-4 col-md-6">' +
      '<div class="card h-100 nx-card-lift">' +
      '<div class="nx-course-cover" style="background:' + t.grad + '"><i class="bi ' + t.icon + '"></i></div>' +
      '<div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-center gap-2 mb-2">' +
      '<span class="badge badge-soft-' + t.color + '">' + esc(c.track) + '</span>' +
      '<span class="badge badge-soft-secondary">' + esc(c.level) + '</span>' +
      (c.enrolled ? '<span class="badge badge-soft-success ms-auto">Enrolled</span>' : '') +
      '</div>' +
      '<h3 class="h5 mb-2">' + esc(c.title) + '</h3>' +
      '<div class="d-flex align-items-center gap-2 mb-2">' + stars(c.rating) +
      '<span class="small text-muted">' + c.rating + ' &middot; ' + c.students.toLocaleString() + ' students</span></div>' +
      '<div class="d-flex align-items-center gap-2 small text-muted mb-3">' +
      '<span class="nx-avatar nx-avatar-xs"><img src="https://i.pravatar.cc/40?img=' + c.img + '" alt=""></span>' + esc(c.tutor) + '</div>' +
      '<div class="d-flex justify-content-between small text-muted mb-3">' +
      '<span><i class="bi bi-play-circle me-1"></i>' + c.lessons + ' lessons</span>' +
      '<span><i class="bi bi-clock me-1"></i>' + c.hours + ' h</span></div>' +
      (c.enrolled
        ? '<div class="mt-auto"><div class="progress progress-thin mb-2" role="progressbar" aria-label="Progress" aria-valuenow="' + c.progress + '" aria-valuemin="0" aria-valuemax="100">' +
          '<div class="progress-bar' + (c.progress === 100 ? ' bg-success' : '') + '" style="width:' + c.progress + '%"></div></div>' +
          '<div class="small text-muted mb-3">' + (c.progress === 100 ? 'Completed' : c.progress + '% complete') + '</div>' +
          '<a class="btn btn-primary w-100" href="apps-course-detail.html">' + (c.progress === 100 ? 'Review course' : 'Continue') + '</a></div>'
        : '<div class="mt-auto"><a class="btn btn-soft-primary w-100" href="apps-course-detail.html">View course</a></div>') +
      '</div></div></div>';
  }

  document.addEventListener('nx:layout-ready', function () {
    function render() {
      var q = ($('#crSearch').val() || '').toLowerCase();
      var tracks = $('.cr-track:checked').map(function () { return this.value; }).get();
      var level = $('.cr-level:checked').val();
      var onlyMine = $('#crEnrolled').is(':checked');

      var list = COURSES.filter(function (c) {
        return tracks.indexOf(c.track) > -1 &&
          (!level || c.level === level) &&
          (!onlyMine || c.enrolled) &&
          (c.title + ' ' + c.tutor).toLowerCase().indexOf(q) > -1;
      });

      var sort = $('#crSort').val();
      list.sort(function (a, b) {
        if (sort === 'Highest rated') return b.rating - a.rating;
        if (sort === 'Newest') return a.age - b.age;
        if (sort === 'Shortest first') return a.hours - b.hours;
        return b.students - a.students;
      });

      $('#crGrid').html(list.length ? list.map(card).join('')
        : '<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x d-block fs-3 mb-2"></i>No courses match those filters.</div></div></div>');
      $('#crCount').text(list.length);
    }

    render();
    $('#crSearch').on('input', render);
    $('.cr-track, .cr-level, #crEnrolled, #crSort').on('change', render);
    $('#crClear').on('click', function () {
      $('.cr-track').prop('checked', true);
      $('#lvAll').prop('checked', true);
      $('#crEnrolled').prop('checked', false);
      $('#crSearch').val('');
      render();
    });
  });
})(jQuery);
