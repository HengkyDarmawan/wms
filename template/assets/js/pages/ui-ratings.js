/* NexaDash — ui/ratings.html */
(function ($) {
  'use strict';

  var WORDS = { 1: 'Terrible', 2: 'Poor', 3: 'Average', 4: 'Good', 5: 'Excellent' };

  document.addEventListener('nx:layout-ready', function () {
    $('#rateMain input').on('change', function () {
      $('#rateLabel').text(this.value + ' of 5 — ' + WORDS[this.value]);
    });

    $('#rateSubmit').on('click', function () {
      var main = $('#rateMain input:checked').val();
      if (!main) {
        $('#rateLabel').addClass('text-danger').text('Pick a star rating first.');
        return;
      }
      var parts = ['rateA', 'rateB', 'rateC'].map(function (n) {
        return $('input[name="' + n + '"]:checked').val() || '—';
      });
      $('#rateLabel')
        .removeClass('text-danger')
        .addClass('text-success')
        .text('Submitted: ' + main + '/5 overall, ' + parts.join(' / ') + ' for ease, support and value.');
      $(this).prop('disabled', true).text('Thanks for rating');
    });
  });
})(jQuery);
