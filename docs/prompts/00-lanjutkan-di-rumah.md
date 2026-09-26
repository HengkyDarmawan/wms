# Prompt: melanjutkan WMS di rumah (XAMPP3)

**Versi:** 2.2
**Tanggal:** 26 September 2026
**Status:** aktif — serah terima dari sesi kantor 26 Sep 2026 (keputusan pemilik produk atas asumsi ⚠ + tujuh tugas turunannya) ke sesi rumah. v2.1 (arsip) adalah serah terima kantor → rumah 25 Sep; arah sebaliknya: [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md) v2.1 (arsip)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [Asumsi lanjutan](../wms/04b-asumsi-lanjutan.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: di rumah, `git pull`, buka Claude Code di `C:\xampp3\htdocs\wms`, lalu tempel **seluruh blok prompt di §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (26 Sep 2026)

| Hal | Keadaan |
|---|---|
| Branch `main` | Belum di-commit saat prompt ini ditulis — minta Claude Code di kantor meng-commit & mem-push dulu; lihat `git log -1` |
| Uji | **654 hijau / 7.581 asersi** (MariaDB 10.4.27, XAMPP kantor). `_verify.py` OK; `npm run build` OK; E2E & `stock:reconcile` lihat [laporan progres §5.8](../00-laporan-progres-2026-09-24.md) |
| Migrasi baru | Tenant `000250_add_over_order_to_purchase_order_lines`, `000260_add_pickup_columns_to_shipments` (isi balik identitas barang di baris SJ), `000270_add_asset_onsite_transfer_columns`, `000280_add_layout_columns_to_warehouse_tables`. Tidak ada migrasi pusat baru |
| Keputusan pemilik | 31 asumsi ⚠ diputus (28 Setuju, A-229 sebagian, A-210 diubah); A-111 & A-116 diubah → dibangun. **119 asumsi tanpa ⚠** di tinjauan asumsi v1.13 masih kosong |
| Selesai sesi kantor | PO lebih dari PRQ (A-246); SJ tanpa PCK & SJ jemput retur (A-247, A-248); transfer aset On-site antar proyek, pindahan sisa proyek, jejak lokasi, kirim tautan via WA (A-249–A-251); dokumen terkait & linimasa proyek (A-252); CNV potong banyak batang (A-253); denah gudang 2D, rak area, bin ikut terpakai, tanggal masuk FIFO (A-254–A-256) |
| Sisa lain | 2g: cross-dock (A-83), impor gudang/bin (O-12), OTP otomatis (O-15), isi balik `from_stock_status` (A-194); utang dokumen 16 §2/§12 (41 laporan); kompresi foto (A-23); tambah/ubah zona-rak-level langsung dari denah (kini lewat tab *Zona & rak*) |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pekerjaan WMS di mesin rumah (XAMPP3 di C:\xampp3, repo C:\xampp3\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp3\php\php.exe; MariaDB 10.4.32, mysql di
C:\xampp3\mysql\bin\mysql.exe, root tanpa password; web `php artisan serve --host=127.0.0.1 --port=8000`;
Node 22, Composer 2.8, `py -3`. Laragon TIDAK dipakai).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-rumah.md,
docs/00-laporan-progres-2026-09-24.md (§4, §5.8), docs/00-tinjauan-asumsi-2026-09-25.md, dan
docs/wms/04b-asumsi-lanjutan.md (A-246–A-256).

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. Pastikan MariaDB XAMPP3 berjalan. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, `CACHE_STORE=array`, DB `127.0.0.1:3306` root tanpa password.
3. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate` (migrasi tenant 000250–000280).
4. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`.
5. `php artisan test` — harus 654 hijau. Kode tidak diubah demi MariaDB (A-76).
6. `py -3 docs/diagram/_verify.py` → HASIL: OK.
7. `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console; kini juga
   memeriksa denah gudang, layar pindahan proyek, dan kartu dokumen terkait), `node tests/e2e/alur-req-sj.mjs`
   (10/10), `node tests/e2e/alur-pendukung.mjs` (9/9) — berurutan pada demo segar; periksa baris LULUS/GAGAL.
8. `php artisan stock:reconcile --tenants=1` → "saldo cocok dengan kartu stok".
Laporkan hasil langkah 0 sebelum lanjut.

## Cara kerja
- Satu tugas per giliran, berurutan. Pola modul: spesifikasi docs/wms/<nn>-*.md → migrasi → domain
  app/Domain/<Modul> (satu Action per permission) → rute GET/POST, Livewire, sidebar + Ctrl+K → uji
  tests/Feature/<Modul> per TC → dokumen (ERD via docs/diagram/_generate_erd.py, asumsi baru di 04b,
  README changelog dengan ID, laporan progres §5, §13 spesifikasi).
- Larangan keras CLAUDE.md: tanpa harga/uang di luar app/Domain/Purchasing, tanpa hapus fisik, stok hanya lewat
  StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Pint HANYA pada berkas yang diubah. Jangan jalankan dua suite uji bersamaan; E2E jangan bersamaan dengan suite,
  dan jangan mengedit kode aplikasi saat suite/E2E berjalan (kelas dimuat bertahap).
- Boleh mendelegasikan ke subagen, lalu VERIFIKASI SENDIRI.
- Keputusan yang tidak tertulis: ambil pilihan paling sesuai dokumen, catat sebagai asumsi "Perlu validasi".
- Jangan commit kecuali saya minta. Jangan menyentuh database di luar prefiks `wms_`.
- Setiap file dokumen ≤ 450 baris. 04 sudah penuh: asumsi baru masuk docs/wms/04b-asumsi-lanjutan.md.

## Jatah nomor
- Asumsi berikutnya: **A-257, A-258, …** (A-246–A-256 dipakai sesi kantor 26 Sep, 04b §2.21).
- TC berikutnya: TC-PO-12, TC-RET-22, TC-SJ-20, TC-AST-17, TC-TRF-21, TC-DOC-04, TC-CNV-17, TC-WH-26,
  TC-RPT-11, TC-STK-36, TC-GRN-22, TC-ADJ-13, TC-NTF-12, TC-OPN-22, TC-ISU-19.
- Versi berikutnya: README 0.51→0.52, 04 0.39→0.40, 04b 0.1→0.2, 06-katalog 0.21→0.22, 05 0.11→0.12,
  model data 0.22→0.23, laporan progres 1.21→1.22, tinjauan asumsi 1.13→1.14, arsip changelog 1.5→1.6.

## Yang SUDAH selesai (jangan dibangun ulang)
Semua 22 spesifikasi Fase 1 + Sisa Fase 1 2a–2f, ditambah sesi kantor 26 Sep: PO lebih dari PRQ beralasan;
SJ tanpa PCK (SJ jemput retur, SJ antar site) dengan sopir & plat; transfer aset On-site antar proyek (TRF aset,
AST `transferred`, `asset_transferred`), pindahan sisa proyek dari hub, jejak lokasi aset; dokumen terkait di
14 layar detail + linimasa dokumen proyek; CNV potong banyak batang; denah gudang 2D (/warehouses/{id}/layout),
rak area, bin ikut terpakai, tanggal masuk FIFO, ubah bin di /bins.

## Urutan pekerjaan
1. **Terapkan keputusan pemilik produk** untuk 119 asumsi tanpa ⚠ di docs/00-tinjauan-asumsi-2026-09-25.md
   (kolom *Keputusan*): `Setuju` → isi *Validasi* di 04; `Ubah: …` → ubah kode + uji + dokumen (asumsi baru di
   04b, isi *Diganti oleh*); `Hapus` → buang perilaku + uji. Bila kolomnya masih kosong, TANYAKAN saya dulu —
   tawarkan tinjauan per kelompok dengan ringkasan sederhana seperti sesi kantor 26 Sep.
2. **Sisa kecil** (bila saya minta): utang dokumen 16 §2/§12; 2g — cross-dock (A-83), impor gudang/bin (O-12),
   OTP otomatis (O-15), `from_stock_status` baris lama (A-194).
3. **Akuntansi**, **kejadian HTTP ke sistem luar [F3]**, dan semua **[F2]** (WhatsApp API, offline penuh,
   dll.) — hanya bila saya minta.

Setiap tugas selesai: laporkan singkat (jumlah uji, layar baru, asumsi baru), lalu lanjut ke berikutnya
tanpa menunggu saya.
```

## 3. Catatan untuk manusia

- Profil mesin rumah di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin). Di kantor, MariaDB XAMPP sempat mati saat sesi dimulai — nyalakan dari XAMPP Control Panel bila `php artisan migrate` menolak koneksi.
- Isi kolom *Keputusan* untuk 119 asumsi tanpa ⚠ di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) sebelum sesi, atau minta Claude menyajikannya per kelompok.
- Coba di browser demo: RET dijemput → SJ jemput (detail RET); hub proyek → *Pindahkan ke proyek lain*; gudang → *Denah gudang* → *Atur denah*; konversi Potong → *Salin pola batang 1*.
- Sebelum kembali ke kantor: minta Claude Code meng-commit & mem-push, lalu perbarui prompt arah rumah → kantor ([00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md)).
