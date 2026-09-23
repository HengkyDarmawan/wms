/* NexaDash — errors/coming-soon.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {
    var target = Date.now() + ((21 * 24 + 8) * 3600 + 42 * 60 + 18) * 1000;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
      var left = Math.max(0, target - Date.now());
      var s = Math.floor(left / 1000);
      $('#csDays').text(pad(Math.floor(s / 86400)));
      $('#csHours').text(pad(Math.floor(s % 86400 / 3600)));
      $('#csMinutes').text(pad(Math.floor(s % 3600 / 60)));
      $('#csSeconds').text(pad(s % 60));
      if (left <= 0) clearInterval(timer);
    }
    var timer = setInterval(tick, 1000);
    tick();

    $('#csForm').on('submit', function (e) {
      e.preventDefault();
      var email = $('#csEmail');
      if (!email[0].checkValidity()) {
        email.addClass('is-invalid');
        $('#csMsg').attr('class', 'small mt-2 text-danger').text('Enter a valid email address.');
        return;
      }
      email.removeClass('is-invalid');
      $('#csMsg').attr('class', 'small mt-2 text-success').text('You are on the list — we will email ' + email.val() + ' when a slot opens.');
      $(this).find('button').prop('disabled', true).text('Added');
    });
  });
})(jQuery);
