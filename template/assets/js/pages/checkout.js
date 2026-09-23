/* NexaDash — commerce/checkout.html */
(function ($) {
  'use strict';

  var step = 1;
  var TOTAL = 4;
  var SUBTOTAL = 516, DISCOUNT = 51.6;

  function money(n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  function recalc() {
    var ship = parseFloat($('.co-ship:checked').val()) || 0;
    var tax = (SUBTOTAL - DISCOUNT) * 0.08;
    $('#coShipCost').text(ship === 0 ? 'Free' : money(ship));
    $('#coTotal').text(money(SUBTOTAL - DISCOUNT + ship + tax));
  }

  function validateStep(n) {
    var ok = true;
    $('[data-pane="' + n + '"]').find('input[required]').each(function () {
      var valid = this.checkValidity();
      $(this).toggleClass('is-invalid', !valid);
      if (!valid) ok = false;
    });
    return ok;
  }

  function render() {
    $('.nx-wizard-pane').removeClass('active');
    $('[data-pane="' + step + '"]').addClass('active');
    $('.nx-step').each(function () {
      var s = +$(this).data('step');
      $(this).removeClass('active done');
      if (s < step) { $(this).addClass('done'); $(this).find('.nx-step-circle').html('<i class="bi bi-check-lg"></i>'); }
      else { $(this).find('.nx-step-circle').text(s); if (s === step) $(this).addClass('active'); }
    });
    $('#coPrev').prop('disabled', step === 1);
    $('#coNext').text(step === TOTAL - 1 ? 'Place order' : 'Continue')
      .append(step === TOTAL - 1 ? '' : '<i class="bi bi-arrow-right ms-1"></i>');
    $('#coNav').toggleClass('d-none', step === TOTAL);
  }

  function summary() {
    var ship = $('.co-ship:checked').data('label');
    var method = $('#payCard').is(':checked') ? 'Card' : ($('#payPaypal').is(':checked') ? 'PayPal' : 'Bank transfer');
    var rows = [
      ['Ship to', $('#coFirst').val() + ' ' + $('#coLast').val() + ', ' + $('#coCity').val()],
      ['Delivery', ship],
      ['Payment', method],
      ['Total', $('#coTotal').text()]
    ];
    $('#coSummary').html(rows.map(function (r) {
      return '<li class="list-group-item d-flex justify-content-between px-0 bg-transparent">' +
        '<span class="text-muted">' + r[0] + '</span><strong>' + $('<span>').text(r[1]).html() + '</strong></li>';
    }).join(''));
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#coCountry').select2({ theme: 'bootstrap-5', width: '100%', minimumResultsForSearch: -1 });
    render();
    recalc();

    $('.co-ship').on('change', recalc);

    $('#coNext').on('click', function () {
      if (!validateStep(step)) return;
      step = Math.min(TOTAL, step + 1);
      if (step === TOTAL) summary();
      render();
    });
    $('#coPrev').on('click', function () {
      step = Math.max(1, step - 1);
      render();
    });
    $('#coForm').on('input', '.is-invalid', function () {
      $(this).toggleClass('is-invalid', !this.checkValidity());
    });

    // Kolom kartu hanya relevan untuk metode kartu.
    $('#coMethods input').on('change', function () {
      $('#coCardFields').toggleClass('d-none', !$('#payCard').is(':checked'));
    });
  });
})(jQuery);
