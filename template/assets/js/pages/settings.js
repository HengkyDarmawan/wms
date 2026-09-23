/* NexaDash — page-settings.html */
(function ($) {
  'use strict';

  var LABELS = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
  function strength(pw) {
    var s = 0;
    if (pw.length >= 8) s++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }

  document.addEventListener('nx:layout-ready', function () {
    $('#saLang, #saTz').select2({ theme: 'bootstrap-5', width: '100%', minimumResultsForSearch: -1 });

    // Avatar.
    $('#setAvatarInput').on('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function (e) { $('#setAvatarPreview').attr('src', e.target.result); };
      r.readAsDataURL(f);
    });
    $('#setAvatarRemove').on('click', function () {
      $('#setAvatarInput').val('');
      $('#setAvatarPreview').attr('src', 'https://i.pravatar.cc/160?img=12');
    });

    // Password strength.
    $('#spNew').on('input', function () {
      var lvl = this.value ? strength(this.value) : 0;
      $('#spStrength').attr('data-level', lvl);
      $('#spStrengthText').text(this.value ? LABELS[lvl] : 'Use 8+ characters with mixed case, numbers and symbols.');
    });

    // Save.
    $('.btn-save').on('click', function () {
      Swal.fire({ icon: 'success', title: 'Settings saved', timer: 1500, showConfirmButton: false });
    });

    // API key copy & regenerate.
    $('.btn-copy').on('click', function () {
      var val = $($(this).data('target')).val();
      if (navigator.clipboard) navigator.clipboard.writeText(val);
      var $b = $(this);
      $b.html('<i class="bi bi-clipboard-check"></i>');
      setTimeout(function () { $b.html('<i class="bi bi-clipboard"></i>'); }, 1600);
    });
    $('.btn-regen, #btnNewKey').on('click', function () {
      var $input = $(this).siblings('input');
      Swal.fire({
        title: 'Regenerate this key?',
        text: 'The old key stops working immediately.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Regenerate', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) {
        if (!r.isConfirmed) return;
        var prefix = ($input.val() || 'nx_live_').slice(0, 8);
        var rand = Array.from({ length: 24 }, function () { return '0123456789abcdef'[Math.floor(Math.random() * 16)]; }).join('');
        $input.val(prefix + rand);
        Swal.fire({ icon: 'success', title: 'New key generated', timer: 1400, showConfirmButton: false });
      });
    });

    // Danger zone.
    $('#btnDeactivate').on('click', function () {
      Swal.fire({
        title: 'Deactivate account?', text: 'Your workspace becomes read-only.', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Deactivate', confirmButtonColor: nxCss('--nx-warning')
      });
    });
    $('#btnDeleteAccount').on('click', function () {
      Swal.fire({
        title: 'Delete account permanently?',
        html: 'Type <strong>DELETE</strong> to confirm. This cannot be undone.',
        input: 'text', inputPlaceholder: 'DELETE',
        icon: 'error', showCancelButton: true,
        confirmButtonText: 'Delete forever', confirmButtonColor: nxCss('--nx-danger'),
        preConfirm: function (v) {
          if (v !== 'DELETE') Swal.showValidationMessage('You must type DELETE exactly.');
          return v;
        }
      }).then(function (r) {
        if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Account scheduled for deletion', timer: 1800, showConfirmButton: false });
      });
    });
  });
})(jQuery);
