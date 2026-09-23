/* NexaDash — ui/blockui.html */
(function ($) {
  'use strict';

  var OVERLAY = '<div class="nx-block-overlay"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><span>Loading&hellip;</span></div>';

  function block($el, label) {
    if ($el.children('.nx-block-overlay').length) return;
    var $o = $(OVERLAY);
    if (label) $o.find('span').text(label);
    $el.append($o);
  }
  function unblock($el) {
    $el.children('.nx-block-overlay').remove();
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#blockCard').on('click', function () {
      var $t = $('#blockTarget');
      block($t, 'Fetching latest figures…');
      setTimeout(function () { unblock($t); }, 2000);
    });

    $('#blockToggle').on('click', function () {
      var $t = $('#blockTarget');
      if ($t.children('.nx-block-overlay').length) {
        unblock($t);
        $(this).text('Toggle indefinitely');
      } else {
        block($t, 'Blocked until you toggle again');
        $(this).text('Unblock');
      }
    });

    $('#buSave').on('click', function () {
      var $f = $('#blockForm');
      block($f, 'Saving changes…');
      setTimeout(function () {
        unblock($f);
        $('#buSave').removeClass('btn-primary').addClass('btn-success').html('<i class="bi bi-check2 me-1"></i>Saved');
        setTimeout(function () {
          $('#buSave').removeClass('btn-success').addClass('btn-primary').text('Save changes');
        }, 1600);
      }, 1600);
    });
  });
})(jQuery);
