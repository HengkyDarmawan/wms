/* NexaDash — ui-icons.html */
(function ($) {
  'use strict';

  var ICONS = ('grid-1x2 graph-up bag people heart-pulse bar-chart receipt box-seam person-badge file-earmark-text ' +
    'envelope chat-dots folder kanban calendar3 magic palette input-cursor-text table signpost-split activity ' +
    'journal-code person-circle tags question-circle clock-history shield-lock exclamation-triangle people-fill bell ' +
    'gear life-preserver house-door search plus-lg dash-lg check-lg x-lg three-dots three-dots-vertical pencil trash ' +
    'download upload share copy eye eye-slash lock unlock star star-fill heart heart-fill bookmark flag funnel sort-down ' +
    'arrow-up arrow-down arrow-left arrow-right arrow-up-right arrow-down-right chevron-up chevron-down chevron-left chevron-right ' +
    'box-arrow-right box-arrow-up-right currency-dollar credit-card cash-stack wallet2 bank percent basket bag-check ' +
    'cart truck rocket-takeoff lightning-charge shield-check bullseye trophy award gift ' +
    'camera camera-video image film music-note headphones mic play-circle pause-circle stop-circle ' +
    'cloud cloud-arrow-up cloud-arrow-down wifi hdd server database code-slash terminal bug braces ' +
    'sun moon-stars stars brightness-high droplet palette2 brush type-bold ' +
    'geo-alt map compass globe clock alarm calendar-check calendar-week hourglass-split ' +
    'emoji-smile emoji-neutral emoji-frown hand-thumbs-up hand-thumbs-down chat-square-text ' +
    'telephone printer laptop phone tablet display keyboard mouse ' +
    'file-earmark-pdf file-earmark-spreadsheet file-earmark-image file-earmark-zip file-earmark-code folder-fill archive ' +
    'person person-plus person-dash person-vcard people-fill building hospital briefcase ' +
    'check-circle x-circle info-circle exclamation-circle question-lg patch-check slash-circle ' +
    'toggle-on toggle-off sliders list-ul list-check grid-3x3-gap layout-sidebar columns-gap ' +
    'send reply forward inbox paperclip pin-angle tag link-45deg qr-code upc-scan').split(/\s+/);

  function render(q) {
    q = (q || '').toLowerCase();
    var list = ICONS.filter(function (n) { return n.indexOf(q) > -1; });
    $('#iconGrid').html(list.map(function (n) {
      return '<button class="nx-icon-cell" type="button" data-icon="bi-' + n + '"><i class="bi bi-' + n + '"></i><span>' + n + '</span></button>';
    }).join(''));
    $('#iconCount').text(list.length);
  }

  document.addEventListener('nx:layout-ready', function () {
    render('');

    $('#iconSearch').on('input', function () { render(this.value); });

    $('#iconGrid').on('click', '.nx-icon-cell', function () {
      var cls = $(this).data('icon');
      if (navigator.clipboard) navigator.clipboard.writeText(cls);
      var $t = $('<div class="toast" role="alert"><div class="toast-body d-flex align-items-center gap-2">' +
        '<i class="bi bi-clipboard-check text-success"></i>Copied <code>' + cls + '</code></div></div>');
      $('#iconToast').append($t);
      new bootstrap.Toast($t[0], { delay: 1800 }).show();
      $t.on('hidden.bs.toast', function () { $t.remove(); });
    });
  });
})(jQuery);
