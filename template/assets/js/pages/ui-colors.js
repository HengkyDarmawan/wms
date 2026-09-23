/* NexaDash — ui-colors.html */
(function ($) {
  'use strict';

  var BRAND = ['--nx-primary', '--nx-primary-hover', '--nx-primary-subtle', '--nx-secondary', '--nx-secondary-subtle',
    '--nx-success', '--nx-success-subtle', '--nx-danger', '--nx-danger-subtle',
    '--nx-warning', '--nx-warning-subtle', '--nx-info', '--nx-info-subtle'];
  var SURFACE = ['--nx-body-bg', '--nx-card-bg', '--nx-border', '--nx-hover-bg', '--nx-sidebar-bg',
    '--nx-text', '--nx-text-body', '--nx-text-muted'];
  var CHART = ['--nx-chart-1', '--nx-chart-2', '--nx-chart-3', '--nx-chart-4', '--nx-chart-5'];

  function swatches(target, names) {
    $(target).html(names.map(function (n) {
      var v = nxCss(n);
      return '<div class="col-lg-3 col-md-4 col-6"><div class="nx-swatch" role="button" data-var="' + n + '">' +
        '<div class="nx-sw-color" style="background:' + v + '"></div>' +
        '<div class="nx-sw-meta"><div class="nx-sw-name">' + n + '</div>' +
        '<div class="text-muted">' + v + '</div></div></div></div>';
    }).join(''));
  }

  function renderAll() {
    swatches('#swatchBrand', BRAND);
    swatches('#swatchSurface', SURFACE);
    swatches('#swatchChart', CHART);
  }

  document.addEventListener('nx:layout-ready', function () {
    renderAll();

    $('#toggleThemeDemo').on('click', function () { $('#nxThemeToggle').trigger('click'); });

    $(document).on('click', '.nx-swatch', function () {
      var name = $(this).data('var');
      if (navigator.clipboard) navigator.clipboard.writeText('var(' + name + ')');
      var $t = $('<div class="toast" role="alert"><div class="toast-body d-flex align-items-center gap-2">' +
        '<i class="bi bi-clipboard-check text-success"></i>Copied <code>var(' + name + ')</code></div></div>');
      $('#colorToast').append($t);
      new bootstrap.Toast($t[0], { delay: 1800 }).show();
      $t.on('hidden.bs.toast', function () { $t.remove(); });
    });
  });

  document.addEventListener('nx:theme-changed', renderAll);
})(jQuery);
