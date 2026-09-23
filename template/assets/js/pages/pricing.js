/* NexaDash — page-pricing.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    function apply() {
      var annual = $('#billingToggle').is(':checked');
      $('.price').each(function () {
        $(this).text($(this).data(annual ? 'annual' : 'monthly'));
      });
      $('.period').text(annual ? 'billed annually' : 'billed monthly');
      $('#labelAnnual').toggleClass('text-muted', !annual);
      $('#labelMonthly').toggleClass('text-muted', annual);
    }
    $('#billingToggle').on('change', apply);
    apply();
  });
})(jQuery);
