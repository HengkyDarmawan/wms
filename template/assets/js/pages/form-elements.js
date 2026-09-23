/* NexaDash — form-elements.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    // Textarea auto-grow.
    $('#feAutogrow').on('input', function () {
      this.style.height = 'auto';
      this.style.height = this.scrollHeight + 'px';
    });
  });
})(jQuery);
