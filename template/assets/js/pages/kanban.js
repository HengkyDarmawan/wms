/* NexaDash — apps-kanban.html */
(function ($) {
  'use strict';

  function refreshCounts() {
    $('.nx-kanban-col').each(function () {
      $(this).find('.nx-kanban-col-head .badge').text($(this).find('.nx-kanban-card').length);
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    document.querySelectorAll('.nx-kanban-cards').forEach(function (col) {
      new Sortable(col, {
        group: 'kanban',
        animation: 160,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onSort: refreshCounts
      });
    });

    // Tambah task.
    $('.nx-kanban-add').on('click', function () {
      var $cards = $(this).siblings('.nx-kanban-cards');
      Swal.fire({
        title: 'New task',
        input: 'text',
        inputPlaceholder: 'Task title',
        showCancelButton: true,
        confirmButtonText: 'Add',
        confirmButtonColor: nxCss('--nx-primary')
      }).then(function (r) {
        if (!r.isConfirmed || !r.value) return;
        var $card = $('<div class="nx-kanban-card">' +
          '<span class="badge badge-soft-primary">Feature</span>' +
          '<div class="nx-kc-title"></div>' +
          '<div class="nx-kc-desc">No description yet.</div>' +
          '<div class="nx-kc-foot"><span class="nx-avatar-group"><span class="nx-avatar nx-avatar-xs">AS</span></span>' +
          '<span><i class="bi bi-chat"></i> 0</span><span class="badge badge-soft-secondary ms-auto">No due date</span></div></div>');
        $card.find('.nx-kc-title').text(r.value);
        $cards.append($card);
        refreshCounts();
      });
    });

    // Filter kartu.
    $('#kanbanSearch').on('input', function () {
      var q = this.value.toLowerCase();
      $('.nx-kanban-card').each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
      });
    });
  });
})(jQuery);
