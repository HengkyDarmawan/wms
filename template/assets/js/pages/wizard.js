/* NexaDash — apps-wizard.html */
(function ($) {
  'use strict';

  var step = 1;
  var TOTAL = 4;

  function validateStep(n) {
    var ok = true;
    $('[data-pane="' + n + '"]').find('input[required], select[required]').each(function () {
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
      var s = parseInt($(this).data('step'), 10);
      $(this).removeClass('active done');
      if (s < step) { $(this).addClass('done'); $(this).find('.nx-step-circle').html('<i class="bi bi-check-lg"></i>'); }
      else { $(this).find('.nx-step-circle').text(s); if (s === step) $(this).addClass('active'); }
    });

    $('#wizPrev').prop('disabled', step === 1);
    $('#wizNext').toggleClass('d-none', step === TOTAL);
    $('#wizFinish').toggleClass('d-none', step !== TOTAL);
  }

  function buildSummary() {
    var rows = [
      ['Name', ($('#wFirst').val() || '—') + ' ' + ($('#wLast').val() || '')],
      ['Email', $('#wEmail').val() || '—'],
      ['Company', $('#wCompany').val() || '—'],
      ['Team size', $('#wSize').val() || '—'],
      ['Industry', $('#wIndustry').val() || '—'],
      ['Plan', $('input[name="wplan"]:checked').val()]
    ];
    $('#wizSummary').html(rows.map(function (r) {
      return '<li class="list-group-item d-flex justify-content-between px-0 bg-transparent">' +
        '<span class="text-muted">' + r[0] + '</span><strong>' + $('<span>').text(r[1]).html() + '</strong></li>';
    }).join(''));
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#wIndustry, #wSize').select2({ theme: 'bootstrap-5', minimumResultsForSearch: -1 });
    render();

    $('#wizNext').on('click', function () {
      if (!validateStep(step)) return;
      step = Math.min(TOTAL, step + 1);
      if (step === TOTAL) buildSummary();
      render();
    });
    $('#wizPrev').on('click', function () {
      step = Math.max(1, step - 1);
      render();
    });
    $('#wizardForm').on('input change', '.is-invalid', function () {
      $(this).toggleClass('is-invalid', !this.checkValidity());
    });
  });
})(jQuery);
