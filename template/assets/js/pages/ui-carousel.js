/* NexaDash — ui/carousel.html */
(function ($) {
  'use strict';

  var PRODUCTS = [
    ['Aurora Headphones', '$129', 'p1'], ['Nova Smartwatch', '$249', 'p2'],
    ['Pulse Speaker', '$89', 'p3'], ['Orbit Keyboard', '$159', 'p4'],
    ['Zenith Mouse', '$69', 'p5'], ['Lumen Lamp', '$119', 'p6']
  ];

  document.addEventListener('nx:layout-ready', function () {
    $('#carSwiper .swiper-wrapper').html(PRODUCTS.map(function (p) {
      return '<div class="swiper-slide"><div class="card h-100"><div class="card-body">' +
        '<div class="nx-pc-img mb-2"><img src="' + nxRoot() + 'assets/img/products/' + p[2] + '.svg" alt="' + p[0] + '" style="object-fit:contain;padding:20px"></div>' +
        '<div class="small fw-semibold">' + p[0] + '</div><div class="small text-muted">' + p[1] + '</div>' +
        '</div></div></div>';
    }).join(''));

    new Swiper('#carSwiper', {
      slidesPerView: 1,
      spaceBetween: 16,
      navigation: { nextEl: '#carSwiper .swiper-button-next', prevEl: '#carSwiper .swiper-button-prev' },
      breakpoints: { 576: { slidesPerView: 2 }, 992: { slidesPerView: 4 }, 1400: { slidesPerView: 5 } }
    });
  });
})(jQuery);
