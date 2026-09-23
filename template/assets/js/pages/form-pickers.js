/* NexaDash — form-pickers.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {
    // ===== Flatpickr =====
    flatpickr('#pkDate', { dateFormat: 'M j, Y', defaultDate: new Date() });
    flatpickr('#pkRange', { mode: 'range', dateFormat: 'M j, Y' });
    flatpickr('#pkDateTime', { enableTime: true, dateFormat: 'M j, Y H:i' });
    flatpickr('#pkTime', { enableTime: true, noCalendar: true, dateFormat: 'H:i', defaultDate: '09:30' });
    flatpickr('#pkMulti', { mode: 'multiple', dateFormat: 'M j' });
    flatpickr('#pkInline', { inline: true, defaultDate: new Date() });

    // ===== noUiSlider =====
    var s1 = document.getElementById('sl1');
    noUiSlider.create(s1, { start: 50, connect: [true, false], range: { min: 0, max: 100 }, format: { to: Math.round, from: Number } });
    s1.noUiSlider.on('update', function (v) { $('#sl1Val').text(v[0]); });

    var s2 = document.getElementById('sl2');
    noUiSlider.create(s2, { start: [20, 80], connect: true, range: { min: 0, max: 100 }, format: { to: Math.round, from: Number } });
    s2.noUiSlider.on('update', function (v) { $('#sl2Val').text(v[0] + ' – ' + v[1]); });

    var s3 = document.getElementById('sl3');
    noUiSlider.create(s3, {
      start: [30, 70], connect: true, step: 10, range: { min: 0, max: 100 },
      tooltips: [true, true], format: { to: Math.round, from: Number }
    });

    var s4 = document.getElementById('sl4');
    noUiSlider.create(s4, {
      start: [500000, 5000000], connect: true, step: 100000, range: { min: 0, max: 10000000 },
      format: { to: Math.round, from: Number }
    });
    s4.noUiSlider.on('update', function (v) {
      $('#sl4Min').text('Rp ' + (+v[0]).toLocaleString('id-ID'));
      $('#sl4Max').text('Rp ' + (+v[1]).toLocaleString('id-ID'));
    });

    // ===== Colour =====
    function setColor(c) {
      $('#pkColor').val(c);
      $('#pkColorVal').text(c);
      $('#pkPreview').css('background', c);
    }
    $('#pkColor').on('input', function () {
      setColor(this.value);
      $('#pkPresets .nx-thumb').removeClass('active');
    });
    $('#pkPresets').on('click', '.nx-thumb', function () {
      $('#pkPresets .nx-thumb').removeClass('active');
      $(this).addClass('active');
      setColor($(this).data('color'));
    });
  });
})(jQuery);
