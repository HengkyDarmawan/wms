/* NexaDash — plugins/index.html: katalog library yang bisa dicari & difilter. */
(function ($) {
  'use strict';

  var cat = 'all';

  function card(p) {
    // Rute halaman disimpan datar; beri awalan agar benar dari folder mana pun.
    var href = nxRoot() + (window.NX_ROUTES && NX_ROUTES[p.page] ? NX_ROUTES[p.page] : p.page);
    return '<div class="col-xl-4 col-md-6 plugin-card" data-cat="' + p.cat + '">' +
      '<div class="card h-100 nx-card-lift"><div class="card-body d-flex flex-column">' +
      '<div class="d-flex align-items-start gap-3 mb-2">' +
      '<span class="nx-icon-sq ' + p.color + '"><i class="bi ' + p.icon + '"></i></span>' +
      '<div class="min-w-0"><h3 class="h5 mb-0 text-truncate">' + p.name + '</h3>' +
      '<div class="small text-muted">v' + p.v + ' &middot; ' + p.lic + '</div></div>' +
      '</div>' +
      '<p class="small text-muted flex-grow-1">' + p.use + '</p>' +
      '<div class="d-flex gap-2">' +
      '<a class="btn btn-sm btn-soft-primary" href="' + href + '"><i class="bi bi-eye me-1"></i>Demo</a>' +
      '<a class="btn btn-sm btn-soft-secondary" href="' + p.site + '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Docs</a>' +
      '</div></div></div></div>';
  }

  function render() {
    var q = ($('#pluginSearch').val() || '').toLowerCase().trim();
    var list = window.NX_PLUGINS.filter(function (p) {
      var okCat = cat === 'all' || p.cat === cat;
      var okQ = !q || (p.name + ' ' + p.use).toLowerCase().indexOf(q) > -1;
      return okCat && okQ;
    });
    $('#pluginGrid').html(list.length
      ? list.map(card).join('')
      : '<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">' +
        '<i class="bi bi-search d-block fs-3 mb-2"></i>No library matches that search.</div></div></div>');
    $('#pluginCount').text(list.length);
  }

  document.addEventListener('nx:layout-ready', function () {
    render();
    $('#pluginSearch').on('input', render);
    $('#pluginFilters').on('click', 'button', function () {
      $('#pluginFilters button').removeClass('btn-soft-primary active').addClass('btn-soft-secondary');
      $(this).removeClass('btn-soft-secondary').addClass('btn-soft-primary active');
      cat = $(this).data('cat');
      render();
    });
  });
})(jQuery);
