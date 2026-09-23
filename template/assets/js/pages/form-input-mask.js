/* NexaDash — form-input-mask.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {
    Inputmask({ mask: '999-9999-9999' }).mask('#mkPhoneId');
    Inputmask({ mask: '(999) 999-9999' }).mask('#mkPhoneUs');
    Inputmask({ mask: '9999 9999 9999 9999' }).mask('#mkNik');
    Inputmask({ mask: '99.999.999.9-999.999' }).mask('#mkNpwp');

    Inputmask({ mask: '9999 9999 9999 9999' }).mask('#mkCard');
    Inputmask({ mask: '99/99', placeholder: 'MM/YY' }).mask('#mkExpiry');
    Inputmask({ mask: '999[9]' }).mask('#mkCvv');
    Inputmask({ mask: 'aa99 9999 9999 9999 9999 99' }).mask('#mkIban');

    Inputmask('datetime', { inputFormat: 'dd/mm/yyyy' }).mask('#mkDate');
    Inputmask('datetime', { inputFormat: 'dd/mm/yyyy HH:MM' }).mask('#mkDateTime');
    Inputmask('datetime', { inputFormat: 'HH:MM' }).mask('#mkTime');

    Inputmask('currency', { prefix: '', groupSeparator: '.', radixPoint: ',', digits: 0, autoUnmask: true, rightAlign: true }).mask('#mkIdr');
    Inputmask('currency', { prefix: '', groupSeparator: ',', radixPoint: '.', digits: 2, rightAlign: true }).mask('#mkUsd');
    Inputmask('decimal', { digits: 2, rightAlign: true, max: 100 }).mask('#mkPercent');
    Inputmask('ip').mask('#mkIp');

    // Deteksi brand kartu sederhana.
    $('#mkCard').on('input', function () {
      var d = this.value.replace(/\D/g, '');
      var icon = 'bi-credit-card', label = '';
      if (/^4/.test(d)) { icon = 'bi-credit-card-2-front'; label = 'Visa'; }
      else if (/^5/.test(d)) { icon = 'bi-credit-card-2-back'; label = 'MC'; }
      else if (/^3/.test(d)) { icon = 'bi-credit-card-fill'; label = 'Amex'; }
      $('#cardBrand').html('<i class="bi ' + icon + '"></i>' + (label ? '<span class="small ms-1">' + label + '</span>' : ''));
    });
  });
})(jQuery);
