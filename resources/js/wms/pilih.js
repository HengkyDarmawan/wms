/* ============================================================
   Komponen Alpine `pilih` (A-358): pilihan TUNGGAL yang bisa dicari,
   memakai tom-select yang sudah dibundel Vite (tanpa CDN). Dipasang oleh
   komponen Blade <x-pilih> dengan `wire:ignore`; nilainya disinkronkan ke
   properti Livewire lewat `$wire.entangle(...)`, jadi aksi simpan menerima
   nilai yang sama dengan <select> biasa ('' = null).

   Tiap pilihan boleh membawa `badge` (mis. jabatan) dan `sub` (teks kecil
   abu-abu, mis. unit) lewat atribut data-data; keduanya ikut dicari dan
   tampil juga pada nilai yang sudah terpilih.
   ============================================================ */

import TomSelect from 'tom-select';

const teks = (v) => (v === null || v === undefined ? '' : String(v));

function isi(d, esc) {
  const badge = d.badge ? ` <span class="badge nx-badge-jabatan">${esc(d.badge)}</span>` : '';
  const sub = d.sub ? ` <span class="small text-muted">· ${esc(d.sub)}</span>` : '';
  return `<span class="fw-semibold">${esc(d.text)}</span>${badge}${sub}`;
}

function pilih(nilai) {
  return {
    nilai,
    ts: null,

    init() {
      const select = this.$refs.select;

      this.ts = new TomSelect(select, {
        allowEmptyOption: true,
        maxOptions: null,
        placeholder: select.dataset.placeholder || '',
        searchField: ['text', 'badge', 'sub'],
        render: {
          option: (d, esc) => (d.value === '' ? `<div class="text-muted">${esc(d.text)}</div>` : `<div>${isi(d, esc)}</div>`),
          // Nilai terpilih: badge & unit ikut tampil (A-358).
          item: (d, esc) => (d.value === '' ? `<div class="text-muted">${esc(d.text)}</div>` : `<div class="nx-pilih-nilai">${isi(d, esc)}</div>`),
          no_results: (d, esc) => `<div class="no-results">${esc(select.dataset.kosong || '')}</div>`,
        },
        onChange: (v) => {
          const baru = teks(v) === '' ? null : v;
          if (teks(baru) !== teks(this.nilai)) this.nilai = baru;
        },
      });

      this.ts.setValue(teks(this.nilai), true);

      this.$watch('nilai', (v) => {
        if (teks(v) !== teks(this.ts.getValue())) this.ts.setValue(teks(v), true);
      });
    },

    destroy() {
      this.ts?.destroy();
      this.ts = null;
    },
  };
}

let terdaftar = false;
function daftar() {
  if (terdaftar || !window.Alpine) return;
  window.Alpine.data('pilih', pilih);
  terdaftar = true;
}

document.addEventListener('livewire:init', daftar);
document.addEventListener('alpine:init', daftar);
