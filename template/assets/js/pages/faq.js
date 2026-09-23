/* NexaDash — page-faq.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('#faqSearch').on('input', function () {
      var q = this.value.toLowerCase().trim();
      $('.faq-list .accordion-item').each(function () {
        $(this).toggle(!q || $(this).text().toLowerCase().indexOf(q) > -1);
      });
      // Sembunyikan kartu kategori yang tidak punya hasil.
      $('.faq-list').each(function () {
        var any = $(this).find('.accordion-item:visible').length > 0;
        $(this).closest('.card').toggle(any || !q);
      });
    });
  });
})(jQuery);
