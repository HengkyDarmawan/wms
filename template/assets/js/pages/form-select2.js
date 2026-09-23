/* NexaDash — form-select2.html */
(function ($) {
  'use strict';

  function personTemplate(state) {
    if (!state.id) return state.text;
    var avatar = $(state.element).data('avatar');
    var role = $(state.element).data('role') || '';
    var $wrap = $('<span class="d-flex align-items-center gap-2"></span>');
    $wrap.append('<span class="nx-avatar nx-avatar-xs"><img src="https://i.pravatar.cc/40?img=' + avatar + '" alt=""></span>');
    $wrap.append($('<span></span>').append($('<strong></strong>').text(state.text), $('<span class="text-muted small ms-1"></span>').text(role)));
    return $wrap;
  }

  document.addEventListener('nx:layout-ready', function () {
    var base = { theme: 'bootstrap-5', width: '100%' };

    $('#s2Single, #s2Disabled, #s2Group, #s2fOwner').select2(base);
    $('#s2Placeholder').select2($.extend({}, base, { placeholder: 'Choose a module', allowClear: true }));
    $('#s2Multi, #s2fStatus, #s2fProduct').select2($.extend({}, base, { placeholder: 'Select one or more', closeOnSelect: false }));
    $('#s2Tags').select2($.extend({}, base, { tags: true, tokenSeparators: [',', ' '], placeholder: 'Add tags' }));
    $('#s2Limit').select2($.extend({}, base, { maximumSelectionLength: 3, placeholder: 'Pick up to 3' }));
    $('#s2People').select2($.extend({}, base, { templateResult: personTemplate, templateSelection: personTemplate }));

    // Daftar besar difilter secara lokal.
    var big = [];
    for (var i = 1; i <= 300; i++) big.push({ id: i, text: 'Workspace item #' + i });
    $('#s2Big').select2($.extend({}, base, { data: big, placeholder: 'Search 300 items' }));
  });
})(jQuery);
