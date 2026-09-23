/* NexaDash — plugins/media.html (Swiper + GLightbox + Cropper.js) */
(function ($) {
  'use strict';

  var PRODUCTS = [
    ['Aurora Headphones', 'Audio', '$129', 'p1'],
    ['Nova Smartwatch', 'Wearables', '$249', 'p2'],
    ['Pulse Speaker', 'Audio', '$89', 'p3'],
    ['Orbit Keyboard', 'Accessories', '$159', 'p4'],
    ['Zenith Mouse', 'Accessories', '$69', 'p5'],
    ['Lumen Desk Lamp', 'Home', '$119', 'p6'],
    ['Vertex Camera', 'Audio', '$199', 'p7'],
    ['Atlas Backpack', 'Accessories', '$89', 'p8']
  ];

  var cropper = null;

  document.addEventListener('nx:layout-ready', function () {

    /* ===================== Swiper ===================== */
    $('#swiperProducts .swiper-wrapper').html(PRODUCTS.map(function (p) {
      return '<div class="swiper-slide"><div class="card h-100"><div class="card-body">' +
        '<div class="nx-pc-img mb-3"><img src="' + nxRoot() + 'assets/img/products/' + p[3] + '.svg" alt="' + p[0] + '" style="object-fit:contain;padding:22px"></div>' +
        '<div class="small text-muted">' + p[1] + '</div>' +
        '<div class="fw-semibold">' + p[0] + '</div>' +
        '<strong class="h5 d-block mt-1">' + p[2] + '</strong>' +
        '</div></div></div>';
    }).join(''));

    new Swiper('#swiperProducts', {
      slidesPerView: 1,
      spaceBetween: 20,
      navigation: { nextEl: '#swiperProducts .swiper-button-next', prevEl: '#swiperProducts .swiper-button-prev' },
      pagination: { el: '#swiperProducts .swiper-pagination', clickable: true },
      breakpoints: { 576: { slidesPerView: 2 }, 992: { slidesPerView: 3 }, 1400: { slidesPerView: 4 } }
    });

    $('#swiperCover .swiper-wrapper').html(PRODUCTS.slice(0, 6).map(function (p) {
      return '<div class="swiper-slide" style="width:200px"><div class="card"><div class="card-body">' +
        '<div class="nx-pc-img"><img src="' + nxRoot() + 'assets/img/products/' + p[3] + '.svg" alt="" style="object-fit:contain;padding:18px"></div>' +
        '<div class="small fw-semibold mt-2 text-center">' + p[0] + '</div></div></div></div>';
    }).join(''));

    new Swiper('#swiperCover', {
      effect: 'coverflow',
      grabCursor: true,
      centeredSlides: true,
      slidesPerView: 'auto',
      loop: true,
      autoplay: { delay: 2400, disableOnInteraction: false },
      coverflowEffect: { rotate: 28, stretch: 0, depth: 120, modifier: 1, slideShadows: false }
    });

    new Swiper('#swiperQuotes', {
      effect: 'fade',
      fadeEffect: { crossFade: true },
      loop: true,
      autoplay: { delay: 4200 },
      pagination: { el: '#swiperQuotes .swiper-pagination', clickable: true }
    });

    /* ===================== GLightbox ===================== */
    var seeds = ['office', 'team', 'desk', 'city', 'code', 'meeting', 'coffee', 'server'];
    $('#galleryGrid').html(seeds.map(function (s, i) {
      var url = 'https://picsum.photos/seed/nexa-' + s + '/1200/800';
      var thumb = 'https://picsum.photos/seed/nexa-' + s + '/400/300';
      return '<div class="col-lg-3 col-sm-6">' +
        '<a class="glightbox d-block nx-pc-img" href="' + url + '" data-gallery="nexa" data-title="' + s + '" data-description="Sample image ' + (i + 1) + ' of ' + seeds.length + '">' +
        '<img src="' + thumb + '" alt="' + s + '" loading="lazy"></a></div>';
    }).join(''));

    GLightbox({ selector: '.glightbox', loop: true });
    GLightbox({ selector: '.glightbox-inline' });
    GLightbox({ selector: '.glightbox-video' });

    /* ===================== Cropper.js ===================== */
    var image = document.getElementById('cropImage');

    function initCropper() {
      if (cropper) cropper.destroy();
      cropper = new Cropper(image, {
        viewMode: 1,
        autoCropArea: 0.7,
        background: false,
        responsive: true
      });
    }
    image.addEventListener('load', initCropper);
    if (image.complete) initCropper();

    $('.crop-ratio').on('click', function () {
      $('.crop-ratio').removeClass('active');
      $(this).addClass('active');
      cropper.setAspectRatio(parseFloat($(this).data('ratio')));
    });
    $('#cropRotL').on('click', function () { cropper.rotate(-90); });
    $('#cropRotR').on('click', function () { cropper.rotate(90); });
    $('#cropFlipH').on('click', function () { cropper.scaleX(-cropper.getData().scaleX || -1); });
    $('#cropReset').on('click', function () { cropper.reset(); });

    $('#cropApply').on('click', function () {
      var canvas = cropper.getCroppedCanvas({ maxWidth: 1600, maxHeight: 1600 });
      if (!canvas) return;
      var url = canvas.toDataURL('image/png');
      $('#cropResult').attr('src', url).show();
      $('#cropPlaceholder').hide();
      $('#cropSize').text(canvas.width + ' × ' + canvas.height + ' px');
      $('#cropWeight').text(Math.round(url.length * 0.75 / 1024) + ' KB');
      $('#cropDownload').attr('href', url).removeClass('disabled');
    });

    $('#cropUpload').on('change', function () {
      var f = this.files && this.files[0];
      if (!f) return;
      var reader = new FileReader();
      reader.onload = function (e) { image.src = e.target.result; };
      reader.readAsDataURL(f);
    });
  });
})(jQuery);
