/* NexaDash — form-editors.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {
    var full = new Quill('#fullEditor', {
      theme: 'snow',
      placeholder: 'Write something worth reading…',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
          [{ align: [] }],
          ['blockquote', 'code-block', 'link', 'image'],
          ['clean']
        ]
      }
    });

    full.clipboard.dangerouslyPasteHTML(
      '<h2>Release notes — v2.4.1</h2>' +
      '<p>This release focuses on <strong>billing reliability</strong> and a handful of long-standing papercuts.</p>' +
      '<ul><li>Fixed retry loops on soft declines</li><li>Improved invoice export performance</li><li>New feature flag management UI</li></ul>' +
      '<blockquote>Upgrading is recommended for all workspaces on the Pro plan.</blockquote>'
    );

    new Quill('#miniEditor', {
      theme: 'snow',
      placeholder: 'A comment, perhaps…',
      modules: { toolbar: [['bold', 'italic'], ['link'], [{ list: 'bullet' }]] }
    });

    function sync() {
      var html = full.getSemanticHTML ? full.getSemanticHTML() : full.root.innerHTML;
      $('#htmlOutput code').text(html);
      $('#charCount').text(full.getLength() - 1 + ' chars');
    }
    full.on('text-change', sync);
    sync();

    $('#clearEditor').on('click', function () { full.setText(''); });
    $('#saveEditor').on('click', function () {
      Swal.fire({ icon: 'success', title: 'Content saved', timer: 1500, showConfirmButton: false });
    });
  });
})(jQuery);
