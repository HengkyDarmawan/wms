/* NexaDash — halaman auth (login, register, forgot, lock, 2FA) */
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

    // ===== Toggle password visibility =====
    $('.btn-toggle-pw').on('click', function () {
      var $i = $($(this).data('target'));
      var show = $i.attr('type') === 'password';
      $i.attr('type', show ? 'text' : 'password');
      $(this).html('<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>')
        .attr('aria-label', show ? 'Hide password' : 'Show password');
    });

    // ===== Login =====
    $('#loginForm').on('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
      var $btn = $(this).find('[type="submit"]');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Signing in&hellip;');
      setTimeout(function () { window.location.href = 'index.html'; }, 900);
    });

    // ===== Register =====
    $('#regPassword').on('input', function () {
      var lvl = this.value ? strength(this.value) : 0;
      $('#regStrength').attr('data-level', lvl);
      $('#regStrengthText').text(this.value ? LABELS[lvl] : 'Use 8+ characters with mixed case, numbers and symbols.');
    });
    $('#registerForm').on('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
      var $btn = $(this).find('[type="submit"]');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating account&hellip;');
      setTimeout(function () { window.location.href = 'apps-wizard.html'; }, 900);
    });

    // ===== Forgot password =====
    $('#forgotForm').on('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
      var email = $('#forgotEmail').val();
      $('#forgotCard').html(
        '<div class="text-center">' +
        '<span class="nx-success-check"><i class="bi bi-envelope-check"></i></span>' +
        '<h1 class="h2 mb-1">Check your inbox</h1>' +
        '<p class="text-muted mb-4">We sent a password reset link to <strong>' + $('<span>').text(email).html() + '</strong>. ' +
        'It expires in one hour.</p>' +
        '<a class="btn btn-primary w-100 btn-lg" href="auth-login.html">Back to sign in</a>' +
        '<p class="text-muted small mt-4 mb-0">Nothing arrived? Check your spam folder or ' +
        '<a class="nx-link" href="auth-forgot-password.html">try another address</a>.</p></div>'
      );
    });

    // ===== Reset password =====
    $('#rpPassword').on('input', function () {
      var lvl = this.value ? strength(this.value) : 0;
      $('#rpStrength').attr('data-level', lvl);
      $('#rpStrengthText').text(this.value ? LABELS[lvl] : 'Use 8+ characters with mixed case, numbers and symbols.');
    });
    $('#resetForm').on('submit', function (e) {
      e.preventDefault();
      var pw = $('#rpPassword').val();
      var pw2 = $('#rpConfirm').val();
      var ok = this.checkValidity() && pw === pw2;
      $('#rpConfirm').toggleClass('is-invalid', pw !== pw2);
      if (!ok) { $(this).addClass('was-validated'); return; }
      var $btn = $(this).find('[type="submit"]');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Updating&hellip;');
      setTimeout(function () { window.location.href = 'login.html'; }, 900);
    });

    // ===== Verify email: hitung mundur sebelum boleh kirim ulang =====
    if ($('#veResend').length) {
      var veLeft = 45;
      var veTimer = setInterval(function () {
        veLeft--;
        $('#veCountdown').text(veLeft);
        if (veLeft <= 0) {
          clearInterval(veTimer);
          $('#veResend').prop('disabled', false).text('Resend the email');
        }
      }, 1000);
      $('#veResend').on('click', function () {
        $(this).prop('disabled', true).text('Sent — check your inbox');
      });
    }

    // ===== Lock screen =====
    $('#lockForm').on('submit', function (e) {
      e.preventDefault();
      if (!this.checkValidity()) { $(this).addClass('was-validated'); return; }
      var $btn = $(this).find('[type="submit"]');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Unlocking&hellip;');
      setTimeout(function () { window.location.href = 'index.html'; }, 800);
    });

    // ===== OTP =====
    var $otp = $('#otpInputs input');
    if ($otp.length) {
      function otpValue() {
        return $otp.map(function () { return this.value; }).get().join('');
      }
      function sync() {
        $('#otpSubmit').prop('disabled', otpValue().length !== 6);
      }
      $otp.on('input', function (e) {
        this.value = this.value.replace(/\D/g, '');
        if (this.value && $(this).next('input').length) $(this).next('input').trigger('focus');
        sync();
      });
      $otp.on('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && $(this).prev('input').length) {
          $(this).prev('input').trigger('focus');
        }
      });
      $otp.on('paste', function (e) {
        e.preventDefault();
        var text = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
        $otp.each(function (i) { this.value = text[i] || ''; });
        $otp.eq(Math.min(text.length, 5)).trigger('focus');
        sync();
      });
      $otp.first().trigger('focus');

      $('#otpForm').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#otpSubmit');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Verifying&hellip;');
        setTimeout(function () { window.location.href = 'index.html'; }, 900);
      });

      // Hitung mundur resend.
      var left = 30;
      var t = setInterval(function () {
        left--;
        $('#otpCountdown').text(left);
        if (left <= 0) {
          clearInterval(t);
          $('#otpResend').prop('disabled', false).text('Resend code');
        }
      }, 1000);
      $('#otpResend').on('click', function () {
        $(this).prop('disabled', true).html('Resend in <span id="otpCountdown">30</span>s');
        left = 30;
        t = setInterval(function () {
          left--;
          $('#otpCountdown').text(left);
          if (left <= 0) { clearInterval(t); $('#otpResend').prop('disabled', false).text('Resend code'); }
        }, 1000);
      });
    }
  });
})(jQuery);
