/* NexaDash — ui/treeview.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {

    // Buka / tutup cabang + seleksi baris.
    $('.nx-tree').on('click', '.nx-tree-row', function (e) {
      if ($(e.target).is('input, label')) return;
      var $li = $(this).parent();
      if ($li.hasClass('has-kids')) {
        $li.toggleClass('open');
        $(this).toggleClass('open');
      }
      $(this).closest('.nx-tree').find('.nx-tree-row').removeClass('selected');
      $(this).addClass('selected');
    });

    $('#tvToggleAll').on('click', function () {
      var expand = $(this).text() === 'Expand all';
      $('#tvFiles li.has-kids').toggleClass('open', expand);
      $('#tvFiles .nx-tree-row').toggleClass('open', expand);
      $(this).text(expand ? 'Collapse all' : 'Expand all');
    });

    $('#tvSearch').on('input', function () {
      var q = this.value.toLowerCase().trim();
      if (!q) { $('#tvFiles li').show(); return; }
      $('#tvFiles li').each(function () {
        var own = $(this).children('.nx-tree-row').text().toLowerCase().indexOf(q) > -1;
        var child = $(this).find('li .nx-tree-row').filter(function () {
          return $(this).text().toLowerCase().indexOf(q) > -1;
        }).length > 0;
        $(this).toggle(own || child);
        if (child) $(this).addClass('open').children('.nx-tree-row').addClass('open');
      });
    });

    // Checkbox tree: induk mengatur anak, anak menentukan status indeterminate induk.
    function countSelected() {
      var n = $('#tvPerms li:not(.has-kids) .tv-check:checked').length;
      $('#tvSelected').text(n);
    }

    function syncUp($box) {
      var $li = $box.closest('li').parent().closest('li');
      if (!$li.length) return;
      var $parent = $li.children('.nx-tree-row').find('.tv-check');
      var $kids = $li.find('ul .tv-check');
      var checked = $kids.filter(':checked').length;
      $parent.prop('checked', checked === $kids.length && checked > 0);
      $parent.prop('indeterminate', checked > 0 && checked < $kids.length);
      syncUp($parent);
    }

    $('#tvPerms').on('change', '.tv-check', function () {
      var $li = $(this).closest('li');
      $li.find('ul .tv-check').prop({ checked: this.checked, indeterminate: false });
      syncUp($(this));
      countSelected();
    });

    // Status awal harus konsisten dengan kotak yang sudah tercentang di markup.
    $('#tvPerms li:not(.has-kids) .tv-check').each(function () { syncUp($(this)); });
    countSelected();
  });
})(jQuery);
