/* ============================================================
   Komponen Alpine `pilih` (A-358, A-383, A-384): pilihan TUNGGAL yang bisa
   dicari, memakai tom-select yang sudah dibundel Vite (tanpa CDN). Dipasang
   oleh komponen Blade <x-pilih> dengan `wire:ignore`; nilainya disinkronkan
   ke properti Livewire lewat `$wire.entangle(...)`, jadi aksi simpan
   menerima nilai yang sama dengan <select> biasa ('' = null, atau '' bila
   propertinya string).

   - Opsi boleh membawa `badge` (mis. jabatan) dan `sub` (teks kecil abu-abu,
     mis. unit) lewat data-*; keduanya ikut dicari dan tampil juga pada nilai
     yang sudah terpilih. Kelompok = <optgroup>, urutannya dikunci.
   - Mode server (`data-server`): ketik >= 2 huruf → `$wire.cariPilihan(model,
     kata)` (method Livewire #[Json], renderless) dengan jeda 300 ms; hasil
     dari server sudah tersaring izin & cakupan (trait CariPilihan).
   - Kotak dibuat ulang (wire:key berubah) saat daftarnya berganti; bila nilai
     lama tidak ada lagi di daftar baru, nilainya dikosongkan.
   - Aksesibilitas: label terhubung ke kotak cari (aria-labelledby),
     `aria-invalid` + `aria-describedby` mengikuti tanda galat pembungkus.
   ============================================================ */

import TomSelect from 'tom-select';

const teks = (v) => (v === null || v === undefined ? '' : String(v));

/* Kotak yang sudah pernah dibuat di halaman ini (per komponen Livewire + model):
   pembuatan kedua berarti daftarnya berganti, bukan halaman baru dimuat. */
const pernahDibuat = new Set();

function isi(d, esc) {
  const badge = d.badge ? ` <span class="badge nx-badge-jabatan">${esc(d.badge)}</span>` : '';
  const sub = d.sub ? ` <span class="small text-muted">· ${esc(d.sub)}</span>` : '';
  return `<span class="fw-semibold">${esc(d.text)}</span>${badge}${sub}`;
}

function pilih(nilai) {
  return {
    nilai,
    ts: null,
    pengamat: null,

    init() {
      const select = this.$refs.select;
      const server = select.dataset.server === '1';
      const minHuruf = Number(select.dataset.min || 2);
      const model = select.dataset.model || '';
      const kunciBuat = `${this.$wire?.$id ?? ''}:${model}`;
      const buatUlang = pernahDibuat.has(kunciBuat);
      pernahDibuat.add(kunciBuat);
      // Properti Livewire bertipe string ('') tetap menerima '', properti ?int menerima null.
      const kosong = typeof this.nilai === 'string' ? '' : null;

      const pengaturan = {
        allowEmptyOption: true,
        maxOptions: server ? 60 : null,
        placeholder: select.dataset.placeholder || '',
        searchField: ['text', 'badge', 'sub', 'cari'],
        optgroupField: 'group',
        lockOptgroupOrder: true,
        // A-404: dropdown selalu ditempel ke <body> (lihat bawah), jadi gaya & ukuran kecil
        // dibawa lewat kelasnya sendiri, bukan turunan .nx-pilih / .nx-pilih-sm.
        dropdownClass: 'ts-dropdown nx-pilih-dropdown' + (select.closest('.nx-pilih-sm') ? ' nx-pilih-dropdown-sm' : ''),
        render: {
          option: (d, esc) => (d.value === '' ? `<div class="text-muted">${esc(d.text)}</div>` : `<div>${isi(d, esc)}</div>`),
          // Nilai terpilih: badge & unit ikut tampil (A-358).
          item: (d, esc) => (d.value === '' ? `<div class="text-muted">${esc(d.text)}</div>` : `<div class="nx-pilih-nilai">${isi(d, esc)}</div>`),
          optgroup_header: (d, esc) => `<div class="optgroup-header">${esc(d.label)}</div>`,
          no_results: (d, esc) => `<div class="no-results">${esc(select.dataset.kosong || '')}</div>`,
          loading: (d, esc) => `<div class="no-results">${esc(select.dataset.memuat || '')}</div>`,
        },
        onChange: (v) => {
          const baru = teks(v) === '' ? kosong : v;
          if (teks(baru) !== teks(this.nilai)) this.nilai = baru;
        },
      };

      // A-404: dropdown SELALU di <body> (dulu hanya `dialog`). Di dalam .ts-wrapper ia dipotong
      // .table-responsive (baris tabel) dan tertutup kartu berikutnya yang ber-transform saat hover
      // (motion.css). tom-select menghitung ulang posisinya saat window scroll/resize.
      pengaturan.dropdownParent = 'body';

      if (server) {
        Object.assign(pengaturan, {
          loadThrottle: 300,
          shouldLoad: (kata) => kata.trim().length >= minHuruf,
          load: (kata, selesai) => {
            this.$wire.cariPilihan(model, kata)
              .then((hasil) => {
                const daftar = (hasil || []).map((o) => ({ ...o, value: String(o.value) }));
                daftar.forEach((o) => {
                  if (o.group && !this.ts.optgroups[o.group]) this.ts.addOptionGroup(o.group, { value: o.group, label: o.group });
                  // Opsi awal yang sama diperbarui (membawa `cari`), supaya tidak tersaring di browser.
                  if (Object.prototype.hasOwnProperty.call(this.ts.options, o.value)) this.ts.updateOption(o.value, o);
                });
                selesai(daftar);
              })
              .catch(() => selesai());
          },
        });
      }

      this.ts = new TomSelect(select, pengaturan);
      this.hubungkanLabel(select);
      this.ikutiGalat(select);
      this.tandaiMengetik();

      const ada = (v) => teks(v) === '' || Object.prototype.hasOwnProperty.call(this.ts.options, teks(v));
      if (buatUlang && !ada(this.nilai)) {
        // Daftar induk berganti dan nilai lama tidak ada lagi → kosongkan.
        this.nilai = kosong;
      }
      this.ts.setValue(teks(this.nilai), true);

      this.$watch('nilai', (v) => {
        if (teks(v) === teks(this.ts.getValue())) return;
        if (server && teks(v) !== '' && !ada(v)) {
          // Nilai diisi dari server (pindai, baris bergeser) dan belum ada di kotak → minta labelnya (A-392).
          this.$wire.labelPilihan(model, teks(v)).then((o) => {
            if (!this.ts || teks(this.nilai) !== teks(v)) return;
            if (o?.group && !this.ts.optgroups[o.group]) this.ts.addOptionGroup(o.group, { value: o.group, label: o.group });
            if (o) this.ts.addOption({ ...o, value: String(o.value) });
            this.ts.setValue(teks(v), true);
          }).catch(() => {});
          return;
        }
        this.ts.setValue(teks(v), true);
      });
    },

    /* Label dari <x-pilih label="…"> (for = {id}-ts-control) atau label[for={id}] di luar komponen. */
    hubungkanLabel(select) {
      const kontrol = this.ts.control_input;
      if (select.dataset.aria) kontrol.setAttribute('aria-label', select.dataset.aria);
      const milikSendiri = document.getElementById(`${select.id}-label`);
      if (milikSendiri) {
        kontrol.setAttribute('aria-labelledby', milikSendiri.id);
        this.ts.dropdown_content.setAttribute('aria-labelledby', milikSendiri.id);
        return;
      }
      const luar = document.querySelector(`label[for="${CSS.escape(select.id)}"], label[for="${CSS.escape(kontrol.id)}"]`);
      // Label di luar komponen bisa di-morph Livewire (for/id kembali); aria-label tetap bertahan.
      if (luar && !kontrol.getAttribute('aria-label')) kontrol.setAttribute('aria-label', luar.textContent.replace(/\s*\*\s*$/, '').trim());
    },

    /* Selama kata cari diketik, teks nilai terpilih disembunyikan (bukan "— Ikuti jabatan — ri"):
       kelas `nx-mengetik` di pembungkus Tom Select + CSS. Setelah memilih, Tom Select mengosongkan
       kotak cari (event `update`); saat kotak ditinggal (blur) sisa ketikan dikosongkan di sini,
       jadi nilai terpilih tampil lagi. */
    tandaiMengetik() {
      const kontrol = this.ts.control_input;
      const tandai = () => this.ts?.wrapper.classList.toggle('nx-mengetik', kontrol.value !== '');
      kontrol.addEventListener('input', tandai);
      kontrol.addEventListener('update', tandai);
      this.ts.on('blur', () => {
        this.ts?.setTextboxValue('');
        tandai();
      });
    },

    /* Pembungkus .nx-pilih di luar wire:ignore memegang tanda galat → teruskan ke kotak cari. */
    ikutiGalat(select) {
      const bungkus = this.$el.closest('.nx-pilih');
      if (!bungkus) return;
      const terapkan = () => {
        const galat = bungkus.classList.contains('is-invalid');
        const kontrol = this.ts?.control_input;
        if (!kontrol) return;
        kontrol.setAttribute('aria-invalid', galat ? 'true' : 'false');
        if (galat) kontrol.setAttribute('aria-describedby', `${select.id}-galat`);
        else kontrol.removeAttribute('aria-describedby');
      };
      terapkan();
      this.pengamat = new MutationObserver(terapkan);
      this.pengamat.observe(bungkus, { attributes: true, attributeFilter: ['class'] });
    },

    destroy() {
      this.pengamat?.disconnect();
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
