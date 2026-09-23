/* NexaDash — forms/sticky-actions.html */
(function ($) {
  'use strict';

  var dirty = false;
  var snapshot = {};

  function capture() {
    snapshot = {};
    $('#saForm').find('input, select, textarea').each(function () {
      if (this.type === 'file') return;
      snapshot[this.id] = this.type === 'checkbox' ? this.checked : $(this).val();
    });
  }

  function setDirty(state) {
    dirty = state;
    $('#saSave').prop('disabled', !state);
    $('#saState')
      .toggleClass('text-warning', state)
      .text(state ? 'You have unsaved changes' : 'No unsaved changes');
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#saOwner, #saVisibility, #saTheme, #saTz, #saLocale, #saCurrency, #saDateFmt, #saWeek, #saRetention')
      .select2({ theme: 'bootstrap-5', width: '100%', minimumResultsForSearch: -1 });

    capture();

    $('#saForm').on('input change', 'input, select, textarea', function () {
      if (this.type !== 'file') setDirty(true);
    });

    $('#saLogo').on('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function (e) { $('#saLogoPreview').attr('src', e.target.result); };
      r.readAsDataURL(f);
      setDirty(true);
    });

    $('#saSave').on('click', function () {
      var $b = $(this);
      $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving&hellip;');
      setTimeout(function () {
        capture();
        setDirty(false);
        $b.html('<i class="bi bi-check2 me-1"></i>Save changes');
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Settings saved', showConfirmButton: false, timer: 1800 });
      }, 1200);
    });

    $('#saReset').on('click', function () {
      if (!dirty) return;
      Swal.fire({
        title: 'Discard changes?', icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Discard', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        Object.keys(snapshot).forEach(function (id) {
          var el = document.getElementById(id);
          if (!el) return;
          if (el.type === 'checkbox') el.checked = snapshot[id];
          else $(el).val(snapshot[id]).trigger('change.select2');
        });
        setDirty(false);
      });
    });

    $('#saDelete').on('click', function () {
      Swal.fire({
        title: 'Delete this workspace?',
        html: 'Type <strong>DELETE</strong> to confirm.',
        input: 'text', icon: 'error', showCancelButton: true,
        confirmButtonText: 'Delete forever', confirmButtonColor: nxCss('--nx-danger'),
        preConfirm: function (v) { if (v !== 'DELETE') Swal.showValidationMessage('Type DELETE exactly.'); return v; }
      });
    });

    // Highlight menu samping mengikuti bagian yang terlihat.
    var sections = $('#saNav a').map(function () { return $(this).attr('href'); }).get();
    $(window).on('scroll', function () {
      var top = window.scrollY + 200;
      var current = sections[0];
      sections.forEach(function (sel) {
        var el = document.querySelector(sel);
        if (el && el.offsetTop <= top) current = sel;
      });
      $('#saNav a').removeClass('active').filter('[href="' + current + '"]').addClass('active');
    });

    $('#saNav a').on('click', function (e) {
      e.preventDefault();
      var el = document.querySelector($(this).attr('href'));
      if (el) window.scrollTo({ top: el.offsetTop - 150, behavior: 'smooth' });
    });
  });
})(jQuery);
