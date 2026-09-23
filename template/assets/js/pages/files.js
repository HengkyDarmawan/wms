/* NexaDash — apps-files.html */
(function ($) {
  'use strict';

  // Harus dimatikan sebelum DOMContentLoaded, sebelum Dropzone memindai .dropzone sendiri.
  Dropzone.autoDiscover = false;

  document.addEventListener('nx:layout-ready', function () {
    nxMountChart('#storageRadial', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'radialBar', height: 210 },
        series: [72],
        labels: ['Used'],
        colors: [nxCss('--nx-chart-1')],
        plotOptions: { radialBar: { hollow: { size: '62%' }, track: { background: nxCss('--nx-secondary-subtle') }, dataLabels: { name: { fontSize: '12px', color: nxCss('--nx-text-muted'), offsetY: 18 }, value: { fontSize: '26px', fontWeight: 800, color: nxCss('--nx-text'), offsetY: -12 } } } }
      });
    });

    new Dropzone('#filesDropzone', {
      url: '#',
      autoProcessQueue: false,
      addRemoveLinks: true,
      dictDefaultMessage: '<i class="bi bi-cloud-arrow-up fs-1 d-block mb-2"></i>Drop files here or click to browse',
      maxFilesize: 20
    });

    $('#viewGrid, #viewList').on('click', function () {
      $('#viewGrid, #viewList').removeClass('active');
      $(this).addClass('active');
    });

    $('.btn-del-file').on('click', function () {
      var $tr = $(this).closest('tr');
      Swal.fire({
        title: 'Delete this file?', text: 'It will be moved to trash for 30 days.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger')
      }).then(function (r) { if (r.isConfirmed) $tr.fadeOut(200, function () { $(this).remove(); }); });
    });
  });
})(jQuery);
