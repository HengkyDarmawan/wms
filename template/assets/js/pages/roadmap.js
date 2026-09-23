/* NexaDash — page-roadmap.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('.nx-vote-btn').on('click', function () {
      var $count = $(this).find('.vote-count');
      var n = parseInt($count.text(), 10);
      var voted = $(this).hasClass('voted');
      $(this).toggleClass('voted');
      $count.text(voted ? n - 1 : n + 1);
    });
  });
})(jQuery);
