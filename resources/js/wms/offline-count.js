/* ============================================================
   Hitung opname offline (32-pwa-offline, AD-16, A-438).
   JavaScript biasa (bukan Alpine/Livewire) karena halaman ini harus jalan
   saat server tidak terjangkau: bundel /build di-cache service worker.
   Data: tugas diunduh saat online; isian disimpan di perangkat sebagai
   antrean transaksi ber-UUID, dikirim saat online/dibuka/ditekan "Kirim".
   ponytail: localStorage (±5 MB, cukup untuk angka hitung); pindah ke
   IndexedDB saat foto offline ditambahkan.
   ============================================================ */
(function () {
  'use strict';

  function el(tag, attrs, kids) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') e.textContent = attrs[k];
      else if (k.indexOf('on') === 0) e.addEventListener(k.slice(2), attrs[k]);
      else e.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) e.appendChild(c); });
    return e;
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function pasang(akar) {
    if (akar.__offlineCount) return;
    akar.__offlineCount = true;

    var KUNCI = 'wms-offline-count:' + location.host + ':' + akar.dataset.user;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var status = akar.querySelector('[data-status]');
    var daftar = akar.querySelector('[data-daftar]');

    function baca() {
      try { return JSON.parse(localStorage.getItem(KUNCI)) || { tugas: [], antrean: [], diunduh: null }; }
      catch (e) { return { tugas: [], antrean: [], diunduh: null }; }
    }
    function tulis(d) {
      try { localStorage.setItem(KUNCI, JSON.stringify(d)); } catch (e) { pesan('Penyimpanan perangkat penuh atau diblokir.', 'danger'); }
    }
    function pesan(teks, jenis) {
      status.className = 'alert py-2 small alert-' + (jenis || 'secondary');
      status.textContent = teks;
    }

    function render() {
      var d = baca();
      daftar.innerHTML = '';
      var antre = d.antrean.filter(function (a) { return a.status !== 'posted'; });
      akar.querySelector('[data-jumlah-antrean]').textContent = String(antre.length);
      if (!d.tugas.length) {
        daftar.appendChild(el('p', { class: 'text-muted small', text: navigator.onLine ? 'Tidak ada bin yang menunggu dihitung.' : 'Belum ada tugas tersimpan di perangkat. Buka halaman ini sekali saat ada sinyal.' }));
      }
      d.tugas.forEach(function (t) {
        var terkirim = d.antrean.find(function (a) { return a.payload.assignment_id === t.id; });
        var badan = el('div', { class: 'card-body' });
        t.baris.forEach(function (b) {
          var nilai = (terkirim && terkirim.payload.qtys[b.id] != null) ? terkirim.payload.qtys[b.id] : '';
          var input = b.unit
            ? el('select', { class: 'form-select form-select-sm', 'data-baris': b.id, 'aria-label': 'Ada / tidak ' + b.kode }, [
                el('option', { value: '', text: 'Belum' }), el('option', { value: '1', text: 'Ada' }), el('option', { value: '0', text: 'Tidak ada' })])
            : el('input', { class: 'form-control form-control-sm', type: 'number', min: '0', step: '0.0001', inputmode: 'decimal', 'data-baris': b.id, 'aria-label': 'Jumlah ' + b.kode });
          input.value = String(nilai);
          badan.appendChild(el('div', { class: 'row g-2 align-items-center mb-2' }, [
            el('div', { class: 'col-7 small' }, [el('div', { class: 'fw-semibold', text: b.kode + ' · ' + b.nama }), b.lacak ? el('div', { class: 'text-muted', text: b.lacak }) : null]),
            el('div', { class: 'col-5 d-flex align-items-center gap-1' }, [input, el('span', { class: 'small text-muted', text: b.unit ? '' : (b.satuan || '') })])
          ]));
        });
        var label = terkirim ? ({ antre: 'Tersimpan di perangkat', posted: 'Terkirim', review: 'Perlu ditinjau', rejected: 'Ditolak' }[terkirim.status] || terkirim.status) : '';
        badan.appendChild(el('div', { class: 'd-flex flex-wrap gap-2 align-items-center' }, [
          el('button', { class: 'btn btn-sm btn-primary', type: 'button', text: 'Simpan di perangkat', onclick: function () { simpan(t, badan, false); } }),
          el('button', { class: 'btn btn-sm btn-outline-success', type: 'button', text: 'Simpan & selesai', onclick: function () { simpan(t, badan, true); } }),
          label ? el('span', { class: 'badge text-bg-' + ({ posted: 'success', review: 'warning', rejected: 'danger' }[terkirim.status] || 'secondary'), text: label }) : null,
          terkirim && terkirim.message ? el('span', { class: 'small text-danger', text: terkirim.message }) : null
        ]));
        daftar.appendChild(el('div', { class: 'card mb-3', 'data-tugas': t.id }, [
          el('div', { class: 'card-header', text: t.sesi + ' · Bin ' + t.bin + (t.putaran === 2 ? ' · hitung ulang' : '') }), badan
        ]));
      });
      var waktu = d.diunduh ? new Date(d.diunduh).toLocaleString('id-ID') : '-';
      akar.querySelector('[data-diunduh]').textContent = waktu;
    }

    function simpan(t, badan, selesai) {
      var qtys = {};
      badan.querySelectorAll('[data-baris]').forEach(function (i) { qtys[i.dataset.baris] = i.value === '' ? null : i.value; });
      if (selesai && Object.keys(qtys).some(function (k) { return qtys[k] === null; })) {
        pesan('Isi semua baris dulu sebelum menandai selesai.', 'warning');
        return;
      }
      var d = baca();
      // Satu bin = satu transaksi terbaru yang belum terkirim; yang sudah dikirim tidak diubah.
      d.antrean = d.antrean.filter(function (a) { return !(a.payload.assignment_id === t.id && a.status === 'antre'); });
      d.antrean.push({ uuid: uuid(), kind: 'count_record', recorded_at: new Date().toISOString(), status: 'antre', payload: { assignment_id: t.id, qtys: qtys, finish: selesai } });
      tulis(d);
      pesan(navigator.onLine ? 'Tersimpan. Mengirim…' : 'Tersimpan di perangkat. Dikirim otomatis saat ada sinyal.', 'info');
      render();
      kirim();
    }

    var sedangKirim = false;
    function kirim() {
      var d = baca();
      var antre = d.antrean.filter(function (a) { return a.status === 'antre'; });
      if (!antre.length || !navigator.onLine || sedangKirim) return Promise.resolve();
      sedangKirim = true;
      return fetch(akar.dataset.sync, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ items: antre.map(function (a) { return { uuid: a.uuid, kind: a.kind, recorded_at: a.recorded_at, payload: a.payload }; }) })
      }).then(function (r) {
        if (r.status === 403) { pesan('Server menahan antrean (langganan/akses). Data tetap di perangkat.', 'warning'); return null; }
        if (r.status === 419 || r.status === 401) { pesan('Sesi habis. Muat ulang halaman saat online, lalu masuk lagi; data tetap di perangkat.', 'warning'); return null; }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(function (j) {
        if (!j) return;
        var d2 = baca();
        j.hasil.forEach(function (h) {
          var a = d2.antrean.find(function (x) { return x.uuid === h.uuid; });
          if (a) { a.status = h.status; a.message = h.message; }
        });
        // Bin yang sudah diposting & selesai tidak perlu ditampilkan lagi.
        var selesai = d2.antrean.filter(function (a) { return a.status === 'posted' && a.payload.finish; }).map(function (a) { return a.payload.assignment_id; });
        d2.tugas = d2.tugas.filter(function (t) { return selesai.indexOf(t.id) < 0; });
        tulis(d2);
        var tinjau = j.hasil.filter(function (h) { return h.status === 'review' || h.status === 'rejected'; }).length;
        pesan(tinjau ? tinjau + ' isian perlu ditinjau Kepala Gudang.' : 'Semua isian terkirim.', tinjau ? 'warning' : 'success');
        render();
      }).catch(function () {
        pesan('Belum terkirim (tidak ada sinyal). Data tetap di perangkat.', 'secondary');
      }).finally(function () { sedangKirim = false; });
    }

    function unduh() {
      if (!navigator.onLine) { pesan('Mode offline: memakai tugas yang tersimpan di perangkat.', 'secondary'); render(); return; }
      fetch(akar.dataset.data, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (j) {
          var d = baca();
          d.tugas = j.tugas;
          d.diunduh = j.diunduh;
          tulis(d);
          pesan('Tugas diperbarui. Halaman ini tetap bisa dipakai tanpa sinyal.', 'success');
          render();
          kirim();
        })
        .catch(function () { pesan('Server tidak terjangkau: memakai tugas yang tersimpan di perangkat.', 'secondary'); render(); });
    }

    akar.querySelector('[data-unduh]').addEventListener('click', unduh);
    akar.querySelector('[data-kirim]').addEventListener('click', kirim);
    window.addEventListener('online', function () { pesan('Sinyal kembali. Mengirim…', 'info'); kirim(); });
    window.addEventListener('offline', function () { pesan('Offline: isian disimpan di perangkat.', 'secondary'); });
    render();
    unduh();
  }

  function pasangSemua() { document.querySelectorAll('[data-offline-count]').forEach(pasang); }
  document.addEventListener('DOMContentLoaded', pasangSemua);
  document.addEventListener('livewire:navigated', pasangSemua);
})();
