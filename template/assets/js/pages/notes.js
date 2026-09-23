/* NexaDash — apps/notes.html */
(function ($) {
  'use strict';

  var LABEL_COLOR = { Work: '--nx-chart-1', Personal: '--nx-chart-5', Ideas: '--nx-chart-3' };
  var seq = 6;
  var filterLabel = '';

  var NOTES = [
    { id: 1, title: 'Q4 roadmap talking points', body: 'Lead with billing reliability — it is the theme leadership already believes in. Onboarding needs a demo, not a slide. Analytics is still discovery, say so plainly.', label: 'Work', pinned: true, date: 'Sep 11' },
    { id: 2, title: 'Stripe migration risks', body: 'Webhook signature version changes. Idempotency keys are scoped differently. Sandbox does not reproduce soft declines, so we need a staging account with test cards.', label: 'Work', pinned: false, date: 'Sep 10' },
    { id: 3, title: 'Onboarding wizard copy', body: 'Four steps feels long. Merge company and plan into one screen and the drop-off should move. Ask design for a two-step variant before we commit.', label: 'Ideas', pinned: true, date: 'Sep 9' },
    { id: 4, title: 'Books to finish', body: 'Thinking in Systems — halfway. The Design of Everyday Things — re-read chapter 4 on affordances before the design review.', label: 'Personal', pinned: false, date: 'Sep 7' },
    { id: 5, title: 'Conference talk outline', body: 'Open with the dashboard nobody trusted. Middle: the three things we changed. Close with the token system and how it made dark mode a file swap.', label: 'Ideas', pinned: false, date: 'Sep 4' }
  ];

  var TASKS = [
    { id: 1, text: 'Review PR #482', done: false },
    { id: 2, text: 'Send Q4 deck to leadership', done: false },
    { id: 3, text: 'Book the offsite venue', done: true },
    { id: 4, text: 'Reply to Kumo discount request', done: false }
  ];

  function esc(s) { return $('<span>').text(s).html(); }

  function renderNotes() {
    var q = ($('#noteSearch').val() || '').toLowerCase();
    var list = NOTES.filter(function (n) {
      return (!filterLabel || n.label === filterLabel) &&
        (n.title + ' ' + n.body).toLowerCase().indexOf(q) > -1;
    }).sort(function (a, b) { return (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0); });

    $('#noteGrid').html(list.length ? list.map(function (n) {
      return '<div class="col-xl-4 col-md-6"><div class="card h-100 nx-note nx-card-lift" style="border-left-color:' + nxCss(LABEL_COLOR[n.label]) + '">' +
        '<div class="card-body d-flex flex-column">' +
        '<div class="d-flex align-items-start gap-2 mb-2">' +
        '<h3 class="h5 mb-0 nx-note-title flex-grow-1">' + esc(n.title) + '</h3>' +
        (n.pinned ? '<i class="bi bi-pin-angle-fill text-warning"></i>' : '') +
        '</div>' +
        '<p class="small text-muted flex-grow-1">' + esc(n.body) + '</p>' +
        '<div class="d-flex align-items-center gap-2">' +
        '<span class="badge badge-soft-secondary">' + esc(n.label) + '</span>' +
        '<span class="small text-muted">' + n.date + '</span>' +
        '<div class="ms-auto d-flex gap-1">' +
        '<button class="btn btn-xs btn-soft-secondary note-pin" data-id="' + n.id + '" type="button" aria-label="Pin"><i class="bi bi-pin-angle"></i></button>' +
        '<button class="btn btn-xs btn-soft-primary note-edit" data-id="' + n.id + '" type="button" aria-label="Edit"><i class="bi bi-pencil"></i></button>' +
        '<button class="btn btn-xs btn-soft-danger note-del" data-id="' + n.id + '" type="button" aria-label="Delete"><i class="bi bi-trash"></i></button>' +
        '</div></div></div></div></div>';
    }).join('') : '<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x d-block fs-3 mb-2"></i>No notes match that filter.</div></div></div>');

    $('#noteCount').text(list.length);
    $('#cntAll').text(NOTES.length);
    $('#cntWork').text(NOTES.filter(function (n) { return n.label === 'Work'; }).length);
    $('#cntPersonal').text(NOTES.filter(function (n) { return n.label === 'Personal'; }).length);
    $('#cntIdeas').text(NOTES.filter(function (n) { return n.label === 'Ideas'; }).length);
  }

  function renderTasks() {
    $('#taskList').html(TASKS.map(function (t) {
      return '<li class="d-flex gap-2 align-items-start py-2 border-bottom">' +
        '<input class="form-check-input mt-1 flex-shrink-0 task-check" type="checkbox" data-id="' + t.id + '" id="task' + t.id + '"' + (t.done ? ' checked' : '') + '>' +
        '<label class="form-check-label small flex-grow-1' + (t.done ? ' text-decoration-line-through text-muted' : '') + '" for="task' + t.id + '">' + esc(t.text) + '</label>' +
        '<button class="btn btn-xs btn-soft-danger task-del" data-id="' + t.id + '" type="button" aria-label="Remove task"><i class="bi bi-x"></i></button></li>';
    }).join(''));
    $('#taskLeft').text(TASKS.filter(function (t) { return !t.done; }).length + ' left');
  }

  function openEditor(note) {
    $('#noteId').val(note ? note.id : '');
    $('#noteModalTitle').text(note ? 'Edit note' : 'New note');
    $('#noteTitle').val(note ? note.title : '');
    $('#noteBody').val(note ? note.body : '');
    $('#noteLabel').val(note ? note.label : 'Work');
    $('#notePinned').prop('checked', note ? note.pinned : false);
    new bootstrap.Modal(document.getElementById('noteModal')).show();
  }

  document.addEventListener('nx:layout-ready', function () {
    renderNotes();
    renderTasks();

    $('#noteSearch').on('input', renderNotes);
    $('#noteNew').on('click', function () { openEditor(null); });

    $('.note-filter').on('click', function () {
      $('.note-filter').removeClass('active');
      $(this).addClass('active');
      filterLabel = $(this).data('label');
      renderNotes();
    });

    $('#noteSave').on('click', function () {
      var title = $('#noteTitle').val().trim();
      if (!title) { $('#noteTitle').addClass('is-invalid'); return; }
      $('#noteTitle').removeClass('is-invalid');
      var id = $('#noteId').val();
      var payload = {
        title: title,
        body: $('#noteBody').val().trim() || 'No detail yet.',
        label: $('#noteLabel').val(),
        pinned: $('#notePinned').is(':checked'),
        date: 'Just now'
      };
      if (id) {
        var n = NOTES.find(function (x) { return String(x.id) === String(id); });
        $.extend(n, payload);
      } else {
        NOTES.unshift($.extend({ id: ++seq }, payload));
      }
      bootstrap.Modal.getInstance(document.getElementById('noteModal')).hide();
      renderNotes();
    });

    $('#noteGrid').on('click', '.note-edit', function () {
      var id = $(this).data('id');
      openEditor(NOTES.find(function (n) { return n.id === id; }));
    });
    $('#noteGrid').on('click', '.note-pin', function () {
      var n = NOTES.find(function (x) { return x.id === $(this).data('id'); }.bind(this));
      n.pinned = !n.pinned;
      renderNotes();
    });
    $('#noteGrid').on('click', '.note-del', function () {
      var id = $(this).data('id');
      Swal.fire({ title: 'Delete this note?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) {
          if (!r.isConfirmed) return;
          NOTES = NOTES.filter(function (n) { return n.id !== id; });
          renderNotes();
        });
    });

    function addTask() {
      var text = $('#taskInput').val().trim();
      if (!text) return;
      TASKS.push({ id: ++seq, text: text, done: false });
      $('#taskInput').val('');
      renderTasks();
    }
    $('#taskAdd').on('click', addTask);
    $('#taskInput').on('keydown', function (e) { if (e.key === 'Enter') addTask(); });

    $('#taskList').on('change', '.task-check', function () {
      var t = TASKS.find(function (x) { return x.id === $(this).data('id'); }.bind(this));
      t.done = this.checked;
      renderTasks();
    });
    $('#taskList').on('click', '.task-del', function () {
      var id = $(this).data('id');
      TASKS = TASKS.filter(function (t) { return t.id !== id; });
      renderTasks();
    });
  });
})(jQuery);
