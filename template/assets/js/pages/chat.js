/* NexaDash — apps-chat.html */
(function ($) {
  'use strict';

  function scrollBottom() {
    var el = document.getElementById('chatBody');
    el.scrollTop = el.scrollHeight;
  }
  function time() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  document.addEventListener('nx:layout-ready', function () {
    scrollBottom();

    $('#chatForm').on('submit', function (e) {
      e.preventDefault();
      var text = $('#chatInput').val().trim();
      if (!text) return;
      var html = '<div class="nx-msg out">' +
        '<span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=12" alt=""></span>' +
        '<div><div class="nx-bubble"></div><div class="nx-msg-time">' + time() + '</div></div></div>';
      var $msg = $(html);
      $msg.find('.nx-bubble').text(text);
      $('#typingIndicator').before($msg);
      $('#chatInput').val('');
      scrollBottom();

      // Balasan otomatis untuk demo.
      setTimeout(function () {
        var reply = $('<div class="nx-msg in"><span class="nx-avatar nx-avatar-sm"><img src="https://i.pravatar.cc/48?img=47" alt=""></span>' +
          '<div><div class="nx-bubble">Got it &mdash; I\'ll update the doc now.</div><div class="nx-msg-time">' + time() + '</div></div></div>');
        $('#typingIndicator').before(reply);
        scrollBottom();
      }, 1400);
    });

    // Ganti kontak.
    $('#contactList').on('click', '.nx-contact', function () {
      $('.nx-contact').removeClass('active');
      $(this).addClass('active').find('.badge').remove();
      $('#chatName').text($(this).data('name'));
      $('#chatAvatar').html('<img src="https://i.pravatar.cc/64?img=' + $(this).data('avatar') + '" alt="">');
    });

    // Filter kontak.
    $('#contactSearch').on('input', function () {
      var q = this.value.toLowerCase();
      $('#contactList .nx-contact').each(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
      });
    });
  });
})(jQuery);
