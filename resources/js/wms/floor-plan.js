/*
   Komponen Alpine `denahGedung` (A-320): satu kanvas SVG gedung. Satuan SVG =
   meter × skala; zoom hanya mengubah lebar tampilan (tanpa ke server).

   - Klik rak → panel isi; di mode Atur denah klik zona/rak/objek memilihnya.
   - Seret benda terpilih untuk memindah, seret kotak kecil di sudut kanan-bawah
     untuk mengubah ukuran; hasil dikirim ke Livewire (snap 0,5 m di server).
   - Tombol panah geser 0,5 m (Shift = 0,1 m), R putar 90°.
   Tanpa pustaka tambahan (D-05).
*/
function denahGedung(opts = {}) {
  return {
    skala: opts.skala || 80,
    zoom: 1,
    drag: null,

    // Selama pengguna belum memperbesar/memperkecil sendiri, kanvas selalu "pas layar"
    // — termasuk saat panel kanan muncul dan kolom denah menyempit.
    otomatis: true,

    init() {
      this.$nextTick(() => this.pas(true));
      if (window.ResizeObserver && this.$refs.wrap) {
        new ResizeObserver(() => { if (this.otomatis) this.pas(true); }).observe(this.$refs.wrap);
      }
    },

    lebarAsli() {
      return this.$refs.svg?.viewBox?.baseVal?.width || 1;
    },

    pas(otomatis = true) {
      const wrap = this.$refs.wrap;
      if (!wrap) return;
      this.otomatis = otomatis;
      this.zoom = Math.max(0.15, Math.min(1.5, (wrap.clientWidth - 8) / this.lebarAsli()));
    },

    perbesar() { this.otomatis = false; this.zoom = Math.min(3, +(this.zoom * 1.25).toFixed(3)); },
    perkecil() { this.otomatis = false; this.zoom = Math.max(0.1, +(this.zoom / 1.25).toFixed(3)); },
    persen() { return Math.round(this.zoom * 100) + '%'; },

    skalaLayar() {
      const m = this.$refs.svg.getScreenCTM();
      return m ? m.a : this.zoom;
    },

    mulai(e) {
      const g = e.target.closest('[data-jenis]');
      if (!g) return;
      const jenis = g.dataset.jenis;
      const id = Number(g.dataset.id);

      if (!this.$wire.edit) {
        if (jenis === 'rak') this.$wire.pilih(jenis, id);
        return;
      }

      e.preventDefault();
      this.drag = {
        g, jenis, id,
        ukuran: e.target.hasAttribute('data-handle'),
        sx: e.clientX, sy: e.clientY,
        x: Number(g.dataset.x), y: Number(g.dataset.y),
        p: Number(g.dataset.p), l: Number(g.dataset.l),
        pindah: false,
      };
      this.$refs.svg.setPointerCapture?.(e.pointerId);
    },

    gerak(e) {
      const d = this.drag;
      if (!d) return;
      const dx = e.clientX - d.sx;
      const dy = e.clientY - d.sy;
      if (Math.abs(dx) + Math.abs(dy) > 3) d.pindah = true;
      if (!d.pindah) return;
      const k = this.skalaLayar();
      if (d.ukuran) {
        const badan = d.g.querySelector('[data-badan]');
        badan?.setAttribute('width', Math.max(8, d.p * this.skala + dx / k));
        badan?.setAttribute('height', Math.max(8, d.l * this.skala + dy / k));
      } else {
        d.g.setAttribute('transform', `translate(${d.x * this.skala + dx / k},${d.y * this.skala + dy / k})`);
      }
    },

    lepas(e) {
      const d = this.drag;
      if (!d) return;
      this.drag = null;
      if (!d.pindah) {
        this.$wire.pilih(d.jenis, d.id);
        return;
      }
      const k = this.skalaLayar() * this.skala;
      const mx = (e.clientX - d.sx) / k;
      const my = (e.clientY - d.sy) / k;
      if (d.ukuran) {
        this.$wire.ubahUkuran(d.jenis, d.id, Math.max(0.5, d.p + mx), Math.max(0.5, d.l + my));
      } else {
        this.$wire.geser(d.jenis, d.id, Math.max(0, d.x + mx), Math.max(0, d.y + my));
      }
    },

    tombol(e) {
      if (!this.$wire.edit || !this.$wire.terpilih) return;
      const tag = document.activeElement?.tagName;
      if (['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) return;
      const l = e.shiftKey ? 0.1 : 0.5;
      const arah = { ArrowLeft: [-l, 0], ArrowRight: [l, 0], ArrowUp: [0, -l], ArrowDown: [0, l] }[e.key];
      if (arah) {
        e.preventDefault();
        this.$wire.geserHalus(arah[0], arah[1]);
      } else if (e.key === 'r' || e.key === 'R') {
        e.preventDefault();
        this.$wire.putar();
      }
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
