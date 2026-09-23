/* NexaDash — apps/social.html */
(function ($) {
  'use strict';

  document.addEventListener('nx:layout-ready', function () {

    // Like toggle.
    $('#feed').on('click', '.btn-like', function () {
      var $btn = $(this);
      var $count = $btn.closest('.nx-post').find('.like-count').first();
      var liked = $btn.hasClass('liked');
      $btn.toggleClass('liked');
      $btn.find('i').toggleClass('bi-hand-thumbs-up bi-hand-thumbs-up-fill');
      var n = parseInt($count.text(), 10) || 0;
      $count.removeClass('d-none').text(liked ? n - 1 : n + 1);
    });

    // Buka / tutup panel komentar.
    $('#feed').on('click', '.btn-comment', function () {
      var $box = $(this).closest('.nx-post').find('.nx-comments');
      $box.toggleClass('d-none');
      if (!$box.hasClass('d-none')) $box.find('.comment-input').trigger('focus');
    });

    // Kirim komentar dengan Enter.
    $('#feed').on('keydown', '.comment-input', function (e) {
      if (e.key !== 'Enter') return;
      var text = this.value.trim();
      if (!text) return;
      var $row = $('<div class="d-flex gap-2 mb-3">' +
        '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
        '<div class="p-2 px-3 rounded flex-grow-1" style="background:var(--nx-body-bg)">' +
        '<div class="small fw-semibold">Aigars S.</div><div class="small comment-text"></div></div></div>');
      $row.find('.comment-text').text(text);
      $(this).closest('.d-flex').before($row);
      this.value = '';
    });

    // Buat kiriman baru.
    $('#postSubmit').on('click', function () {
      var text = $('#postInput').val().trim();
      if (!text) { $('#postInput').addClass('is-invalid'); return; }
      $('#postInput').removeClass('is-invalid');
      var $post = $('<article class="card mb-4 nx-post"><div class="card-body">' +
        '<div class="d-flex align-items-center gap-3 mb-3">' +
        '<span class="nx-avatar"><img src="https://i.pravatar.cc/80?img=12" alt=""></span>' +
        '<div class="min-w-0"><div class="fw-semibold">Aigars Silkalns</div><div class="small text-muted">Administrator &middot; just now</div></div>' +
        '<span class="badge badge-soft-primary ms-auto">New</span></div>' +
        '<p class="post-text"></p>' +
        '<div class="d-flex align-items-center justify-content-between small text-muted">' +
        '<span><i class="bi bi-hand-thumbs-up-fill text-primary"></i> <span class="like-count">0</span> &middot; 0 comments</span><span>0 shares</span></div>' +
        '<div class="nx-post-actions">' +
        '<button class="nx-post-action btn-like" type="button"><i class="bi bi-hand-thumbs-up me-1"></i>Like</button>' +
        '<button class="nx-post-action btn-comment" type="button"><i class="bi bi-chat me-1"></i>Comment</button>' +
        '<button class="nx-post-action" type="button"><i class="bi bi-share me-1"></i>Share</button></div>' +
        '<div class="nx-comments mt-3 d-none"><div class="d-flex gap-2">' +
        '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
        '<input class="form-control form-control-sm comment-input" placeholder="Write a comment&hellip;"></div></div>' +
        '</div></article>');
      $post.find('.post-text').text(text);
      $('#feed').prepend($post);
      $('#postInput').val('');
    });

    // Follow / unfollow.
    $('.btn-follow').on('click', function () {
      var following = $(this).hasClass('btn-soft-secondary');
      $(this).toggleClass('btn-soft-primary btn-soft-secondary').text(following ? 'Follow' : 'Following');
    });
  });
})(jQuery);
