/* NexaDash — commerce/reviews.html */
(function ($) {
  'use strict';

  var REVIEWS = [
    { id: 1, name: 'Maria Gomez', img: 45, product: 'Aurora Wireless Headphones', prodImg: 'p1', rating: 5, date: 'Sep 8, 2026', status: 'published', verified: true,
      text: 'Genuinely the most comfortable pair I have owned. The transparency mode is good enough that I forget to take them off in meetings.' },
    { id: 2, name: 'James Lee', img: 13, product: 'Aurora Wireless Headphones', prodImg: 'p1', rating: 4, date: 'Sep 2, 2026', status: 'published', verified: true,
      text: 'Sound is excellent and battery claims hold up. Docking a star because the case is bulkier than it needs to be.' },
    { id: 3, name: 'Anonymous', img: 60, product: 'Pulse Portable Speaker', prodImg: 'p3', rating: 1, date: 'Sep 11, 2026', status: 'flagged', verified: false,
      text: 'BUY CHEAP SPEAKERS AT my-discount-site dot example — best prices guaranteed!!!' },
    { id: 4, name: 'Hana Sato', img: 20, product: 'Orbit Mechanical Keyboard', prodImg: 'p4', rating: 5, date: 'Sep 10, 2026', status: 'pending', verified: true,
      text: 'The tactile switches are quieter than I expected, which matters in an open office. Keycaps feel like they will outlast the laptop.' },
    { id: 5, name: 'Piotr Nowak', img: 57, product: 'Nova Smartwatch', prodImg: 'p2', rating: 3, date: 'Sep 9, 2026', status: 'pending', verified: true,
      text: 'Battery is two days, not the five advertised, once you turn the always-on display on. Everything else is solid.' },
    { id: 6, name: 'Amina Diallo', img: 31, product: 'Zenith Ergonomic Mouse', prodImg: 'p5', rating: 4, date: 'Sep 7, 2026', status: 'pending', verified: false,
      text: 'Took a week to adjust to the shape but my wrist stopped aching, so it did the job.' },
    { id: 7, name: 'Spam Bot', img: 66, product: 'Lumen Desk Lamp', prodImg: 'p6', rating: 5, date: 'Sep 6, 2026', status: 'flagged', verified: false,
      text: 'Click here for free lamps!!! Limited time only!!!' },
    { id: 8, name: 'Olivia Brown', img: 44, product: 'Atlas Travel Backpack', prodImg: 'p8', rating: 5, date: 'Sep 4, 2026', status: 'pending', verified: true,
      text: 'Fits a 16 inch laptop plus three days of clothes and still counts as a personal item on most airlines.' },
    { id: 9, name: 'Chen Wei', img: 25, product: 'Vertex Streaming Camera', prodImg: 'p7', rating: 4, date: 'Sep 1, 2026', status: 'published', verified: true,
      text: 'Auto-framing is reliable and the low-light handling is much better than the built-in webcam it replaced.' }
  ];

  var filter = 'all';
  var STATUS_BADGE = { published: ['success', 'Published'], pending: ['warning', 'Pending'], flagged: ['danger', 'Flagged'] };

  function esc(s) { return $('<span>').text(s).html(); }
  function stars(n) {
    var out = '';
    for (var i = 1; i <= 5; i++) out += n >= i ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
    return '<span class="nx-stars sm">' + out + '</span>';
  }

  function card(r) {
    var b = STATUS_BADGE[r.status];
    return '<div class="card mb-3 rv-item" data-id="' + r.id + '" data-status="' + r.status + '"><div class="card-body">' +
      '<div class="d-flex flex-wrap align-items-center gap-3 mb-3">' +
      '<span class="nx-avatar"><img src="https://i.pravatar.cc/64?img=' + r.img + '" alt=""></span>' +
      '<div class="min-w-0"><div class="d-flex align-items-center gap-2"><strong>' + esc(r.name) + '</strong>' +
      (r.verified ? '<span class="badge badge-soft-success"><i class="bi bi-patch-check me-1"></i>Verified buyer</span>' : '') + '</div>' +
      '<div class="small text-muted">' + r.date + '</div></div>' +
      '<div class="ms-auto d-flex align-items-center gap-2">' + stars(r.rating) +
      '<span class="badge badge-soft-' + b[0] + ' rv-badge">' + b[1] + '</span></div></div>' +
      '<div class="d-flex align-items-center gap-2 mb-2">' +
      '<img src="' + nxRoot() + 'assets/img/products/' + r.prodImg + '.svg" width="28" height="28" alt="" class="rounded">' +
      '<span class="small text-muted">' + esc(r.product) + '</span></div>' +
      '<p class="mb-3">' + esc(r.text) + '</p>' +
      '<div class="d-flex flex-wrap gap-2">' +
      '<button class="btn btn-sm btn-soft-success rv-approve" type="button"><i class="bi bi-check2 me-1"></i>Approve</button>' +
      '<button class="btn btn-sm btn-soft-secondary rv-reply" type="button"><i class="bi bi-reply me-1"></i>Reply</button>' +
      '<button class="btn btn-sm btn-soft-warning rv-flag" type="button"><i class="bi bi-flag me-1"></i>Flag</button>' +
      '<button class="btn btn-sm btn-soft-danger ms-auto rv-delete" type="button"><i class="bi bi-trash me-1"></i>Delete</button>' +
      '</div><div class="rv-reply-box mt-3 d-none"></div>' +
      '</div></div>';
  }

  function render() {
    var q = ($('#rvSearch').val() || '').toLowerCase();
    var list = REVIEWS.filter(function (r) {
      return (filter === 'all' || r.status === filter) &&
        (r.name + ' ' + r.product + ' ' + r.text).toLowerCase().indexOf(q) > -1;
    });
    $('#rvList').html(list.length ? list.map(card).join('')
      : '<div class="card"><div class="card-body text-center text-muted py-5"><i class="bi bi-chat-square-x d-block fs-3 mb-2"></i>No reviews match that filter.</div></div>');
    $('#rvCount').text(list.length);
  }

  document.addEventListener('nx:layout-ready', function () {
    render();

    nxMountChart('#reviewChart', function () {
      return $.extend(true, nxApexBase(), {
        chart: { type: 'line', height: 190 },
        series: [{ name: 'Average rating', data: [4.2, 4.3, 4.1, 4.4, 4.5, 4.4, 4.6, 4.5, 4.7, 4.6, 4.6, 4.6] }],
        colors: [nxCss('--nx-warning')],
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: { categories: ['Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'], axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { min: 3.5, max: 5, tickAmount: 3 }
      });
    });

    $('#rvSearch').on('input', render);
    $('#rvFilters').on('click', 'button', function () {
      $('#rvFilters button').removeClass('btn-soft-primary active').addClass('btn-soft-secondary');
      $(this).removeClass('btn-soft-secondary').addClass('btn-soft-primary active');
      filter = $(this).data('f');
      render();
    });

    function setStatus($item, status) {
      var r = REVIEWS.find(function (x) { return x.id === $item.data('id'); });
      r.status = status;
      render();
    }

    $('#rvList').on('click', '.rv-approve', function () {
      setStatus($(this).closest('.rv-item'), 'published');
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Review published', showConfirmButton: false, timer: 1500 });
    });
    $('#rvList').on('click', '.rv-flag', function () { setStatus($(this).closest('.rv-item'), 'flagged'); });

    $('#rvList').on('click', '.rv-reply', function () {
      var $box = $(this).closest('.card-body').find('.rv-reply-box');
      if (!$box.children().length) {
        $box.html('<div class="d-flex gap-2"><span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
          '<input class="form-control form-control-sm rv-reply-input" placeholder="Reply publicly as NexaDash&hellip;"></div>');
      }
      $box.toggleClass('d-none');
      $box.find('input').trigger('focus');
    });
    $('#rvList').on('keydown', '.rv-reply-input', function (e) {
      if (e.key !== 'Enter' || !this.value.trim()) return;
      var $reply = $('<div class="d-flex gap-2 mt-2"><span class="nx-avatar nx-avatar-sm" style="color:var(--nx-primary);background:var(--nx-primary-subtle)"><i class="bi bi-shop"></i></span>' +
        '<div class="p-2 px-3 rounded flex-grow-1" style="background:var(--nx-primary-subtle)"><div class="small fw-semibold">NexaDash</div><div class="small reply-text"></div></div></div>');
      $reply.find('.reply-text').text(this.value.trim());
      $(this).closest('.rv-reply-box').append($reply);
      this.value = '';
    });

    $('#rvList').on('click', '.rv-delete', function () {
      var id = $(this).closest('.rv-item').data('id');
      Swal.fire({ title: 'Delete this review?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: nxCss('--nx-danger') })
        .then(function (r) {
          if (!r.isConfirmed) return;
          REVIEWS = REVIEWS.filter(function (x) { return x.id !== id; });
          render();
        });
    });
  });
})(jQuery);
