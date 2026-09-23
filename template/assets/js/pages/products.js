/* NexaDash — commerce-products.html */
(function ($) {
  'use strict';

  var range = [0, 300];

  function applyFilters() {
    var cats = $('.cat-filter:checked').map(function () { return this.value; }).get();
    var shown = 0;
    $('.product-item').each(function () {
      var price = parseInt($(this).data('price'), 10);
      var ok = cats.indexOf($(this).data('cat')) > -1 && price >= range[0] && price <= range[1];
      $(this).toggle(ok);
      if (ok) shown++;
    });
    $('#productCount').text(shown);
  }

  document.addEventListener('nx:layout-ready', function () {
    var slider = document.getElementById('priceSlider');
    noUiSlider.create(slider, {
      start: [0, 300], connect: true, step: 5, range: { min: 0, max: 300 },
      format: { to: function (v) { return Math.round(v); }, from: function (v) { return +v; } }
    });
    slider.noUiSlider.on('update', function (values) {
      range = [+values[0], +values[1]];
      $('#priceMin').text('$' + values[0]);
      $('#priceMax').text('$' + values[1]);
      applyFilters();
    });

    $('.cat-filter').on('change', applyFilters);
    $('#clearFilters').on('click', function () {
      $('.cat-filter').prop('checked', true);
      slider.noUiSlider.set([0, 300]);
      applyFilters();
    });

    $('#pvGrid').on('click', function () {
      $(this).addClass('active'); $('#pvTable').removeClass('active');
      $('#productGrid').removeClass('d-none'); $('#productTable').addClass('d-none');
    });
    $('#pvTable').on('click', function () {
      $(this).addClass('active'); $('#pvGrid').removeClass('active');
      $('#productTable').removeClass('d-none'); $('#productGrid').addClass('d-none');
    });

    $('#sortBy').on('change', function () {
      var mode = this.value;
      var $items = $('.product-item').get();
      $items.sort(function (a, b) {
        var pa = +$(a).data('price'), pb = +$(b).data('price');
        if (mode === 'Price: low to high') return pa - pb;
        if (mode === 'Price: high to low') return pb - pa;
        return 0;
      });
      $('#productGrid').append($items);
    });
  });
})(jQuery);
