/* NexaDash — ui/media-player.html */
(function ($) {
  'use strict';

  function fmt(s) {
    if (!isFinite(s)) return '0:00';
    var m = Math.floor(s / 60);
    var r = Math.floor(s % 60);
    return m + ':' + (r < 10 ? '0' : '') + r;
  }

  function wire(media, opts) {
    var $bar = $(opts.bar);
    var $fill = $bar.children('div');

    function icon(playing) {
      $(opts.play).html('<i class="bi ' + (playing ? 'bi-pause-fill' : 'bi-play-fill') + '"></i>');
    }

    $(opts.play).on('click', function () {
      // play() menolak promise kalau browser memblokir autoplay; jangan biarkan lolos.
      if (media.paused) {
        var p = media.play();
        if (p && p.catch) p.catch(function () { icon(false); });
      } else {
        media.pause();
      }
    });

    media.addEventListener('play', function () { icon(true); });
    media.addEventListener('pause', function () { icon(false); });
    media.addEventListener('ended', function () { icon(false); });

    media.addEventListener('timeupdate', function () {
      var pct = media.duration ? media.currentTime / media.duration * 100 : 0;
      $fill.css('width', pct + '%');
      if (opts.time) $(opts.time).text(fmt(media.currentTime) + ' / ' + fmt(media.duration));
      if (opts.cur) $(opts.cur).text(fmt(media.currentTime));
    });
    media.addEventListener('loadedmetadata', function () {
      if (opts.time) $(opts.time).text(fmt(0) + ' / ' + fmt(media.duration));
      if (opts.dur) $(opts.dur).text(fmt(media.duration));
    });

    $bar.on('click', function (e) {
      if (!media.duration) return;
      var rect = this.getBoundingClientRect();
      media.currentTime = (e.clientX - rect.left) / rect.width * media.duration;
    });
  }

  document.addEventListener('nx:layout-ready', function () {
    var video = document.getElementById('mpVideo');
    var audio = document.getElementById('mpAudio');

    wire(video, { play: '#mpVideoPlay', bar: '#mpVideoBar', time: '#mpVideoTime' });
    wire(audio, { play: '#mpAudioPlay', bar: '#mpAudioBar', cur: '#mpAudioCur', dur: '#mpAudioDur' });

    $('#mpVideoBack').on('click', function () { video.currentTime = Math.max(0, video.currentTime - 10); });
    $('#mpVideoFwd').on('click', function () { video.currentTime = Math.min(video.duration || 0, video.currentTime + 10); });
    $('#mpVideoRate').on('change', function () { video.playbackRate = parseFloat(this.value); });
    $('#mpVideoVol').on('input', function () {
      video.volume = parseFloat(this.value);
      video.muted = video.volume === 0;
      $('#mpVideoMute').html('<i class="bi ' + (video.muted ? 'bi-volume-mute' : 'bi-volume-up') + '"></i>');
    });
    $('#mpVideoMute').on('click', function () {
      video.muted = !video.muted;
      $(this).html('<i class="bi ' + (video.muted ? 'bi-volume-mute' : 'bi-volume-up') + '"></i>');
    });
    $('#mpVideoFull').on('click', function () {
      if (video.requestFullscreen) video.requestFullscreen();
    });

    $('#mpPlaylist').on('click', '.playlist-item', function () {
      $('.playlist-item').removeClass('active').find('.nx-icon-sq')
        .attr('class', 'nx-icon-sq secondary').html('<i class="bi bi-music-note"></i>');
      $(this).addClass('active').find('.nx-icon-sq')
        .attr('class', 'nx-icon-sq primary').html('<i class="bi bi-play-fill"></i>');
    });
  });
})(jQuery);
