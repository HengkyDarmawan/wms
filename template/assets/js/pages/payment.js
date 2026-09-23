/* NexaDash — pages/payment.html */
(function ($) {
  'use strict';

  var SEATS = 40;
  var promo = 0;

  function money(n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function totals() {
    var annual = $('#pmAnnual').is(':checked');
    var rate = annual ? 47 : 59;
    var subtotal = SEATS * rate * (annual ? 12 : 1);
    var discount = annual ? subtotal * 0.2 : 0;
    var promoCut = (subtotal - discount) * promo;
    var tax = (subtotal - discount - promoCut) * 0.08;
    var total = subtotal - discount - promoCut + tax;

    $('#pmRate').text('$' + rate + (annual ? ' × 12' : ''));
    $('#pmSubtotal').text(money(subtotal));
    $('#pmDiscount').text('−' + money(discount + promoCut));
    $('#pmTax').text(money(tax));
    $('#pmTotal').text(money(total));
    $('#pmPayAmount').text(money(total));
  }

  document.addEventListener('nx:layout-ready', function () {
    Inputmask({ mask: '9999 9999 9999 9999' }).mask('#pmNumber');
    Inputmask({ mask: '99/99', placeholder: 'MM/YY' }).mask('#pmExp');
    Inputmask({ mask: '999[9]' }).mask('#pmCvv');

    totals();
    $('input[name="pmPeriod"]').on('change', totals);

    // Pratinjau kartu mengikuti isian.
    $('#pmNumber').on('input', function () {
      var raw = this.value.replace(/\D/g, '');
      var masked = (raw + '••••••••••••••••').slice(0, 16).replace(/(.{4})/g, '$1 ').trim();
      $('#pmNumPreview').text(masked);

      var icon = 'bi-credit-card', label = '';
      if (/^4/.test(raw)) { icon = 'bi-credit-card-2-front'; label = 'Visa'; }
      else if (/^5/.test(raw)) { icon = 'bi-credit-card-2-back'; label = 'Mastercard'; }
      else if (/^3/.test(raw)) { icon = 'bi-credit-card-fill'; label = 'Amex'; }
      $('#pmBrandIcon').attr('class', 'bi ' + icon + ' fs-3 ms-auto');
      $('#pmBrandLabel').html('<i class="bi ' + icon + '"></i>' + (label ? '<span class="small ms-1">' + label + '</span>' : ''));
    });
    $('#pmName').on('input', function () {
      $('#pmNamePreview').text((this.value || 'YOUR NAME').toUpperCase());
    });
    $('#pmExp').on('input', function () {
      $('#pmExpPreview').text(this.value || 'MM/YY');
    });

    // Metode pembayaran mengganti blok isian.
    $('#pmMethods input').on('change', function () {
      var card = $('#pmCard').is(':checked');
      $('#pmCardFields, #pmCardPreview').toggleClass('d-none', !card);
      $('#pmPaypalFields').toggleClass('d-none', !$('#pmPaypal').is(':checked'));
      $('#pmBankFields').toggleClass('d-none', !$('#pmBankTransfer').is(':checked'));
    });

    $('#pmPromoApply').on('click', function () {
      var code = ($('#pmPromo').val() || '').trim().toUpperCase();
      if (code === 'WELCOME10') {
        promo = 0.1;
        $('#pmPromoMsg').attr('class', 'small text-success mb-3').text('WELCOME10 applied — an extra 10% off.');
      } else {
        promo = 0;
        $('#pmPromoMsg').attr('class', 'small text-danger mb-3').text('That code is not valid.');
      }
      totals();
    });

    $('#pmPay').on('click', function () {
      var $b = $(this);
      $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing&hellip;');
      setTimeout(function () {
        $b.prop('disabled', false).html('<i class="bi bi-lock-fill me-1"></i>Pay <span id="pmPayAmount">' + $('#pmTotal').text() + '</span>');
        Swal.fire({
          icon: 'success', title: 'Payment received',
          text: 'Your Pro subscription is active. A receipt is on its way.',
          confirmButtonColor: nxCss('--nx-primary')
        });
      }, 1600);
    });
  });
})(jQuery);
