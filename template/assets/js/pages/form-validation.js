/* NexaDash — form-validation.html */
(function ($) {
  'use strict';

  function strength(pw) {
    var score = 0;
    if (pw.length >= 8) score++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
    if (/\d/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
  }
  var LABELS = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];

  function revalidate() {
    var user = $('#cvUser').val().trim();
    var pw = $('#cvPass').val();
    var pw2 = $('#cvPass2').val();

    var userOk = /^[a-z0-9-]{4,}$/.test(user);
    $('#cvUser').toggleClass('is-invalid', user.length > 0 && !userOk).toggleClass('is-valid', userOk);

    var lvl = pw ? strength(pw) : 0;
    $('#cvStrength').attr('data-level', lvl);
    $('#cvStrengthText').text(pw ? LABELS[lvl] : 'Use 8+ characters with a mix of cases, numbers and symbols.');

    var matchOk = pw.length > 0 && pw === pw2;
    $('#cvPass2').toggleClass('is-invalid', pw2.length > 0 && !matchOk).toggleClass('is-valid', matchOk);

    $('#cvSubmit').prop('disabled', !(userOk && lvl >= 3 && matchOk));
  }

  document.addEventListener('nx:layout-ready', function () {
    // Bootstrap native.
    $('#bsForm').on('submit', function (e) {
      if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      else {
        e.preventDefault();
        Swal.fire({ icon: 'success', title: 'Account created', timer: 1600, showConfirmButton: false });
      }
      $(this).addClass('was-validated');
    });
    $('#bsReset').on('click', function () { $('#bsForm').removeClass('was-validated'); });

    // Custom.
    $('#cvUser, #cvPass, #cvPass2').on('input', revalidate);
    $('#cvToggle').on('click', function () {
      var $i = $('#cvPass');
      var show = $i.attr('type') === 'password';
      $i.attr('type', show ? 'text' : 'password');
      $(this).html('<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>');
    });
    $('#customForm').on('submit', function (e) {
      e.preventDefault();
      Swal.fire({ icon: 'success', title: 'Password set', timer: 1600, showConfirmButton: false });
    });
  });
})(jQuery);
