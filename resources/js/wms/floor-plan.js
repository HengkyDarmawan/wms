/*
   Komponen Alpine `denahGedung` — Denah ringan (K-H, A-353).

   - Data denah (zona → rak → tingkat → petak, objek denah, gedung) dimuat
     SEKALI dari halaman. SVG digambar di browser (tanpa request).
   - Mode Lihat: klik rak → satu request kecil `$wire.isiRak(id)` (isi rak saja);
     cari & ganti warna dikerjakan di browser.
   - Mode Atur: geser/ubah ukuran/putar/tambah/ubah/nonaktifkan dikerjakan di
     browser, bisa DIURUNGKAN, lalu satu tombol "Simpan perubahan" mengirim
     semua operasi sekali (`$wire.simpanPerubahan(ops)`); server menerapkannya
     dalam satu transaksi dan mengembalikan galat per objek.
   - Tanpa pembaruan otomatis berkala (tanpa polling/websocket).
   Satuan: meter; SVG = meter × skala. Tanpa pustaka tambahan (D-05).
*/

const WARNA_STATUS = { kosong: '#f1f3f5', terisi: '#b2f2bb', penuh: '#ffc9c9', beku: '#a5d8ff', terpakai: '#d0bfff' };
const warnaUmur = (u) => (u === null || u === undefined ? '#f1f3f5' : u < 30 ? '#b2f2bb' : u < 90 ? '#ffec99' : u < 180 ? '#ffd8a8' : '#ffc9c9');
const GRID = 0.5;
const HALUS = 0.1;
const SEL_LEBAR = 0.8;
const SEL_TINGGI = 0.5;
const BINGKAI = 0.1;
const JARAK = 0.5;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const bulat = (n) => Math.round(n * 100) / 100;
const snap = (n, g) => bulat(Math.round(n / g) * g);
const angka = (n) => (n === null || n === undefined || n === '' ? '' : String(bulat(Number(n))).replace('.', ','));
const potong = (s, n) => (String(s ?? '').length > n ? String(s).slice(0, n - 1) + '…' : String(s ?? ''));

function denahGedung(opts = {}) {
  // Data dikirim di <script type="application/json" id="…">; argumen berupa id-nya.
  if (typeof opts === 'string') opts = JSON.parse(document.getElementById(opts)?.textContent || '{}');
  return {
    d: opts.denah,
    meta: opts.meta || {},
    skala: opts.meta?.skala || 80,
    zoom: 1,
    otomatis: true,
    warna: 'status',
    cari: '',
    edit: false,
    pilih: null,
    rak: null,
    binId: null,
    memuat: false,
    ops: [],
    riwayat: [],
    galat: [],
    menyimpan: false,
    lanjutan: false,
    tab: 'isi',
    tmp: 0,
    layarKecil: false,
    tampilDaftar: false,
    drag: null,
    f: {
      zona: { code: '', name: '' },
      rak: { zona: '', code: '', name: '', levels: '1', bins: '4', capacity_qty: '' },
      area: { zona: '', code: '', name: '', capacity_qty: '1', seluruh_zona: false },
      level: { code: '', bins: '0' },
      binBaru: {},
      gedung: { length_m: '', width_m: '' },
      alasan: '',
      edit: {},
      tandai: { utama: '', bins: [], alasan: '' },
    },

    init() {
      this.isiFormGedung();
      const mq = window.matchMedia('(max-width: 767.98px)');
      this.layarKecil = mq.matches;
      mq.addEventListener?.('change', (e) => { this.layarKecil = e.matches; this.$nextTick(() => this.gambar()); });
      // K-J + batas bin: layar kecil atau gudang sangat besar → versi daftar.
      this.tampilDaftar = this.layarKecil || (this.d.jumlah_bin || 0) > (this.meta.maksBinGambar || 2000);
      this.$nextTick(() => { this.gambar(); this.pas(true); });
      if (window.ResizeObserver && this.$refs.wrap) {
        new ResizeObserver(() => { if (this.otomatis) this.pas(true); }).observe(this.$refs.wrap);
      }
      window.addEventListener('beforeunload', (e) => {
        if (this.ops.length) { e.preventDefault(); e.returnValue = ''; }
      });
    },

    // ------------------------------------------------------------ tampilan

    get q() { return this.cari.trim().toLowerCase(); },

    semuaRak() {
      return this.d.zones.flatMap((z) => z.racks.map((r) => ({ z, r })));
    },

    cocokBin(b) { return this.q !== '' && (b.code.toLowerCase() + ' ' + (b.cari || '')).includes(this.q); },

    cocokRak(r) {
      if (this.q === '') return false;
      return (r.code + ' ' + (r.name || '')).toLowerCase().includes(this.q) || r.levels.some((l) => l.bins.some((b) => this.cocokBin(b)));
    },

    hasilCari() {
      if (this.q === '') return [];
      const out = [];
      for (const { z, r } of this.semuaRak()) {
        for (const l of r.levels) for (const b of l.bins) if (this.cocokBin(b)) out.push({ rak: r.id, pendek: b.pendek, code: b.code, bin: b.id, zona: z.code });
        if (out.length >= 50) break;
      }
      return out;
    },

    warnaBin(b) {
      if (b.nonaktif) return '#dee2e6';
      return this.warna === 'umur' ? warnaUmur(b.umur) : WARNA_STATUS[b.status] || WARNA_STATUS.kosong;
    },

    angka(n) { return angka(n); },

    binLokal(id) {
      for (const z of this.d.zones) for (const r of z.racks) for (const l of r.levels) { const b = l.bins.find((x) => String(x.id) === String(id)); if (b) return b; }
      return null;
    },

    /** Warna petak di panel rak: status/umur dari data denah (isi rak tidak membawanya). */
    warnaPetak(b) { return this.warnaBin(this.binLokal(b.id) || { ...b, status: b.total > 0 ? 'terisi' : 'kosong' }); },

    warnaRak(r) {
      return this.warna === 'umur' ? warnaUmur(r.umur) : WARNA_STATUS[r.status] || WARNA_STATUS.kosong;
    },

    ukuranRak(r) {
      const kolom = Math.max(1, ...r.levels.map((l) => l.bins.length));
      r.kolom = kolom;
      r.w = r.is_area ? r.len : Math.max(r.len, kolom * SEL_LEBAR + 2 * BINGKAI);
      r.h = r.is_area ? r.wid : Math.max(r.wid, Math.max(1, r.levels.length) * SEL_TINGGI + 2 * BINGKAI);
    },

    ukuranZona(z) {
      const kanan = Math.max(0, ...z.racks.map((r) => r.x + r.w)) + JARAK;
      const bawah = Math.max(0, ...z.racks.map((r) => r.y + r.h)) + JARAK;
      z.w = Math.max(z.length_m ?? 6, kanan);
      z.h = Math.max(z.width_m ?? 2.5, bawah);
    },

    kanvas() {
      const g = this.d.gedung;
      const kanan = Math.max(g?.p || 0, ...this.d.zones.map((z) => z.x + z.w), ...this.d.objects.map((o) => o.x + o.p));
      const bawah = Math.max(g?.l || 0, ...this.d.zones.map((z) => z.y + z.h), ...this.d.objects.map((o) => o.y + o.l));
      return { w: Math.max(6, kanan + JARAK), h: Math.max(3, bawah + JARAK) };
    },

    tumpukan() {
      const kotak = [];
      for (const z of this.d.zones) for (const r of z.racks) kotak.push({ id: 'rak:' + r.id, nama: 'Rak ' + z.code + '-' + r.code, x: z.x + r.x, y: z.y + r.y, p: r.len, l: r.wid });
      const padat = this.d.objects.filter((o) => o.solid).map((o) => ({ id: 'obj:' + o.id, nama: o.name, x: o.x, y: o.y, p: o.p, l: o.l }));
      const zona = this.d.zones.map((z) => ({ id: 'zona:' + z.id, nama: 'Zona ' + z.code, x: z.x, y: z.y, p: z.length_m ?? z.w, l: z.width_m ?? z.h }));
      const tumpuk = (a, b) => a.x + 0.001 < b.x + b.p && b.x + 0.001 < a.x + a.p && a.y + 0.001 < b.y + b.l && b.y + 0.001 < a.y + a.l;
      const pesan = [];
      const ids = new Set();
      const catat = (a, b) => { pesan.push(a.nama + ' ↔ ' + b.nama); ids.add(a.id); ids.add(b.id); };
      kotak.forEach((a, i) => {
        kotak.slice(i + 1).forEach((b) => tumpuk(a, b) && catat(a, b));
        padat.forEach((o) => tumpuk(a, o) && catat(a, o));
      });
      zona.forEach((a, i) => zona.slice(i + 1).forEach((b) => tumpuk(a, b) && catat(a, b)));
      const g = this.d.gedung;
      if (g) {
        [...zona, ...this.d.objects.map((o) => ({ id: 'obj:' + o.id, nama: o.name, x: o.x, y: o.y, p: o.p, l: o.l }))].forEach((a) => {
          if (a.x + a.p > g.p + 0.001 || a.y + a.l > g.l + 0.001) { pesan.push(a.nama + ' keluar dari garis gedung'); ids.add(a.id); }
        });
      }
      return { pesan, ids };
    },

    galatObjek() {
      const m = {};
      for (const g of this.galat) m[g.objek] = g.pesan;
      return m;
    },

    // ------------------------------------------------------------ gambar SVG

    gambar() {
      const svg = this.$refs.svg;
      if (!svg) return;
      const S = this.skala;
      const K = this.kanvas();
      const F = bulat(Math.max(1, Math.min(3, K.w / 15)));
      const M = 40 * F;
      const tump = this.tumpukan().ids;
      const galat = this.galatObjek();
      const pil = this.pilih ? this.pilih.jenis + ':' + this.pilih.id : '';
      const o = [];

      o.push(`<defs><pattern id="grid-d" width="${S / 2}" height="${S / 2}" patternUnits="userSpaceOnUse"><path d="M ${S / 2} 0 L 0 0 0 ${S / 2}" fill="none" stroke="#eef0f3" stroke-width="1"/></pattern></defs>`);
      o.push(`<rect x="0" y="0" width="${K.w * S}" height="${K.h * S}" fill="url(#grid-d)"/>`);
      const g = this.d.gedung;
      if (g) {
        o.push(`<rect x="0" y="0" width="${g.p * S}" height="${g.l * S}" fill="none" stroke="#212529" stroke-width="6" data-gedung/>`);
        o.push(`<text x="0" y="${-10 * F}" font-size="${20 * F}" font-weight="600" fill="currentColor">${esc(this.meta.teks?.gedung || 'Gedung')} ${esc(this.meta.gudang?.code)} — ${angka(g.p)} × ${angka(g.l)} m</text>`);
        for (let m = 0; m <= g.p; m += 5) o.push(`<text x="${m * S}" y="${g.l * S + 22 * F}" font-size="${14 * F}" text-anchor="middle" fill="#868e96">${m}</text>`);
      }

      const objek = (ob) => {
        const sel = pil === 'obj:' + ob.id;
        const merah = tump.has('obj:' + ob.id) || galat['obj:' + ob.id];
        const garis = ['forklift_lane', 'open_area'].includes(ob.type) || merah ? ` stroke-dasharray="${merah ? '8 4' : '10 6'}"` : '';
        const ikon = { door: '🚪', dock: '🚚', forklift_lane: '⇄', office: '🏢' }[ob.type] || '';
        let s = `<g data-jenis="obj" data-id="${ob.id}" data-objek="${esc(ob.type)}" transform="translate(${ob.x * S},${ob.y * S})" style="cursor:${this.edit ? 'move' : 'default'}">`;
        s += `<rect data-badan x="0" y="0" width="${ob.p * S}" height="${ob.l * S}" rx="${ob.type === 'pillar' ? 2 : 5}" fill="${ob.fill}" stroke="${merah ? '#e03131' : sel ? '#6366f1' : ob.stroke}" stroke-width="${sel || merah ? 4 : 1.5}"${garis}><title>${esc(ob.label)} · ${esc(ob.name)}</title></rect>`;
        s += ob.type === 'pillar'
          ? `<text x="${ob.p * S + 4}" y="-3" font-size="${12 * F}" fill="#495057" pointer-events="none">${esc(ob.name)}</text>`
          : `<text x="8" y="${Math.min(ob.l * S - 6, 20 * F)}" font-size="${14 * F}" fill="#495057" pointer-events="none">${ikon} ${esc(potong(ob.name, 24))}</text>`;
        if (this.edit && sel) s += `<rect data-handle x="${ob.p * S - 7}" y="${ob.l * S - 7}" width="14" height="14" fill="#6366f1" style="cursor:nwse-resize"/>`;
        return s + '</g>';
      };

      this.d.objects.filter((x) => !x.solid).forEach((ob) => o.push(objek(ob)));

      for (const z of this.d.zones) {
        const selZ = pil === 'zona:' + z.id;
        const merahZ = tump.has('zona:' + z.id) || galat['zona:' + z.id];
        o.push(`<g data-jenis="zona" data-id="${z.id}" data-zona="${esc(z.code)}" transform="translate(${z.x * S},${z.y * S})" style="cursor:${this.edit ? 'move' : 'default'}">`);
        o.push(`<rect data-badan x="0" y="0" width="${z.w * S}" height="${z.h * S}" rx="8" fill="#eef2ff" fill-opacity="0.55" stroke="${merahZ ? '#e03131' : '#6366f1'}" stroke-width="${selZ ? 5 : 2}"${merahZ ? ' stroke-dasharray="10 5"' : ''}/>`);
        o.push(`<text x="10" y="${22 * F}" font-size="${17 * F}" font-weight="600" fill="#4338ca">Zona ${esc(z.code)} — ${esc(potong(z.name, 28))}</text>`);
        o.push(`<text x="${z.w * S - 8}" y="${z.h * S - 8}" font-size="${13 * F}" text-anchor="end" fill="#6366f1">${z.length_m !== null && z.length_m !== undefined ? angka(z.length_m) + ' × ' + angka(z.width_m ?? 0) + ' m' : esc(this.meta.teks?.ukuranOtomatis || 'ukuran otomatis')}</text>`);

        for (const r of z.racks) {
          const W = r.w * S;
          const H = r.h * S;
          const sel = pil === 'rak:' + r.id || (this.rak && this.rak.id === r.id && !this.edit);
          const merah = tump.has('rak:' + r.id) || galat['rak:' + r.id];
          const cocok = this.cocokRak(r);
          const garis = merah ? '#e03131' : cocok ? '#f76707' : sel ? '#1c7ed6' : '#495057';
          const tebal = cocok || sel || merah ? 3 : 1.5;
          o.push(`<g data-jenis="rak" data-id="${r.id}" data-rak="${esc(r.code)}" transform="translate(${r.x * S},${r.y * S})" style="cursor:${this.edit ? 'move' : 'pointer'}">`);
          o.push(`<text x="0" y="${-7 * F}" font-size="${13 * F}" font-weight="600" fill="currentColor">${esc(r.code)}${r.is_area ? ' ▦' : ''}${r.name ? ` <tspan font-weight="400" fill-opacity="0.65">· ${esc(potong(r.name, 18))}</tspan>` : ''}</text>`);
          if (r.is_area) {
            o.push(`<rect data-badan x="0" y="0" width="${W}" height="${H}" rx="6" fill="${this.warnaRak(r)}" stroke="${garis}" stroke-width="${tebal}" stroke-dasharray="6 3"/>`);
            o.push(`<text x="${W / 2}" y="${H / 2 + 4}" font-size="12" text-anchor="middle" fill="#495057" pointer-events="none">${esc(this.meta.teks?.area || 'Area lantai')}</text>`);
          } else {
            o.push(`<rect data-badan x="0" y="0" width="${W}" height="${H}" rx="6" fill="#ffffff" stroke="${garis}" stroke-width="${tebal}"${merah ? ' stroke-dasharray="6 3"' : ''}/>`);
            const p = BINGKAI * S;
            const tb = (H - 2 * p) / Math.max(1, r.levels.length);
            const ls = (W - 2 * p) / Math.max(1, r.kolom || 1);
            r.levels.forEach((l, i) => {
              const yb = p + i * tb;
              o.push(`<text x="-5" y="${yb + tb / 2 + 4}" font-size="${11 * F}" font-weight="600" text-anchor="end" fill="currentColor">${esc(l.code)}</text>`);
              l.bins.forEach((b, j) => {
                const c = this.cocokBin(b);
                const bs = this.rak && this.rak.id === r.id && this.binId === b.id;
                o.push(`<rect data-bin="${b.id}" x="${p + j * ls + 2}" y="${yb + 2}" width="${Math.max(1, ls - 4)}" height="${Math.max(1, tb - 4)}" rx="3" fill="${this.warnaBin(b)}" stroke="${c ? '#f76707' : bs ? '#1c7ed6' : '#ced4da'}" stroke-width="${c || bs ? 2.5 : 1}"><title>${esc(b.pendek)} · ${esc(b.code)}${b.total > 0 ? ' · ' + angka(b.total) : ''}</title></rect>`);
                if (ls >= 28 && tb >= 16) o.push(`<text x="${p + (j + 0.5) * ls}" y="${yb + tb / 2 + 4}" font-size="${ls >= 44 ? 12 : 10}" text-anchor="middle" fill="#212529" pointer-events="none">${esc(b.short)}</text>`);
              });
            });
            if (!r.levels.length) o.push(`<text x="${W / 2}" y="${H / 2 + 4}" font-size="10" font-style="italic" text-anchor="middle" fill="#868e96">belum ada tingkat</text>`);
          }
          if (this.edit && pil === 'rak:' + r.id) o.push(`<rect data-handle x="${r.len * S - 7}" y="${r.wid * S - 7}" width="14" height="14" fill="#6366f1" style="cursor:nwse-resize"/>`);
          o.push('</g>');
        }
        if (this.edit && selZ) o.push(`<rect data-handle x="${(z.length_m ?? z.w) * S - 9}" y="${(z.width_m ?? z.h) * S - 9}" width="18" height="18" fill="#6366f1" style="cursor:nwse-resize"/>`);
        o.push('</g>');
      }

      this.d.objects.filter((x) => x.solid).forEach((ob) => o.push(objek(ob)));

      svg.setAttribute('viewBox', `${-M} ${-M} ${K.w * S + 2 * M} ${K.h * S + 2 * M}`);
      svg.innerHTML = o.join('');
      this.setUkuran();
    },

    lebarAsli() { return this.$refs.svg?.viewBox?.baseVal?.width || 1; },

    setUkuran() {
      const svg = this.$refs.svg;
      if (!svg) return;
      const vb = svg.viewBox.baseVal;
      svg.setAttribute('width', Math.max(50, vb.width * this.zoom));
      svg.setAttribute('height', Math.max(50, vb.height * this.zoom));
    },

    pas(otomatis = true) {
      const wrap = this.$refs.wrap;
      if (!wrap || !wrap.clientWidth) return;
      this.otomatis = otomatis;
      this.zoom = Math.max(0.15, Math.min(1.5, (wrap.clientWidth - 8) / this.lebarAsli()));
      this.setUkuran();
    },

    perbesar() { this.otomatis = false; this.zoom = Math.min(3, +(this.zoom * 1.25).toFixed(3)); this.setUkuran(); },
    perkecil() { this.otomatis = false; this.zoom = Math.max(0.1, +(this.zoom / 1.25).toFixed(3)); this.setUkuran(); },
    persen() { return Math.round(this.zoom * 100) + '%'; },

    // ------------------------------------------------------------ cari objek

    zona(id) { return this.d.zones.find((z) => String(z.id) === String(id)); },
    rakLokal(id) { for (const z of this.d.zones) { const r = z.racks.find((x) => String(x.id) === String(id)); if (r) return { z, r }; } return null; },
    objek(id) { return this.d.objects.find((o) => String(o.id) === String(id)); },
    idNyata(id) { return typeof id === 'number' || /^\d+$/.test(String(id)); },

    // ------------------------------------------------------------ mode lihat

    async bukaRak(id, binId = null) {
      const lokal = this.rakLokal(id);
      if (!lokal) return;
      this.pilih = this.edit ? { jenis: 'rak', id } : null;
      if (!this.idNyata(id)) {
        this.rak = { id, code: lokal.r.code, name: lokal.r.name, is_area: lokal.r.is_area, zona: lokal.z.code, zona_nama: lokal.z.name, levels: lokal.r.levels, baru: true };
        this.binId = null;
        this.gambar();
        return;
      }
      this.memuat = true;
      try {
        this.rak = await this.$wire.isiRak(Number(id));
        const semua = this.rak.levels.flatMap((l) => l.bins);
        this.binId = binId ?? (semua.find((b) => b.total > 0) || semua[0])?.id ?? null;
      } finally {
        this.memuat = false;
      }
      this.gambar();
    },

    tutup() { this.rak = null; this.pilih = null; this.binId = null; this.gambar(); },

    binTerpilih() {
      if (!this.rak) return null;
      for (const l of this.rak.levels) { const b = l.bins.find((x) => x.id === this.binId); if (b) return { ...b, level: l.code }; }
      return null;
    },

    kolomRak() { return this.rak ? Math.max(1, ...this.rak.levels.map((l) => l.bins.length)) : 1; },

    ringkasBin(b) {
      if (!b.isi || !b.isi.length) return b.total > 0 ? angka(b.total) : 'kosong';
      return potong([...new Set(b.isi.map((s) => s.item_code))].join(', '), 22);
    },

    // ------------------------------------------------------------ pointer

    mulai(e) {
      const el = e.target.closest('[data-jenis]');
      if (!el) return;
      const jenis = el.dataset.jenis;
      const id = /^\d+$/.test(el.dataset.id) ? Number(el.dataset.id) : el.dataset.id;

      if (!this.edit) {
        if (jenis === 'rak') {
          const bin = e.target.closest('[data-bin]');
          this.bukaRak(id, bin ? Number(bin.dataset.bin) : null);
        }
        return;
      }

      e.preventDefault();
      const benda = this.benda(jenis, id);
      if (!benda) return;
      this.drag = { el, jenis, id, ukuran: e.target.hasAttribute('data-handle'), sx: e.clientX, sy: e.clientY, x: benda.x, y: benda.y, p: benda.p, l: benda.l, pindah: false };
      this.$refs.svg.setPointerCapture?.(e.pointerId);
    },

    skalaLayar() { const m = this.$refs.svg.getScreenCTM(); return m ? m.a : this.zoom; },

    gerak(e) {
      const d = this.drag;
      if (!d) return;
      const dx = e.clientX - d.sx;
      const dy = e.clientY - d.sy;
      if (Math.abs(dx) + Math.abs(dy) > 3) d.pindah = true;
      if (!d.pindah) return;
      const k = this.skalaLayar();
      if (d.ukuran) {
        const badan = d.el.querySelector('[data-badan]');
        badan?.setAttribute('width', Math.max(8, d.p * this.skala + dx / k));
        badan?.setAttribute('height', Math.max(8, d.l * this.skala + dy / k));
      } else {
        d.el.setAttribute('transform', `translate(${d.x * this.skala + dx / k},${d.y * this.skala + dy / k})`);
      }
    },

    lepas(e) {
      const d = this.drag;
      if (!d) return;
      this.drag = null;
      if (!d.pindah) { this.pilihBenda(d.jenis, d.id); return; }
      const k = this.skalaLayar() * this.skala;
      const mx = (e.clientX - d.sx) / k;
      const my = (e.clientY - d.sy) / k;
      if (d.ukuran) this.ubahUkuran(d.jenis, d.id, Math.max(GRID, d.p + mx), Math.max(GRID, d.l + my));
      else this.geser(d.jenis, d.id, Math.max(0, d.x + mx), Math.max(0, d.y + my), GRID);
    },

    tombol(e) {
      if (!this.edit || !this.pilih) return;
      if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
      const l = e.shiftKey ? HALUS : GRID;
      const arah = { ArrowLeft: [-l, 0], ArrowRight: [l, 0], ArrowUp: [0, -l], ArrowDown: [0, l] }[e.key];
      if (arah) {
        e.preventDefault();
        const b = this.benda(this.pilih.jenis, this.pilih.id);
        if (b) this.geser(this.pilih.jenis, this.pilih.id, Math.max(0, b.x + arah[0]), Math.max(0, b.y + arah[1]), HALUS);
      } else if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        this.putar();
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        this.urungkan();
      }
    },

    /** Posisi & ukuran tampak atas sebuah benda (meter). */
    benda(jenis, id) {
      if (jenis === 'zona') { const z = this.zona(id); return z && { x: z.x, y: z.y, p: z.length_m ?? z.w, l: z.width_m ?? z.h }; }
      if (jenis === 'rak') { const a = this.rakLokal(id); return a && { x: a.r.x, y: a.r.y, p: a.r.len, l: a.r.wid }; }
      const o = this.objek(id);
      return o && { x: o.x, y: o.y, p: o.p, l: o.l };
    },

    pilihBenda(jenis, id) {
      this.pilih = { jenis, id };
      this.f.alasan = '';
      if (jenis === 'rak') { this.bukaRak(id); this.isiFormEdit(); return; }
      this.rak = null;
      this.isiFormEdit();
      this.gambar();
    },

    // ------------------------------------------------------------ riwayat & operasi

    catat(op) {
      this.riwayat.push({ d: JSON.stringify(this.d), n: this.ops.length });
      this.ops.push(op);
    },

    urungkan() {
      const r = this.riwayat.pop();
      if (!r) return;
      this.d = JSON.parse(r.d);
      this.ops.length = r.n;
      this.galat = [];
      if (this.pilih && !this.benda(this.pilih.jenis, this.pilih.id)) this.tutup();
      this.isiFormEdit();
      this.gambar();
    },

    batalSemua() {
      while (this.riwayat.length) this.urungkan();
    },

    jepit(x, y, p, l, batasP, batasL) {
      if (batasP !== null && batasP !== undefined) x = Math.min(x, Math.max(0, batasP - p));
      if (batasL !== null && batasL !== undefined) y = Math.min(y, Math.max(0, batasL - l));
      return [bulat(Math.max(0, x)), bulat(Math.max(0, y))];
    },

    geser(jenis, id, x, y, grid) {
      const b = this.benda(jenis, id);
      if (!b) return;
      let [nx, ny] = [snap(x, grid), snap(y, grid)];
      const g = this.d.gedung;
      if (jenis === 'rak') { const a = this.rakLokal(id); [nx, ny] = this.jepit(nx, ny, b.p, b.l, a.z.length_m, a.z.width_m); } else { [nx, ny] = this.jepit(nx, ny, b.p, b.l, g?.p, g?.l); }
      if (nx === b.x && ny === b.y) { this.gambar(); return; }
      this.catat({ op: 'geser', jenis, id, x: nx, y: ny, halus: grid === HALUS });
      if (jenis === 'zona') { const z = this.zona(id); Object.assign(z, { x: nx, y: ny, pos_x: nx, pos_y: ny, otomatis: false }); }
      else if (jenis === 'rak') { const a = this.rakLokal(id); Object.assign(a.r, { x: nx, y: ny, otomatis: false }); this.ukuranZona(a.z); }
      else Object.assign(this.objek(id), { x: nx, y: ny });
      this.isiFormEdit();
      this.gambar();
    },

    ubahUkuran(jenis, id, p, l) {
      const np = Math.max(GRID, snap(p, jenis === 'obj' ? HALUS : GRID));
      const nl = Math.max(GRID, snap(l, jenis === 'obj' ? HALUS : GRID));
      this.catat({ op: 'ukuran', jenis, id, p: np, l: nl });
      if (jenis === 'zona') { const z = this.zona(id); Object.assign(z, { length_m: np, width_m: nl }); this.ukuranZona(z); }
      else if (jenis === 'rak') { const a = this.rakLokal(id); Object.assign(a.r, { len: np, wid: nl }); this.ukuranRak(a.r); this.ukuranZona(a.z); }
      else { const o = this.objek(id); Object.assign(o, { p: np, l: nl }); }
      this.isiFormEdit();
      this.gambar();
    },

    putar() {
      if (!this.edit || !this.pilih || !['rak', 'obj'].includes(this.pilih.jenis)) return;
      const { jenis, id } = this.pilih;
      this.catat({ op: 'putar', jenis, id });
      if (jenis === 'rak') {
        const a = this.rakLokal(id);
        a.r.orientation = a.r.orientation === 'v' ? 'h' : 'v';
        [a.r.len, a.r.wid] = [a.r.wid, a.r.len];
        this.ukuranRak(a.r);
        this.ukuranZona(a.z);
      } else {
        const o = this.objek(id);
        o.rotation = (o.rotation + 90) % 360;
        [o.p, o.l] = [o.l, o.p];
      }
      this.isiFormEdit();
      this.gambar();
    },

    idBaru() { this.tmp += 1; return 'b' + this.tmp; },

    // ------------------------------------------------------------ tambah

    tambahZona() {
      const kode = this.f.zona.code.trim().toUpperCase();
      const nama = this.f.zona.name.trim();
      if (!kode || !nama) { this.pesan('Isi kode dan nama zona.', 'danger'); return; }
      if (this.d.zones.some((z) => z.code === kode)) { this.pesan('Kode zona ' + kode + ' sudah dipakai.', 'danger'); return; }
      const id = this.idBaru();
      const bawah = Math.max(0, ...this.d.zones.map((z) => z.y + z.h));
      this.catat({ op: 'zona_baru', tmp: id, data: { code: kode, name: nama } });
      this.d.zones.push({ id, code: kode, name: nama, length_m: null, width_m: null, pos_x: null, pos_y: null, x: JARAK, y: bawah + 1, w: 6, h: 2.5, otomatis: true, racks: [] });
      this.f.zona = { code: '', name: '' };
      this.f.rak.zona = id;
      this.gambar();
    },

    kodeBin(z, rak, level, n) {
      return [this.meta.gudang?.code, z.code, rak, level, 'B' + String(n).padStart(2, '0')].join('-');
    },

    susunLevel(z, kodeRak, tmpRak, kodeLevel, jumlahBin) {
      const bins = [];
      for (let n = 1; n <= jumlahBin; n++) {
        bins.push({ id: tmpRak + '#' + kodeLevel + '#' + n, code: this.kodeBin(z, kodeRak, kodeLevel, n), short: 'B' + String(n).padStart(2, '0'), pendek: kodeRak + ' · ' + kodeLevel + ' · ' + String(n).padStart(2, '0'), total: 0, status: 'kosong', umur: null, nonaktif: false, cari: '' });
      }
      return { id: tmpRak + '#' + kodeLevel, code: kodeLevel, bins };
    },

    tempatRakBaru(z, r) {
      const kanan = Math.max(0, ...z.racks.map((x) => x.x + x.w));
      r.x = z.racks.length ? bulat(kanan + JARAK) : JARAK;
      r.y = JARAK * 2;
    },

    tambahRak() {
      const z = this.zona(this.f.rak.zona);
      const kode = this.f.rak.code.trim().toUpperCase();
      const levels = parseInt(this.f.rak.levels, 10);
      const bins = parseInt(this.f.rak.bins || '0', 10);
      if (!z) { this.pesan('Pilih zona.', 'danger'); return; }
      if (!kode) { this.pesan('Isi kode rak.', 'danger'); return; }
      if (z.racks.some((r) => r.code === kode)) { this.pesan('Kode rak ' + kode + ' sudah ada di zona ' + z.code + '.', 'danger'); return; }
      if (!(levels >= 1 && levels <= this.meta.maksLevel) || !(bins >= 0 && bins <= this.meta.maksBinPerLevel)) { this.pesan('Jumlah tingkat 1–' + this.meta.maksLevel + ', bin per tingkat 0–' + this.meta.maksBinPerLevel + '.', 'danger'); return; }
      const id = this.idBaru();
      const lv = [];
      for (let i = levels; i >= 1; i--) lv.push(this.susunLevel(z, kode, id, 'L' + i, bins));
      const r = { id, code: kode, name: this.f.rak.name.trim() || null, is_area: false, len: 2, wid: 1, orientation: 'h', height_m: null, otomatis: true, status: 'kosong', umur: null, jumlah_bin: levels * bins, levels: lv };
      this.ukuranRak(r);
      this.tempatRakBaru(z, r);
      this.catat({ op: 'rak_baru', tmp: id, zona: z.id, data: { code: kode, name: this.f.rak.name.trim(), levels: String(levels), bins_per_level: String(bins), prefix: 'B', capacity_qty: this.f.rak.capacity_qty } });
      z.racks.push(r);
      this.ukuranZona(z);
      this.f.rak = { ...this.f.rak, code: '', name: '' };
      this.gambar();
    },

    tambahArea() {
      const z = this.zona(this.f.area.zona);
      const kode = this.f.area.code.trim().toUpperCase();
      if (!z || !kode) { this.pesan('Pilih zona dan isi kode area.', 'danger'); return; }
      if (z.racks.some((r) => r.code === kode)) { this.pesan('Kode ' + kode + ' sudah ada di zona ' + z.code + '.', 'danger'); return; }
      const id = this.idBaru();
      const seluruh = !!this.f.area.seluruh_zona;
      const r = { id, code: kode, name: this.f.area.name.trim() || null, is_area: true, len: seluruh ? (z.length_m ?? 2) : 2, wid: seluruh ? (z.width_m ?? 1) : 1, orientation: 'h', otomatis: !seluruh, status: 'kosong', umur: null, jumlah_bin: 1,
        levels: [{ id: id + '#L1', code: 'L1', bins: [{ id: id + '#AREA', code: [this.meta.gudang?.code, z.code, kode, 'L1', 'AREA'].join('-'), short: 'AREA', pendek: kode + ' · Area', total: 0, status: 'kosong', umur: null, nonaktif: false, cari: '' }] }] };
      this.ukuranRak(r);
      if (seluruh) { r.x = 0; r.y = 0; } else this.tempatRakBaru(z, r);
      this.catat({ op: 'area_baru', tmp: id, zona: z.id, data: { code: kode, name: this.f.area.name.trim(), capacity_qty: this.f.area.capacity_qty, seluruh_zona: seluruh ? '1' : '0' } });
      z.racks.push(r);
      this.ukuranZona(z);
      this.f.area = { ...this.f.area, code: '', name: '' };
      this.gambar();
    },

    tambahLevel() {
      const a = this.pilih?.jenis === 'rak' ? this.rakLokal(this.pilih.id) : null;
      if (!a || a.r.is_area) return;
      const ada = a.r.levels.map((l) => l.code);
      let kode = this.f.level.code.trim().toUpperCase();
      if (!kode) { let n = ada.length + 1; while (ada.includes('L' + n)) n++; kode = 'L' + n; }
      if (ada.includes(kode)) { this.pesan('Tingkat ' + kode + ' sudah ada.', 'danger'); return; }
      const bins = parseInt(this.f.level.bins || '0', 10);
      if (!(bins >= 0 && bins <= this.meta.maksBinPerLevel)) { this.pesan('Jumlah bin 0–' + this.meta.maksBinPerLevel + '.', 'danger'); return; }
      const id = this.idBaru();
      const lv = this.susunLevel(a.z, a.r.code, id, kode, bins);
      lv.id = id;
      lv.bins.forEach((b, i) => { b.id = id + '#' + (i + 1); });
      this.catat({ op: 'level_baru', tmp: id, rak: a.r.id, data: { code: kode, bins: String(bins), prefix: 'B' } });
      a.r.levels.unshift(lv);
      a.r.levels.sort((x, y) => y.code.localeCompare(x.code, undefined, { numeric: true }));
      this.ukuranRak(a.r);
      this.ukuranZona(a.z);
      this.f.level = { code: '', bins: '0' };
      this.segarkanPanelRak(a);
      this.gambar();
    },

    tambahBin(levelId) {
      const a = this.pilih?.jenis === 'rak' ? this.rakLokal(this.pilih.id) : null;
      if (!a || a.r.is_area) return;
      const l = a.r.levels.find((x) => String(x.id) === String(levelId));
      const n = parseInt(this.f.binBaru[levelId] || '1', 10);
      if (!l || !(n >= 1 && n <= this.meta.maksBinPerLevel)) { this.pesan('Jumlah bin 1–' + this.meta.maksBinPerLevel + '.', 'danger'); return; }
      const mulai = l.bins.length;
      const tambahan = this.susunLevel(a.z, a.r.code, 'x', l.code, mulai + n).bins.slice(mulai);
      tambahan.forEach((b, i) => { b.id = l.id + '+' + (mulai + i + 1); });
      // Id level: angka (tingkat lama), `bN` (tingkat baru), atau `bN#L2` (tingkat rak baru).
      this.catat({ op: 'bin_baru', level: l.id, jumlah: String(n) });
      l.bins.push(...tambahan);
      this.ukuranRak(a.r);
      this.ukuranZona(a.z);
      this.f.binBaru[levelId] = '';
      this.segarkanPanelRak(a);
      this.gambar();
    },

    tambahObjek(jenis) {
      const j = this.meta.jenisObjek?.[jenis];
      if (!j) return;
      const id = this.idBaru();
      this.catat({ op: 'objek_baru', tmp: id, jenis, data: { pos_x: 0.5, pos_y: 0.5 } });
      this.d.objects.push({ id, type: jenis, label: j.label, name: j.label, x: 0.5, y: 0.5, p: j.p, l: j.l, length_m: j.p, width_m: j.l, rotation: 0, solid: j.solid, fill: j.fill, stroke: j.stroke });
      this.pilihBenda('obj', id);
      this.pesan(j.label + ' ditambahkan — seret ke tempatnya.');
    },

    // ------------------------------------------------------------ isian panel (mode Atur)

    isiFormGedung() {
      this.f.gedung = { length_m: this.d.gedung ? String(this.d.gedung.p) : '', width_m: this.d.gedung ? String(this.d.gedung.l) : '' };
    },

    isiFormEdit() {
      if (!this.pilih) { this.f.edit = {}; return; }
      const { jenis, id } = this.pilih;
      if (jenis === 'zona') { const z = this.zona(id); this.f.edit = z ? { name: z.name, length_m: z.length_m ?? '', width_m: z.width_m ?? '', pos_x: z.otomatis ? '' : z.x, pos_y: z.otomatis ? '' : z.y } : {}; }
      // Isian rak = ukuran fisik (panjang rak, lebar rak); gambar memakai tampak atas yang tertukar bila memanjang ke bawah.
      if (jenis === 'rak') { const a = this.rakLokal(id); const v = a?.r.orientation === 'v'; this.f.edit = a ? { name: a.r.name ?? '', length_m: v ? a.r.wid : a.r.len, width_m: v ? a.r.len : a.r.wid, height_m: a.r.height_m ?? '', orientation: a.r.orientation || 'h', pos_x: a.r.otomatis ? '' : a.r.x, pos_y: a.r.otomatis ? '' : a.r.y } : {}; }
      if (jenis === 'obj') { const o = this.objek(id); this.f.edit = o ? { object_type: o.type, name: o.name, pos_x: o.x, pos_y: o.y, length_m: o.p, width_m: o.l } : {}; }
    },

    terapkanIsian() {
      if (!this.pilih) return;
      const { jenis, id } = this.pilih;
      const e = { ...this.f.edit };
      if (jenis === 'zona') {
        if (!String(e.name || '').trim()) { this.pesan('Nama zona wajib diisi.', 'danger'); return; }
        this.catat({ op: 'zona', id, data: e });
        const z = this.zona(id);
        const num = (v) => (v === '' || v === null ? null : Number(String(v).replace(',', '.')));
        Object.assign(z, { name: e.name.trim(), length_m: num(e.length_m), width_m: num(e.width_m) });
        if (num(e.pos_x) !== null && num(e.pos_y) !== null) Object.assign(z, { x: num(e.pos_x), y: num(e.pos_y), otomatis: false });
        this.ukuranZona(z);
      } else if (jenis === 'rak') {
        this.catat({ op: 'rak', id, data: e });
        const a = this.rakLokal(id);
        const num = (v) => (v === '' || v === null ? null : Number(String(v).replace(',', '.')));
        const p = num(e.length_m) ?? 2;
        const l = num(e.width_m) ?? 1;
        const v = e.orientation === 'v';
        Object.assign(a.r, { name: String(e.name || '').trim() || null, orientation: e.orientation, height_m: num(e.height_m), len: v ? l : p, wid: v ? p : l });
        if (num(e.pos_x) !== null && num(e.pos_y) !== null) Object.assign(a.r, { x: num(e.pos_x), y: num(e.pos_y), otomatis: false });
        this.ukuranRak(a.r);
        this.ukuranZona(a.z);
      } else {
        if (!String(e.name || '').trim()) { this.pesan('Nama objek wajib diisi.', 'danger'); return; }
        const j = this.meta.jenisObjek?.[e.object_type];
        this.catat({ op: 'objek', id, data: e });
        const o = this.objek(id);
        const num = (v) => Number(String(v).replace(',', '.'));
        Object.assign(o, { name: e.name.trim(), type: e.object_type, label: j?.label ?? o.label, fill: j?.fill ?? o.fill, stroke: j?.stroke ?? o.stroke, solid: j?.solid ?? o.solid, x: num(e.pos_x), y: num(e.pos_y), p: num(e.length_m), l: num(e.width_m) });
      }
      this.pesan('Diterapkan — belum tersimpan.');
      this.gambar();
    },

    nonaktifkan() {
      if (!this.pilih) return;
      const { jenis, id } = this.pilih;
      if (jenis !== 'obj' && !this.f.alasan) { this.pesan('Pilih alasan.', 'danger'); return; }
      this.catat({ op: 'nonaktif', jenis, id, alasan: this.f.alasan });
      if (jenis === 'zona') this.d.zones = this.d.zones.filter((z) => String(z.id) !== String(id));
      else if (jenis === 'rak') { const a = this.rakLokal(id); a.z.racks = a.z.racks.filter((r) => String(r.id) !== String(id)); }
      else this.d.objects = this.d.objects.filter((o) => String(o.id) !== String(id));
      this.tutup();
    },

    /** A-255: bin kosong di sebelah ikut terpakai barang besar di bin utama (diganti Gabung bin di Bagian 2). */
    binLama(a) {
      return a ? a.r.levels.flatMap((l) => l.bins).filter((b) => this.idNyata(b.id)) : [];
    },

    tandaiTerpakai() {
      const a = this.pilih?.jenis === 'rak' ? this.rakLokal(this.pilih.id) : null;
      const t = this.f.tandai;
      if (!a || !t.utama || !t.bins.length || !t.alasan.trim()) { this.pesan('Pilih bin utama, bin yang ikut terpakai, dan isi alasannya.', 'danger'); return; }
      const utama = this.binLama(a).find((b) => String(b.id) === String(t.utama));
      this.catat({ op: 'tandai', utama: Number(t.utama), bins: t.bins.map(Number), alasan: t.alasan.trim() });
      for (const b of this.binLama(a)) if (t.bins.map(String).includes(String(b.id))) Object.assign(b, { status: 'terpakai', terpakai_oleh: utama?.code });
      this.f.tandai = { utama: '', bins: [], alasan: '' };
      this.gambar();
    },

    lepasTerpakai(binId) {
      const a = this.pilih?.jenis === 'rak' ? this.rakLokal(this.pilih.id) : null;
      const b = this.binLama(a).find((x) => x.id === binId);
      if (!b) return;
      this.catat({ op: 'lepas', bin: binId });
      Object.assign(b, { status: b.total > 0 ? 'terisi' : 'kosong', terpakai_oleh: null });
      this.gambar();
    },

    simpanGedung() {
      const num = (v) => (String(v ?? '').trim() === '' ? null : Number(String(v).replace(',', '.')));
      const p = num(this.f.gedung.length_m);
      const l = num(this.f.gedung.width_m);
      if ((p === null) !== (l === null)) { this.pesan('Isi panjang dan lebar gedung, atau kosongkan keduanya.', 'danger'); return; }
      this.catat({ op: 'gedung', data: { ...this.f.gedung } });
      this.d.gedung = p === null ? null : { p, l };
      this.gambar();
      this.pas(this.otomatis);
    },

    segarkanPanelRak(a) {
      if (this.rak && String(this.rak.id) === String(a.r.id)) this.rak = { ...this.rak, levels: a.r.levels };
    },

    // ------------------------------------------------------------ simpan

    async simpan() {
      if (!this.ops.length || this.menyimpan) return;
      this.menyimpan = true;
      this.galat = [];
      try {
        const hasil = await this.$wire.simpanPerubahan(this.ops);
        if (hasil.ok) {
          this.d = hasil.denah;
          this.ops = [];
          this.riwayat = [];
          this.tmp = 0;
          this.tutup();
          this.isiFormGedung();
          this.pesan(hasil.pesan);
        } else {
          this.galat = hasil.galat || [];
          this.pesan(hasil.pesan || 'Perubahan belum tersimpan — perbaiki yang ditandai merah.', 'danger');
        }
      } finally {
        this.menyimpan = false;
        this.gambar();
      }
    },

    aturEdit(nyala) {
      if (!nyala && this.ops.length && !window.confirm('Ada ' + this.ops.length + ' perubahan belum disimpan. Buang?')) return;
      if (!nyala) this.batalSemua();
      this.edit = nyala;
      this.tab = nyala ? 'atur' : 'isi';
      this.pilih = null;
      this.rak = null;
      this.galat = [];
      this.gambar();
    },

    pesan(teks, jenis = 'success') {
      window.dispatchEvent(new CustomEvent('pesan', { detail: { teks, jenis } }));
    },
  };
}

let terdaftar = false;
function daftar() {
  if (terdaftar || !window.Alpine) return;
  window.Alpine.data('denahGedung', denahGedung);
  terdaftar = true;
}

document.addEventListener('livewire:init', daftar);
document.addEventListener('alpine:init', daftar);
