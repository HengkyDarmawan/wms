/* NexaDash — commerce-product-detail.html */
(function ($) {
  'use strict';
  document.addEventListener('nx:layout-ready', function () {
    $('.nx-thumb').on('click', function () {
      $('.nx-thumb').removeClass('active');
      $(this).addClass('active');
      $('#mainImage').attr('src', $(this).data('img'));
    });

    $('#qtyPlus').on('click', function () {
      $('#qtyInput').val(Math.min(99, +$('#qtyInput').val() + 1));
    });
    $('#qtyMinus').on('click', function () {
      $('#qtyInput').val(Math.max(1, +$('#qtyInput').val() - 1));
    });

    $('#btnAddCart').on('click', function () {
      Swal.fire({
        icon: 'success',
        title: 'Added to cart',
        text: $('#qtyInput').val() + ' × Aurora Wireless Headphones',
        timer: 1800, showConfirmButton: false
      });
    });

    $('#reviewForm').on('submit', function (e) {
      e.preventDefault();
      Swal.fire({ icon: 'success', title: 'Thanks for your review!', timer: 1600, showConfirmButton: false });
      this.reset();
    });
  });
})(jQuery);
