/* ============================================================
   Kanvas tanda tangan bukti terima (15-picking-shipment §6, A-231)
   Setiap [data-signature] berisi <canvas>, input tersembunyi
   [data-signature-target] (menerima data URL PNG), dan tombol
   [data-signature-clear]. Bekerja dengan jari, pena, atau mouse;
   nilai input diperbarui setiap goresan selesai dan memicu event
   `input` supaya wire:model / form biasa ikut membacanya.
   ============================================================ */

(function () {
  'use strict';

  function pasang(wadah) {
    if (wadah.__signature) return;
    wadah.__signature = true;

    var kanvas = wadah.querySelector('canvas');
    var target = wadah.querySelector('[data-signature-target]');
    var hapus = wadah.querySelector('[data-signature-clear]');
    if (!kanvas || !target) return;

    var ctx = kanvas.getContext('2d');
    var menggambar = false;
    var kosong = true;

    function ukur() {
      // Resolusi mengikuti lebar tampil supaya goresan tidak pecah di HP.
      var lebar = kanvas.clientWidth || 300;
      var tinggi = kanvas.getAttribute('height') ? parseInt(kanvas.getAttribute('height'), 10) : 160;
      var skala = window.devicePixelRatio || 1;
      kanvas.width = Math.round(lebar * skala);
      kanvas.height = Math.round(tinggi * skala);
      kanvas.style.height = tinggi + 'px';
      ctx.setTransform(skala, 0, 0, skala, 0, 0);
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#111';
    }

    function titik(e) {
      var r = kanvas.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function mulai(e) {
      e.preventDefault();
      menggambar = true;
      var p = titik(e);
      ctx.beginPath();
      ctx.moveTo(p.x, p.y);
      kanvas.setPointerCapture && kanvas.setPointerCapture(e.pointerId);
    }

    function gerak(e) {
      if (!menggambar) return;
      e.preventDefault();
      var p = titik(e);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      kosong = false;
    }

    function selesai(e) {
      if (!menggambar) return;
      menggambar = false;
      if (e) e.preventDefault();
      simpan();
    }

    function simpan() {
      target.value = kosong ? '' : kanvas.toDataURL('image/png');
      target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function bersihkan() {
      ctx.save();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, kanvas.width, kanvas.height);
      ctx.restore();
      kosong = true;
      simpan();
    }

    ukur();
    kanvas.style.touchAction = 'none';
    kanvas.addEventListener('pointerdown', mulai);
    kanvas.addEventListener('pointermove', gerak);
    kanvas.addEventListener('pointerup', selesai);
    kanvas.addEventListener('pointercancel', selesai);
    kanvas.addEventListener('pointerleave', selesai);
    if (hapus) hapus.addEventListener('click', bersihkan);
  }

  function pasangSemua() {
    document.querySelectorAll('[data-signature]').forEach(pasang);
  }

  document.addEventListener('DOMContentLoaded', pasangSemua);
  // Livewire memuat ulang bagian halaman; kanvas baru ikut dipasang (pola sama dengan scan.js).
  document.addEventListener('livewire:navigated', pasangSemua);
  document.addEventListener('livewire:init', function () {
    if (window.Livewire && window.Livewire.hook) {
      window.Livewire.hook('morph.updated', function () { setTimeout(pasangSemua, 0); });
    }
  });
})();
