/* ============================================================
   Pindai barcode/QR dengan kamera (Blueprint §6.10, §11, A-193)
   Setiap input ber-atribut data-scan mendapat tombol kamera. Memakai
   BarcodeDetector bawaan browser (Chrome/Edge Android); bila tidak
   tersedia, tombol tidak muncul dan scanner USB/Bluetooth (mode
   keyboard) tetap bisa dipakai langsung di input.
   ============================================================ */

(function () {
  'use strict';

  var didukung = 'BarcodeDetector' in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

  function tombol(input) {
    if (input.__scanTombol || !didukung) return;
    input.__scanTombol = true;

    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-outline-secondary';
    b.setAttribute('aria-label', 'Pindai dengan kamera');
    b.innerHTML = '<i class="bi bi-upc-scan"></i>';
    b.addEventListener('click', function () { pindai(input); });

    if (input.parentElement && input.parentElement.classList.contains('input-group')) {
      input.parentElement.appendChild(b);
    } else {
      var grup = document.createElement('div');
      grup.className = 'input-group';
      input.parentNode.insertBefore(grup, input);
      grup.appendChild(input);
      grup.appendChild(b);
    }
  }

  function pindai(input) {
    var lapis = document.createElement('div');
    lapis.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-dark bg-opacity-75';
    lapis.style.zIndex = 2000;
    lapis.innerHTML = '<video playsinline muted style="max-width:92vw;max-height:70vh;border-radius:.5rem"></video>' +
      '<button type="button" class="btn btn-light mt-3">Batal</button>';
    document.body.appendChild(lapis);

    var video = lapis.querySelector('video');
    var aliran = null;
    var selesai = false;
    var tutup = function () {
      selesai = true;
      if (aliran) aliran.getTracks().forEach(function (t) { t.stop(); });
      lapis.remove();
    };
    lapis.querySelector('button').addEventListener('click', tutup);

    var detektor = new window.BarcodeDetector();
    var loop = function () {
      if (selesai) return;
      detektor.detect(video).then(function (hasil) {
        if (hasil.length > 0) {
          input.value = hasil[0].rawValue;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
          // Seperti scanner keyboard yang mengirim Enter (alur pindai PCK, A-203).
          input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
          tutup();
        } else {
          requestAnimationFrame(loop);
        }
      }).catch(function () { requestAnimationFrame(loop); });
    };

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
      aliran = s;
      video.srcObject = s;
      return video.play();
    }).then(loop).catch(function () {
      tutup();
      console.warn('Kamera tidak bisa dibuka; ketik atau pakai scanner.');
    });
  }

  function pasang() {
    document.querySelectorAll('input[data-scan]').forEach(tombol);
  }

  document.addEventListener('DOMContentLoaded', pasang);
  document.addEventListener('livewire:navigated', pasang);
  document.addEventListener('livewire:init', function () {
    if (window.Livewire && window.Livewire.hook) {
      window.Livewire.hook('morph.updated', function () { setTimeout(pasang, 0); });
    }
  });
})();
