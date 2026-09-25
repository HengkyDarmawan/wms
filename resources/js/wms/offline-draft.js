/* ============================================================
   Draf lokal (Blueprint §11, A-49, A-193)
   Isian di dalam [data-draft="<kunci>"] disimpan ke localStorage
   setiap kali berubah, dipulihkan saat halaman dibuka lagi (mis.
   setelah sinyal putus atau halaman tertutup), lalu dihapus saat
   komponen mengirim event `draft-clear` setelah tersimpan di server.
   Hanya nilai input ber-wire:model; berkas/foto tidak disimpan.
   ============================================================ */

(function () {
  'use strict';

  var awalan = 'wms-draft:' + location.host + ':';

  function simpanan(kunci) {
    try { return JSON.parse(localStorage.getItem(awalan + kunci) || 'null'); } catch (e) { return null; }
  }

  function tulis(kunci, data) {
    try { localStorage.setItem(awalan + kunci, JSON.stringify(data)); } catch (e) { /* penyimpanan penuh/diblokir */ }
  }

  function hapus(kunci) {
    try { localStorage.removeItem(awalan + kunci); } catch (e) { /* abaikan */ }
  }

  function modelDari(el) {
    for (var i = 0; i < el.attributes.length; i++) {
      if (el.attributes[i].name.indexOf('wire:model') === 0) return el.attributes[i].value;
    }
    return null;
  }

  function status(wadah, teks) {
    var s = wadah.querySelector('[data-draft-status]');
    if (s) { s.textContent = teks; s.classList.toggle('d-none', teks === ''); }
  }

  function pulihkan(wadah) {
    var kunci = wadah.getAttribute('data-draft');
    var data = simpanan(kunci);
    if (!data || !data.nilai) return;

    var dipulihkan = 0;
    wadah.querySelectorAll('input, select, textarea').forEach(function (el) {
      var m = modelDari(el);
      if (!m || el.type === 'file' || !(m in data.nilai)) return;
      if (el.value !== '' || el.value === data.nilai[m]) return; // isian dari server menang
      el.value = data.nilai[m];
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      dipulihkan++;
    });

    if (dipulihkan > 0) {
      status(wadah, 'Draf di perangkat ini dipulihkan (' + new Date(data.waktu).toLocaleString('id-ID') + '). Simpan agar terkirim.');
    }
  }

  function pantau(wadah) {
    if (wadah.__drafDipantau) return;
    wadah.__drafDipantau = true;

    var kunci = wadah.getAttribute('data-draft');
    var catat = function (e) {
      var m = e.target && e.target.attributes ? modelDari(e.target) : null;
      if (!m || e.target.type === 'file') return;
      var data = simpanan(kunci) || { nilai: {} };
      data.nilai[m] = e.target.value;
      data.waktu = Date.now();
      tulis(kunci, data);
      status(wadah, navigator.onLine ? '' : 'Offline: isian tersimpan di perangkat. Tekan Simpan lagi saat sinyal kembali.');
    };

    wadah.addEventListener('input', catat);
    wadah.addEventListener('change', catat);
    pulihkan(wadah);
  }

  function pasang() {
    document.querySelectorAll('[data-draft]').forEach(pantau);
  }

  window.addEventListener('draft-clear', function (e) {
    var d = e.detail || {};
    var kunci = d.key || (d[0] && d[0].key);
    if (kunci) {
      hapus(kunci);
      document.querySelectorAll('[data-draft="' + kunci + '"]').forEach(function (w) { status(w, ''); });
    }
  });

  document.addEventListener('DOMContentLoaded', pasang);
  document.addEventListener('livewire:navigated', pasang);
  document.addEventListener('livewire:init', function () {
    if (window.Livewire && window.Livewire.hook) {
      window.Livewire.hook('morph.updated', function () { setTimeout(pasang, 0); });
    }
  });
})();
