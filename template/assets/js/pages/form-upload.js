/* NexaDash — form-upload.html */
(function ($) {
  'use strict';

  var timer = null;

  // Harus dimatikan sebelum DOMContentLoaded, sebelum Dropzone memindai .dropzone sendiri.
  Dropzone.autoDiscover = false;

  document.addEventListener('nx:layout-ready', function () {
    new Dropzone('#mainDropzone', {
      url: '#',
      autoProcessQueue: false,
      addRemoveLinks: true,
      maxFilesize: 20,
      dictDefaultMessage: '<i class="bi bi-cloud-arrow-up fs-1 d-block mb-2"></i><strong>Drop files here</strong><br><span class="small">or click to browse your computer</span>',
      dictRemoveFile: 'Remove'
    });

    // Preview avatar.
    $('#avatarInput').on('change', function () {
      var file = this.files && this.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (e) { $('#avatarPreview').attr('src', e.target.result); };
      reader.readAsDataURL(file);
    });
    $('#avatarReset').on('click', function () {
      $('#avatarInput').val('');
      $('#avatarPreview').attr('src', 'https://i.pravatar.cc/160?img=12');
    });

    // Galeri multi-gambar.
    $('#galleryInput').on('change', function () {
      var files = Array.prototype.slice.call(this.files || []);
      if (!files.length) return;
      $('#galleryPreview').empty();
      files.forEach(function (f) {
        var reader = new FileReader();
        reader.onload = function (e) {
          $('#galleryPreview').append(
            '<div class="col-4 col-md-3"><div class="nx-pc-img"><img src="' + e.target.result + '" alt="' + $('<span>').text(f.name).html() + '"></div></div>'
          );
        };
        reader.readAsDataURL(f);
      });
    });

    // Simulasi progres.
    function simulate() {
      clearInterval(timer);
      var pct = 0;
      timer = setInterval(function () {
        pct = Math.min(100, pct + Math.random() * 9);
        $('#simBar').css('width', pct + '%').closest('.progress').attr('aria-valuenow', Math.round(pct));
        $('#simPct').text(Math.round(pct));
        if (pct >= 100) {
          clearInterval(timer);
          $('#simBar').removeClass('progress-bar-striped progress-bar-animated').addClass('bg-success');
        }
      }, 300);
    }
    $('#simStart').on('click', function () {
      $('#simBar').addClass('progress-bar-striped progress-bar-animated').removeClass('bg-success');
      simulate();
    });
    simulate();
  });
})(jQuery);
