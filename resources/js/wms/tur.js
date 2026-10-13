/* ============================================================
   Pemandu di layar (A-414): tombol "Tunjukkan caranya" memutar tur
   driver.js (dibundel Vite, tanpa CDN). Langkah ditulis di Blade lewat
   komponen <x-tur>, sebagai JSON di <script type="application/json">:
     [{ "el": "#item-kode", "judul": "…", "isi": "…" }, …]
   - `el` kosong / tidak ada di layar → kotak penjelasan di tengah layar,
     jadi tur tetap jalan walau sebagian isian tersembunyi (mis. tab lain).
   - Event delegasi di document: tetap berfungsi setelah Livewire
     menggambar ulang bagian halaman.
   ============================================================ */

import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

function langkahDari(tombol) {
  const sumber = document.getElementById(tombol.getAttribute('data-tur-mulai'));
  if (!sumber) return [];

  let daftar = [];
  try { daftar = JSON.parse(sumber.textContent || '[]'); } catch (e) { return []; }

  return daftar.map(function (l) {
    const el = l.el ? document.querySelector(l.el) : null;
    const langkah = { popover: { title: l.judul || '', description: l.isi || '' } };
    // Elemen yang tersembunyi (lebar 0) dianggap tidak ada → tampil di tengah.
    if (el && el.getClientRects().length > 0) langkah.element = el;
    return langkah;
  });
}

document.addEventListener('click', function (e) {
  const tombol = e.target.closest('[data-tur-mulai]');
  if (!tombol) return;
  e.preventDefault();

  const steps = langkahDari(tombol);
  if (!steps.length) return;

  driver({
    showProgress: true,
    progressText: '{{current}} dari {{total}}',
    nextBtnText: 'Lanjut →',
    prevBtnText: '← Kembali',
    doneBtnText: 'Selesai',
    allowClose: true,
    smoothScroll: true,
    stagePadding: 6,
    popoverClass: 'nx-tur',
    steps: steps,
  }).drive();
});
