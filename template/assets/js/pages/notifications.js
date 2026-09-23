/* NexaDash — page-notifications.html */
(function ($) {
  'use strict';

  function counts() {
    $('#cntAll').text($('#notifList .nx-notif-item').length);
    $('#cntUnread').text($('#notifList .nx-notif-item.unread').length);
    $('#cntMention').text($('#notifList .nx-notif-item[data-type="mention"]').length);
  }

  function applyFilter(f) {
    $('#notifList .nx-notif-item').each(function () {
      var show = f === 'all' ||
        (f === 'unread' && $(this).hasClass('unread')) ||
        (f === 'mention' && $(this).data('type') === 'mention');
      $(this).toggle(show);
    });
    // Sembunyikan header hari yang kosong.
    $('.nx-activity-day').each(function () {
      var $next = $(this).nextUntil('.nx-activity-day', '.nx-notif-item');
      $(this).toggle($next.filter(':visible').length > 0);
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    counts();

    $('.nav-link[data-filter]').on('click', function () {
      $('.nav-link[data-filter]').removeClass('active');
      $(this).addClass('active');
      applyFilter($(this).data('filter'));
    });

    $('#notifList').on('click', '.btn-mark', function (e) {
      e.stopPropagation();
      $(this).closest('.nx-notif-item').removeClass('unread');
      $(this).remove();
      counts();
    });

    $('#markAllRead').on('click', function () {
      $('#notifList .nx-notif-item').removeClass('unread');
      $('.btn-mark').remove();
      counts();
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'All marked as read', showConfirmButton: false, timer: 1800 });
    });
  });
})(jQuery);
