/* NexaDash — error-maintenance.html countdown */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    var target = Date.now() + (1 * 3600 + 42 * 60 + 18) * 1000;
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
      var left = Math.max(0, target - Date.now());
      var s = Math.floor(left / 1000);
      $('#cdHours').text(pad(Math.floor(s / 3600)));
      $('#cdMinutes').text(pad(Math.floor(s % 3600 / 60)));
      $('#cdSeconds').text(pad(s % 60));
      if (left <= 0) clearInterval(timer);
    }
    var timer = setInterval(tick, 1000);
    tick();
  });
})(jQuery);
