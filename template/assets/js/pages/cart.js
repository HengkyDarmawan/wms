/* NexaDash — commerce/cart.html */
(function ($) {
  'use strict';

  var CART = [
    { name: 'Aurora Wireless Headphones', variant: 'Midnight', price: 129, qty: 1, img: 'p1' },
    { name: 'Orbit Mechanical Keyboard', variant: 'Tactile', price: 159, qty: 2, img: 'p4' },
    { name: 'Zenith Ergonomic Mouse', variant: 'Graphite', price: 69, qty: 1, img: 'p5' }
  ];
  var discountRate = 0;

  function money(n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { return $('<span>').text(s).html(); }

  function renderCart() {
    $('#cartList').html(CART.map(function (it, i) {
      return '<div class="nx-row-item px-4 cart-row" data-i="' + i + '">' +
        '<img src="' + nxRoot() + 'assets/img/products/' + it.img + '.svg" width="56" height="56" alt="" class="rounded">' +
        '<div class="nx-row-main"><div class="nx-row-title">' + esc(it.name) + '</div>' +
        '<div class="nx-row-sub">' + esc(it.variant) + ' &middot; ' + money(it.price) + ' each</div></div>' +
        '<div class="nx-qty"><button type="button" class="qty-minus" aria-label="Decrease">&minus;</button>' +
        '<input type="text" value="' + it.qty + '" readonly aria-label="Quantity">' +
        '<button type="button" class="qty-plus" aria-label="Increase">+</button></div>' +
        '<strong class="nx-num ms-3" style="min-width:86px;text-align:right">' + money(it.price * it.qty) + '</strong>' +
        '<button class="nx-icon-btn ms-2 cart-remove" type="button" aria-label="Remove item"><i class="bi bi-trash"></i></button>' +
        '</div>';
    }).join(''));

    $('#cartEmpty').toggleClass('d-none', CART.length > 0);
    $('#cartCount').text(CART.reduce(function (s, i) { return s + i.qty; }, 0) + ' items');
    totals();
  }

  function totals() {
    var subtotal = CART.reduce(function (s, i) { return s + i.price * i.qty; }, 0);
    var discount = subtotal * discountRate;
    var shipping = CART.length === 0 ? 0 : (subtotal - discount >= 500 ? 0 : 12);
    var tax = (subtotal - discount) * 0.08;
    var total = subtotal - discount + shipping + tax;

    $('#sumSubtotal').text(money(subtotal));
    $('#sumDiscount').text('−' + money(discount));
    $('#sumShipping').text(shipping === 0 ? 'Free' : money(shipping));
    $('#sumTax').text(money(tax));
    $('#sumTotal').text(money(total));

    var missing = Math.max(0, 500 - (subtotal - discount));
    $('#freeShipMsg').text(missing > 0 ? 'Add ' + money(missing) + ' more to qualify.' : 'You qualify for free shipping.');
  }

  document.addEventListener('nx:layout-ready', function () {
    renderCart();

    $('#cartList').on('click', '.qty-plus', function () {
      CART[$(this).closest('.cart-row').data('i')].qty++;
      renderCart();
    });
    $('#cartList').on('click', '.qty-minus', function () {
      var it = CART[$(this).closest('.cart-row').data('i')];
      it.qty = Math.max(1, it.qty - 1);
      renderCart();
    });
    $('#cartList').on('click', '.cart-remove', function () {
      CART.splice($(this).closest('.cart-row').data('i'), 1);
      renderCart();
    });

    $('.cart-add').on('click', function () {
      CART.push({ name: $(this).data('name'), variant: 'Default', price: +$(this).data('price'), qty: 1, img: $(this).data('img') });
      renderCart();
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Added to cart', showConfirmButton: false, timer: 1500 });
    });

    $('#cartClear').on('click', function () {
      Swal.fire({ title: 'Empty the cart?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Clear', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) { if (r.isConfirmed) { CART.length = 0; renderCart(); } });
    });

    $('#promoApply').on('click', function () {
      var code = ($('#promoInput').val() || '').trim().toUpperCase();
      if (code === 'WELCOME10') {
        discountRate = 0.1;
        $('#promoMsg').attr('class', 'small text-success').text('WELCOME10 applied — 10% off.');
      } else {
        discountRate = 0;
        $('#promoMsg').attr('class', 'small text-danger').text('That code is not valid.');
      }
      totals();
    });
  });
})(jQuery);
