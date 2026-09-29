/* ============================================================
   Komponen Alpine `pilihTag` (A-350): kotak pilihan ganda bergaya tag
   memakai tom-select (dibundel Vite, tanpa CDN). Dipasang oleh komponen
   Blade <x-pilih-tag> dengan `wire:ignore`; nilainya disinkronkan ke
   properti Livewire lewat `$wire.entangle(...)`, jadi aksi simpan menerima
   array id yang sama dengan <select multiple> biasa.
   ============================================================ */

import TomSelect from 'tom-select';

const sama = (a, b) => a.length === b.length && a.every((v, i) => v === b[i]);
const rapikan = (v) => (Array.isArray(v) ? v : v === null || v === undefined || v === '' ? [] : [v])
  .map(String)
  .filter((x) => x !== '');

function pilihTag(nilai) {
  return {
    nilai,
    ts: null,

    init() {
      const select = this.$refs.select;

      this.ts = new TomSelect(select, {
        plugins: {
          remove_button: { title: select.dataset.hapus || 'Hapus' },
        },
        maxOptions: null,
        hideSelected: true,
        closeAfterSelect: false,
        placeholder: select.dataset.placeholder || '',
        render: {
          no_results: () => `<div class="no-results">${select.dataset.kosong || ''}</div>`,
        },
        onChange: (v) => {
          const baru = rapikan(v);
          if (!sama(baru, rapikan(this.nilai))) this.nilai = baru;
        },
      });

      this.ts.setValue(rapikan(this.nilai), true);

      this.$watch('nilai', (v) => {
        const baru = rapikan(v);
        if (!sama(baru, rapikan(this.ts.getValue()))) this.ts.setValue(baru, true);
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
  window.Alpine.data('pilihTag', pilihTag);
  terdaftar = true;
}

document.addEventListener('livewire:init', daftar);
document.addEventListener('alpine:init', daftar);
