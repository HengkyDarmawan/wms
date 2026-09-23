/* NexaDash — apps/course-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    // Tandai pelajaran selesai saat diklik.
    $('.lesson').on('click', function () {
      if ($(this).find('.bi-lock').length) return;
      var $icon = $(this).find('i.bi').first();
      var done = $(this).hasClass('done');
      $(this).toggleClass('done');
      $icon.attr('class', done ? 'bi bi-circle text-muted' : 'bi bi-check-circle-fill text-success');
      $('#cdDone').text($('.lesson.done').length);
    });

    $('#cdPlay, #cdResume').on('click', function () {
      $('[data-bs-target="#cdM2"]').filter('.collapsed').trigger('click');
      var el = document.querySelector('.lesson.current');
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    $('#cdDownload').on('click', function () {
      var $b = $(this);
      $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Preparing&hellip;');
      setTimeout(function () {
        $b.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Resources ready');
      }, 1400);
    });
  });
})(jQuery);
