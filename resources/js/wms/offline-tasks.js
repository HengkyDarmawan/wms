/* ============================================================
   Tugas gudang offline (32-pwa-offline, AD-16, A-439).
   JavaScript biasa karena halaman ini harus jalan tanpa server. Tugas dan
   antrean disimpan di IndexedDB (A-438: foto tidak muat di localStorage).
   Klien hanya merekam niat; server memvalidasi lewat Action yang sama.
   ============================================================ */
(function () {
  'use strict';

  var LABEL = { antre: 'Tersimpan di perangkat', posted: 'Terkirim', review: 'Perlu ditinjau', rejected: 'Ditolak' };
  var WARNA = { posted: 'success', review: 'warning', rejected: 'danger' };

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

  function buka() {
    return new Promise(function (ok, gagal) {
      var r = indexedDB.open('wms-offline', 1);
      r.onupgradeneeded = function () { r.result.createObjectStore('kv'); };
      r.onsuccess = function () { ok(r.result); };
      r.onerror = function () { gagal(r.error); };
    });
  }

  function idbBaca(kunci) {
    return buka().then(function (db) {
      return new Promise(function (ok, gagal) {
        var r = db.transaction('kv').objectStore('kv').get(kunci);
        r.onsuccess = function () { ok(r.result); };
        r.onerror = function () { gagal(r.error); };
      });
    });
  }

  function idbTulis(kunci, nilai) {
    return buka().then(function (db) {
      return new Promise(function (ok, gagal) {
        var t = db.transaction('kv', 'readwrite');
        t.objectStore('kv').put(nilai, kunci);
        t.oncomplete = function () { ok(); };
        t.onerror = function () { gagal(t.error); };
      });
    });
  }

  /* Foto diperkecil di perangkat agar antrean dan kiriman tetap ringan. */
  function kecilkan(berkas) {
    return new Promise(function (ok, gagal) {
      var url = URL.createObjectURL(berkas);
      var img = new Image();
      img.onload = function () {
        var skala = Math.min(1, 1280 / Math.max(img.width, img.height));
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * skala);
        c.height = Math.round(img.height * skala);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        ok(c.toDataURL('image/jpeg', 0.7));
      };
      img.onerror = function () { URL.revokeObjectURL(url); gagal(new Error('foto')); };
      img.src = url;
    });
  }

  function pasang(akar) {
    if (akar.__offlineTasks) return;
    akar.__offlineTasks = true;

    var KUNCI = 'tasks:' + location.host + ':' + akar.dataset.user;
    var d = { paket: null, antrean: [], csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
    var status = akar.querySelector('[data-status]');
    var daftar = akar.querySelector('[data-daftar]');
    var sedangKirim = false;

    function pesan(teks, jenis) {
      status.className = 'alert py-2 small alert-' + (jenis || 'secondary');
      status.textContent = teks;
    }

    function simpanLokal() {
      return idbTulis(KUNCI, d).catch(function () { pesan('Penyimpanan perangkat penuh atau diblokir.', 'danger'); });
    }

    function entri(ref) { return d.antrean.filter(function (a) { return a.ref === ref; }).pop(); }

    function lencana(ref) {
      var a = entri(ref);
      if (!a) return null;
      return el('span', {}, [
        el('span', { class: 'badge text-bg-' + (WARNA[a.status] || 'secondary'), text: LABEL[a.status] || a.status }),
        a.message ? el('span', { class: 'small text-danger ms-1', text: a.message }) : null
      ]);
    }

    function antrekan(ref, kind, payload, berkas) {
      // Isian terbaru menggantikan yang belum terkirim; yang sudah dikirim tidak diubah.
      d.antrean = d.antrean.filter(function (a) { return !(a.ref === ref && a.status === 'antre'); });
      d.antrean.push({ uuid: uuid(), ref: ref, kind: kind, recorded_at: new Date().toISOString(), status: 'antre', payload: payload, berkas: berkas || null });
      simpanLokal().then(function () {
        pesan(navigator.onLine ? 'Tersimpan. Mengirim…' : 'Tersimpan di perangkat. Dikirim otomatis saat ada sinyal.', 'info');
        render();
        kirim();
      });
    }

    function judulBarang(b) {
      return el('div', { class: 'small' }, [
        el('div', { class: 'fw-semibold', text: b.kode + ' · ' + b.nama }),
        el('div', { class: 'text-muted', text: [b.lacak, b.qty + ' ' + (b.satuan || ''), b.bin ? 'Bin ' + b.bin : null].filter(Boolean).join(' · ') })
      ]);
    }

    function kartu(judul, badan) {
      return el('div', { class: 'card mb-3' }, [el('div', { class: 'card-header', text: judul }), badan]);
    }

    function kartuPutaway(t) {
      var badan = el('div', { class: 'card-body' });
      t.baris.forEach(function (b) {
        var ref = 'pw-' + b.id;
        var bin = el('input', { class: 'form-control form-control-sm', 'aria-label': 'Bin tujuan ' + b.kode, placeholder: 'Pindai / ketik bin' });
        bin.value = b.bin || '';
        var alasan = el('input', { class: 'form-control form-control-sm', 'aria-label': 'Alasan ganti bin ' + b.kode, placeholder: 'Alasan bila ganti bin' });
        badan.appendChild(el('div', { class: 'border-bottom pb-2 mb-2' }, [
          judulBarang(b),
          el('div', { class: 'row g-2 mt-1' }, [el('div', { class: 'col-sm-5' }, [bin]), el('div', { class: 'col-sm-7' }, [alasan])]),
          el('div', { class: 'd-flex flex-wrap gap-2 align-items-center mt-2' }, [
            el('button', { class: 'btn btn-sm btn-primary', type: 'button', text: 'Taruh di bin', onclick: function () {
              antrekan(ref, 'putaway_place', { task_id: t.id, line_id: b.id, bin_code: bin.value.trim(), override_reason: alasan.value.trim() || null });
            } }),
            lencana(ref)
          ])
        ]));
      });
      return kartu('Put-away ' + t.nomor, badan);
    }

    function kartuPicking(t) {
      var ref = 'pick-' + t.id;
      var badan = el('div', { class: 'card-body' });
      var isian = t.baris.map(function (b) {
        var qty = el('input', { class: 'form-control form-control-sm', type: 'number', min: '0', max: String(b.qty), step: '0.0001', inputmode: 'decimal', 'aria-label': 'Jumlah diambil ' + b.kode });
        qty.value = String(b.qty);
        var alasan = el('select', { class: 'form-select form-select-sm', 'aria-label': 'Alasan kurang ' + b.kode }, [el('option', { value: '', text: 'Alasan bila kurang' })]);
        Object.keys(d.paket.alasan_kurang || {}).forEach(function (k) { alasan.appendChild(el('option', { value: k, text: d.paket.alasan_kurang[k] })); });
        badan.appendChild(el('div', { class: 'border-bottom pb-2 mb-2' }, [
          judulBarang(b),
          el('div', { class: 'row g-2 mt-1' }, [el('div', { class: 'col-sm-5' }, [qty]), el('div', { class: 'col-sm-7' }, [alasan])])
        ]));
        return { b: b, qty: qty, alasan: alasan };
      });
      badan.appendChild(el('div', { class: 'd-flex flex-wrap gap-2 align-items-center' }, [
        el('button', { class: 'btn btn-sm btn-primary', type: 'button', text: 'Selesaikan picking', onclick: function () {
          antrekan(ref, 'pick_record', { task_id: t.id, finish: true, lines: isian.map(function (i) { return { line_id: i.b.id, qty_picked: i.qty.value === '' ? 0 : i.qty.value, short_reason: i.alasan.value || null }; }) });
        } }),
        lencana(ref)
      ]));
      return kartu('Picking ' + t.nomor, badan);
    }

    function kartuTombol(jenis, t, ref, kind, payload, teks) {
      var badan = el('div', { class: 'card-body' });
      t.baris.forEach(function (b) { badan.appendChild(el('div', { class: 'border-bottom pb-2 mb-2' }, [judulBarang(b)])); });
      badan.appendChild(el('div', { class: 'd-flex flex-wrap gap-2 align-items-center' }, [
        el('button', { class: 'btn btn-sm btn-primary', type: 'button', text: teks, onclick: function () { antrekan(ref, kind, payload); } }),
        lencana(ref)
      ]));
      return kartu(jenis + ' ' + t.nomor, badan);
    }

    function inputFoto(label) {
      return el('label', { class: 'form-label small d-block mb-2', text: label }, [
        el('input', { class: 'form-control form-control-sm mt-1', type: 'file', accept: 'image/*', capture: 'environment' })
      ]);
    }

    function angka(nilai, label) {
      var i = el('input', { class: 'form-control form-control-sm', type: 'number', min: '0', step: '0.0001', inputmode: 'decimal', 'aria-label': label });
      i.value = String(nilai);
      return i;
    }

    function kartuBukti(t) {
      var ref = 'pod-' + t.id;
      var badan = el('div', { class: 'card-body' });
      var isian = t.baris.map(function (b) {
        var baik = angka(b.qty, 'Baik ' + b.kode), rusak = angka(0, 'Rusak ' + b.kode), kurang = angka(0, 'Kurang ' + b.kode);
        var foto = inputFoto('Foto kerusakan (wajib bila ada yang rusak)');
        badan.appendChild(el('div', { class: 'border-bottom pb-2 mb-2' }, [
          judulBarang(b),
          el('div', { class: 'row g-2 my-1 small' }, [
            el('div', { class: 'col-4' }, [el('span', { text: 'Baik' }), baik]),
            el('div', { class: 'col-4' }, [el('span', { text: 'Rusak' }), rusak]),
            el('div', { class: 'col-4' }, [el('span', { text: 'Kurang' }), kurang])
          ]),
          foto
        ]));
        return { b: b, baik: baik, rusak: rusak, kurang: kurang, foto: foto.querySelector('input') };
      });
      var nama = el('input', { class: 'form-control form-control-sm mb-2', 'aria-label': 'Nama penerima', placeholder: 'Nama penerima' });
      nama.value = t.nama_bawaan || '';
      var catatan = el('input', { class: 'form-control form-control-sm mb-2', 'aria-label': 'Catatan', placeholder: 'Catatan (opsional)' });
      var foto = inputFoto('Foto serah terima (opsional)');
      var fotoSj = t.wajib_sj ? inputFoto('Foto SJ bertanda tangan & cap (wajib)') : null;
      [nama, catatan, foto, fotoSj].forEach(function (x) { if (x) badan.appendChild(x); });

      function kirimBukti() {
        if (!nama.value.trim()) { pesan('Nama penerima wajib diisi.', 'warning'); return; }
        var sj = fotoSj && fotoSj.querySelector('input').files[0];
        if (t.wajib_sj && !sj) { pesan('Foto SJ bertanda tangan & cap wajib diunggah.', 'warning'); return; }
        var kurangFoto = isian.some(function (i) { return Number(i.rusak.value) > 0 && !i.foto.files[0]; });
        if (kurangFoto) { pesan('Foto wajib dilampirkan untuk baris yang rusak.', 'warning'); return; }
        var berkas = { lines: {} };
        var tugas = [];
        function ambil(f, simpan) { if (f) tugas.push(kecilkan(f).then(simpan)); }
        ambil(foto.querySelector('input').files[0], function (u) { berkas.photo = u; });
        ambil(sj, function (u) { berkas.signed_document = u; });
        isian.forEach(function (i) { ambil(i.foto.files[0], function (u) { berkas.lines[i.b.id] = u; }); });
        Promise.all(tugas).then(function () {
          antrekan(ref, 'delivery_confirm', {
            shipment_id: t.id, received_by_name: nama.value.trim(), notes: catatan.value.trim() || null,
            lines: isian.map(function (i) { return { shipment_line_id: i.b.id, qty_good: i.baik.value || 0, qty_damaged: i.rusak.value || 0, qty_missing: i.kurang.value || 0 }; })
          }, berkas);
        }).catch(function () { pesan('Foto tidak terbaca. Pilih foto lain.', 'danger'); });
      }

      badan.appendChild(el('div', { class: 'd-flex flex-wrap gap-2 align-items-center' }, [
        el('button', { class: 'btn btn-sm btn-primary', type: 'button', text: 'Simpan bukti terima', onclick: kirimBukti }),
        lencana(ref)
      ]));
      return kartu('Bukti terima ' + t.nomor, badan);
    }

    function render() {
      akar.querySelector('[data-jumlah-antrean]').textContent = String(d.antrean.filter(function (a) { return a.status === 'antre'; }).length);
      akar.querySelector('[data-diunduh]').textContent = d.paket && d.paket.diunduh ? new Date(d.paket.diunduh).toLocaleString('id-ID') : '-';
      daftar.innerHTML = '';
      var p = d.paket;
      if (!p) {
        daftar.appendChild(el('p', { class: 'text-muted small', text: 'Belum ada tugas tersimpan di perangkat. Buka halaman ini sekali saat ada sinyal.' }));
        return;
      }
      var kartuSemua = [].concat(
        p.penerimaan.map(function (t) { return kartuTombol('Penerimaan', t, 'grn-' + t.id, 'receipt_receive', { receipt_id: t.id }, 'Terima barang'); }),
        p.putaway.map(kartuPutaway),
        p.picking.map(kartuPicking),
        p.muat.map(function (t) { return kartuTombol('Konfirmasi muat', t, 'sj-' + t.id, 'shipment_ship', { shipment_id: t.id }, 'Berangkatkan'); }),
        p.bukti.map(kartuBukti)
      );
      if (!kartuSemua.length) daftar.appendChild(el('p', { class: 'text-muted small', text: 'Tidak ada tugas gudang yang menunggu.' }));
      kartuSemua.forEach(function (k) { daftar.appendChild(k); });
    }

    function kirim() {
      var antre = d.antrean.filter(function (a) { return a.status === 'antre'; });
      if (!antre.length || !navigator.onLine || sedangKirim) return Promise.resolve();
      // Transaksi berfoto dikirim sendiri agar kiriman tidak melewati batas ukuran server.
      var paket = antre[0].berkas ? [antre[0]] : antre.slice(0, 50).filter(function (a) { return !a.berkas; });
      sedangKirim = true;
      var lanjut = false;
      return fetch(akar.dataset.sync, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': d.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ items: paket.map(function (a) { return { uuid: a.uuid, kind: a.kind, recorded_at: a.recorded_at, payload: a.payload, berkas: a.berkas }; }) })
      }).then(function (r) {
        if (r.status === 403) { pesan('Server menahan antrean (langganan/akses). Data tetap di perangkat.', 'warning'); return null; }
        if (r.status === 419 || r.status === 401) { pesan('Sesi habis. Masuk lagi saat online; data tetap di perangkat.', 'warning'); return null; }
        if (r.status === 429) { pesan('Terlalu banyak kiriman. Dicoba lagi sebentar; data tetap di perangkat.', 'warning'); return null; }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(function (j) {
        if (!j) return;
        j.hasil.forEach(function (h) {
          var a = d.antrean.find(function (x) { return x.uuid === h.uuid; });
          if (a) { a.status = h.status; a.message = h.message; a.berkas = null; }
        });
        lanjut = d.antrean.some(function (a) { return a.status === 'antre'; });
        var tinjau = j.hasil.filter(function (h) { return h.status === 'review' || h.status === 'rejected'; }).length;
        pesan(tinjau ? tinjau + ' isian perlu ditinjau Kepala Gudang.' : 'Semua isian terkirim.', tinjau ? 'warning' : 'success');
        d.antrean = d.antrean.filter(function (a) { return a.status !== 'posted'; });
        return simpanLokal().then(render);
      }).catch(function () {
        pesan('Belum terkirim (tidak ada sinyal). Data tetap di perangkat.', 'secondary');
      }).finally(function () {
        sedangKirim = false;
        if (lanjut) kirim(); else if (navigator.onLine && !d.antrean.some(function (a) { return a.status === 'antre'; })) unduh(true);
      });
    }

    function unduh(diam) {
      if (!navigator.onLine) { if (!diam) pesan('Mode offline: memakai tugas yang tersimpan di perangkat.', 'secondary'); render(); return; }
      fetch(akar.dataset.data, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function (j) {
          d.paket = j;
          d.csrf = j.csrf || d.csrf;
          return simpanLokal();
        })
        .then(function () {
          if (!diam) pesan('Tugas diperbarui. Halaman ini tetap bisa dipakai tanpa sinyal.', 'success');
          render();
          kirim();
        })
        .catch(function () { if (!diam) pesan('Server tidak terjangkau: memakai tugas yang tersimpan di perangkat.', 'secondary'); render(); });
    }

    akar.querySelector('[data-unduh]').addEventListener('click', function () { unduh(false); });
    akar.querySelector('[data-kirim]').addEventListener('click', kirim);
    window.addEventListener('online', function () { pesan('Sinyal kembali. Mengirim…', 'info'); kirim(); });
    window.addEventListener('offline', function () { pesan('Offline: isian disimpan di perangkat.', 'secondary'); });

    idbBaca(KUNCI).catch(function () { return null; }).then(function (lama) {
      if (lama) { d.paket = lama.paket; d.antrean = lama.antrean || []; }
      render();
      unduh(false);
    });
  }

  function pasangSemua() { document.querySelectorAll('[data-offline-tasks]').forEach(pasang); }
  document.addEventListener('DOMContentLoaded', pasangSemua);
  document.addEventListener('livewire:navigated', pasangSemua);
})();
