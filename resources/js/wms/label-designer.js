/* ============================================================
   Desainer label (18-template-dokumen-label §6, A-262)
   Komponen Alpine `labelDesigner`: kanvas seukuran label (mm × zoom),
   elemen digeser & diubah ukurannya dengan interact.js (di-bundle Vite,
   tanpa CDN). Semua angka disimpan dalam mm, dibulatkan 0,1 mm; server
   merapikan lagi (LabelDesignRules::normalize) supaya tetap di dalam label.
   ============================================================ */

import interact from 'interactjs';

const PT_KE_MM = 0.3528;
const URUTAN = ['logo', 'title', 'subtitle', 'detail', 'qr', 'barcode'];

function salin(obj) {
  return JSON.parse(JSON.stringify(obj));
}

function bulat(n, langkah) {
  return Math.round(n / langkah) * langkah;
}

function labelDesigner(cfg) {
  return {
    lebar: cfg.width,
    tinggi: cfg.height,
    elements: salin(cfg.elements),
    defaults: cfg.defaults,
    names: cfg.names,
    sample: cfg.sample,
    codeMode: cfg.codeMode,
    isDefault: cfg.isDefault,
    saved: cfg.saved,
    jadikanBawaan: false,
    urutan: URUTAN,
    dipilih: 'title',
    zoom: 6,
    grid: true,
    menyimpan: false,
    pegangan: [],

    init() {
      const ruang = (this.$refs.wadah?.clientWidth || 600) - 44; // padding wadah + bayangan kanvas
      this.zoom = Math.max(2, Math.min(14, Math.round((ruang / this.lebar) * 10) / 10));
    },

    destroy() {
      this.pegangan.forEach((i) => i.unset());
      this.pegangan = [];
    },

    tampil(k) {
      if (k === 'barcode') return this.codeMode !== 'qr';
      if (k === 'qr') return this.codeMode !== 'barcode';
      return !!this.elements[k].visible;
    },

    kanvasStyle() {
      const z = this.zoom;
      return `width:${this.lebar * z}px;height:${this.tinggi * z}px;background-size:${z}px ${z}px, ${z}px ${z}px, ${5 * z}px ${5 * z}px, ${5 * z}px ${5 * z}px;`;
    },

    kotak(k) {
      const e = this.elements[k];
      const z = this.zoom;
      return `left:${e.x * z}px;top:${e.y * z}px;width:${e.w * z}px;height:${e.h * z}px;`;
    },

    teksStyle(k) {
      const e = this.elements[k];
      const px = e.font * PT_KE_MM * this.zoom;
      return `font-size:${px}px;line-height:1.2;font-weight:${e.bold ? 700 : 400};text-align:${k === 'barcode' ? 'center' : e.align};`;
    },

    barcodeTinggi() {
      const e = this.elements.barcode;
      const teks = e.show_text ? e.font * PT_KE_MM * 1.25 : 0;
      return Math.max(1, e.h - teks) * this.zoom;
    },

    qrSisi() {
      const e = this.elements.qr;
      const s = Math.min(e.w, e.h) * this.zoom;
      return `width:${s}px;height:${s}px;`;
    },

    qrPosisi() {
      const a = this.elements.qr.align;
      return `align-items:center;justify-content:${a === 'left' ? 'flex-start' : a === 'right' ? 'flex-end' : 'center'};`;
    },

    batas(k) {
      const e = this.elements[k];
      e.w = Math.min(Math.max(1.5, e.w), this.lebar);
      e.h = Math.min(Math.max(1.5, e.h), this.tinggi);
      e.x = Math.min(Math.max(0, e.x), this.lebar - e.w);
      e.y = Math.min(Math.max(0, e.y), this.tinggi - e.h);
    },

    rapikan(k) {
      const e = this.elements[k];
      const langkah = this.grid ? 0.5 : 0.1;
      ['x', 'y', 'w', 'h'].forEach((f) => { e[f] = Math.round(bulat(e[f], langkah) * 10) / 10; });
      this.batas(k);
    },

    pasang(node, k) {
      const self = this;
      const it = interact(node)
        .draggable({
          listeners: {
            move(ev) {
              const e = self.elements[k];
              e.x += ev.dx / self.zoom;
              e.y += ev.dy / self.zoom;
              self.batas(k);
            },
            end() { self.rapikan(k); },
          },
        })
        .resizable({
          edges: { left: true, right: true, top: true, bottom: true },
          margin: 6,
          listeners: {
            move(ev) {
              const e = self.elements[k];
              e.x += ev.deltaRect.left / self.zoom;
              e.y += ev.deltaRect.top / self.zoom;
              e.w = ev.rect.width / self.zoom;
              e.h = ev.rect.height / self.zoom;
              self.batas(k);
            },
            end() { self.rapikan(k); },
          },
        });
      this.pegangan.push(it);
    },

    ubahAngka(f, nilai) {
      const n = parseFloat(String(nilai).replace(',', '.'));
      if (!this.dipilih || Number.isNaN(n)) return;
      if (f === 'font') {
        this.elements[this.dipilih].font = Math.min(72, Math.max(4, Math.round(n * 10) / 10));
        return;
      }
      this.elements[this.dipilih][f] = n;
      this.rapikan(this.dipilih);
    },

    perbesar(arah) {
      this.zoom = Math.max(2, Math.min(20, Math.round((this.zoom + arah) * 10) / 10));
    },

    tataUlang() {
      this.elements = salin(this.defaults[this.codeMode]);
    },

    tombol(ev) {
      if (!this.dipilih || ['INPUT', 'SELECT', 'TEXTAREA'].includes(ev.target.tagName)) return;
      const langkah = ev.shiftKey ? 5 : 0.5;
      const e = this.elements[this.dipilih];
      const geser = { ArrowLeft: ['x', -langkah], ArrowRight: ['x', langkah], ArrowUp: ['y', -langkah], ArrowDown: ['y', langkah] }[ev.key];
      if (!geser) return;
      ev.preventDefault();
      e[geser[0]] += geser[1];
      this.rapikan(this.dipilih);
    },

    async simpan() {
      this.menyimpan = true;
      try {
        await this.$wire.simpan(salin(this.elements), this.codeMode, this.jadikanBawaan);
      } finally {
        this.menyimpan = false;
      }
    },
  };
}

let terdaftar = false;
function daftar() {
  if (terdaftar || !window.Alpine) return;
  window.Alpine.data('labelDesigner', labelDesigner);
  terdaftar = true;
}

// Pola modul lain: Livewire memuat Alpine; daftarkan sebelum Alpine mulai.
document.addEventListener('livewire:init', daftar);
document.addEventListener('alpine:init', daftar);
