# Prompt: melanjutkan WMS di rumah (XAMPP3)

**Versi:** 2.0
**Tanggal:** 25 September 2026
**Status:** aktif — serah terima dari sesi kantor (XAMPP + MariaDB 10.4.27) ke sesi rumah (XAMPP3 + MariaDB 10.4.32). v1.x (arsip) adalah serah terima 24 Sep yang semua butirnya sudah selesai; arah sebaliknya: [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md) (arsip)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: di rumah, `git pull`, buka Claude Code di `C:\xampp3\htdocs\wms`, lalu tempel **seluruh blok prompt di §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (25 Sep 2026, sore)

| Hal | Keadaan |
|---|---|
| Branch `main` | Seluruh pekerjaan sesi kantor 25 Sep di-commit & di-push (satu commit di atas `9578e74`); lihat `git log -1` |
| Uji | **616 hijau / 6.744 asersi** (MariaDB 10.4.27, XAMPP kantor). E2E pada demo segar: `alur-req-sj` 10/10, `alur-pendukung` 9/9, `ui-check` 87 cek, 0 error console |
| Migrasi baru sejak `9578e74` | Tenant `2026_01_01_000210_create_purchasing_tables` (PO, harga beli vendor). Tidak ada migrasi pusat baru |
| Modul selesai Fase 1 | **Semua 22 spesifikasi**: `docs/wms/10`–`27`, `30` dan `docs/purchasing/01`–`02` (Purchasing inti Fase 1b). Tidak ada dokumen tanpa kode |
| Selesai hari ini | Pindai REQ/ISU (A-206); impor vendor & saldo awal (A-207); Purchasing 1b (A-208–A-218); landing Part 5 (A-220–A-225); sidebar 12 grup lipat (A-227); hub proyek (A-228); konversi per jenis (A-229); pengaturan company (A-230); halaman penerima bertoken + foto/tanda tangan (A-231); 11 laporan §9 + 4 cetak TRF/RET/PRQ/GRN (A-232); rapi UI (font, tema, teks tanpa kode internal, beranda portal); sapuan §13 di 13 spesifikasi |
| Asumsi menunggu validasi | A-72–A-126, A-150–A-232 — **137 asumsi**, kolom *Keputusan* di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) masih kosong |
| Uji browser | `tests/e2e/ui-check.mjs`, `alur-req-sj.mjs` (10 langkah), `alur-pendukung.mjs` (9 langkah) — helper `go()` menunggu kesiapan halaman; butuh `MYSQL_BIN` |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pekerjaan WMS di mesin rumah (XAMPP3 di C:\xampp3, repo C:\xampp3\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp3\php\php.exe; MariaDB 10.4.32, mysql di
C:\xampp3\mysql\bin\mysql.exe, root tanpa password; web `php artisan serve --host=127.0.0.1 --port=8000`
karena Apache XAMPP3 memegang port 80; Node 22, Composer 2.8, `py -3`. Laragon TIDAK dipakai).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-rumah.md,
docs/00-laporan-progres-2026-09-24.md (§4, §5.6), docs/00-tinjauan-asumsi-2026-09-25.md, dan
docs/wms/04-keputusan-dan-asumsi.md §2.15–§2.19.

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, `CACHE_STORE=array` (stancl/tenancy butuh cache bertag),
   koneksi DB `127.0.0.1:3306` root tanpa password.
3. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate` (ada migrasi purchasing 000210).
4. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`
   (`--tenants` menerima id company, bukan kode).
5. `php artisan test` — harus 616 hijau. Kode tidak diubah demi MariaDB (A-76).
6. `py -3 docs/diagram/_verify.py` → HASIL: OK.
7. Baris hosts (Administrator): `127.0.0.1 wms.test demo.wms.test auth.wms.test`. Jalankan
   `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console),
   `node tests/e2e/alur-req-sj.mjs` (10/10), `node tests/e2e/alur-pendukung.mjs` (9/9) — dua yang terakhir
   hanya lulus penuh pada demo yang baru di-seed dan dijalankan berurutan.
Laporkan hasil langkah 0 sebelum lanjut.

## Cara kerja
- Satu tugas per giliran, berurutan. Perubahan fitur mengikuti pola modul: spesifikasi docs/wms/<nn>-*.md →
  migrasi → domain app/Domain/<Modul> (satu Action per permission) → rute GET/POST, Livewire, sidebar + Ctrl+K
  → uji tests/Feature/<Modul> per TC → dokumen (ERD via docs/diagram/_generate_erd.py, asumsi baru,
  README changelog dengan ID, laporan progres, §13 spesifikasi).
- Larangan keras CLAUDE.md: tanpa harga/uang di luar app/Domain/Purchasing, tanpa hapus fisik, stok hanya lewat
  app/Domain/Stock/Support/StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Pint HANYA pada berkas yang diubah (`vendor/bin/pint <berkas…>`), jangan per direktori.
- Jangan jalankan dua suite uji bersamaan (database uji dipakai bersama); E2E jangan bersamaan dengan suite.
- Boleh mendelegasikan ke subagen, lalu VERIFIKASI SENDIRI: `php artisan test`, `_verify.py`,
  `tenants:migrate`, `npm run build`, `node tests/e2e/ui-check.mjs`.
- Keputusan yang tidak tertulis: ambil pilihan paling sesuai dokumen, catat sebagai asumsi "Perlu validasi".
  Jangan berhenti untuk bertanya kecuali keputusan bisnis besar.
- Jangan commit kecuali saya minta. Jangan menyentuh database di luar prefiks `wms_`.
- Setiap file dokumen ≤ 450 baris; bila README penuh, pindahkan blok changelog tertua ke
  docs/00-catatan-perubahan-arsip.md (sudah ada polanya).

## Jatah nomor
- Asumsi berikutnya: **A-233, A-234, …** (A-206–A-232 dipakai sesi kantor 25 Sep).
- Versi berikutnya: README 0.45→0.46, 04-keputusan 0.33→0.34, 06-katalog 0.19→0.20, model data 0.20→0.21,
  laporan progres 1.15→1.16, tinjauan asumsi 1.7→1.8.

## Yang SUDAH selesai (jangan dibangun ulang)
Semua 22 spesifikasi Fase 1 (docs/wms/10–27, 30; docs/purchasing/01–02) punya kode + uji. Sesi kantor 25 Sep
menambah: pindai REQ/ISU (A-206); impor Excel vendor & saldo awal (A-207); Purchasing inti 1b — PO dari PRQ,
harga beli vendor, approval nilai PO, PO → catatan pemesanan → GRN, cetak PO (A-208–A-218); landing page
(A-220–A-225); sidebar 12 grup lipat + filter (A-227); hub proyek /projects/{id} dengan tab & tutup proyek
berchecklist (A-228); form konversi per jenis dengan ConversionPlanner (A-229); layar Pengaturan company
/settings/company (A-230); halaman penerima bertoken /terima/{token} + foto & tanda tangan bukti terima,
policy issueToken (A-231); 11 laporan §9 Stock/Request/Shipment + cetak TRF/RET/PRQ/GRN (A-232); beranda
portal klien dengan Stok On-site; teks UI tanpa kode internal; tanggal-jam zona company; sapuan §13.

## Urutan pekerjaan
1. **Terapkan keputusan pemilik produk** dari kolom *Keputusan* di docs/00-tinjauan-asumsi-2026-09-25.md
   (137 asumsi): `Setuju` → isi kolom *Validasi* di 04; `Ubah: …` → ubah kode + uji + dokumen (perubahan
   substantif = asumsi/keputusan baru; keputusan lama tidak diedit, isi *Diganti oleh*); `Hapus` → buang
   perilaku + uji, catat di changelog. Kerjakan yang bertanda ⚠ lebih dulu. Bila kolomnya masih kosong,
   TANYAKAN saya dulu — jangan menebak.
2. **Sisa Fase 1** (setelah butir 1, atau bila saya minta), urutan usulan:
   a. Notifikasi §8 yang belum: SLA tinjau & pengganti item & tanggal janji (14), item sementara & proyek
      ditutup (11), reservasi menggantung & periode dikunci (13), PR ke penindak lanjut (26), jatuh tempo &
      sisa umur aset (25), pengingat tagihan WA (17, [F2] kanal).
   b. Lampiran generik (A-68): foto pemakaian ISU, foto serah terima aset keluar, PDF laporan opname.
   c. Override SJ mendesak dari bin beku (BR-OPN-02); job penuaan tenggat penggantian & pengingat SLA (14).
   d. Laporan §9 modul 19–22 (penerimaan, approval, opname, retur/transfer) di kerangka 16 §3.2.
   e. SJ balik untuk barang di tangan klien; short pick PCK TRF → backorder REQ penunggu (22).
   f. ADJ kelebihan terima (BR-GRN-05); bukti terima per unit serial/potong (15); transfer aset antar
      proyek (25); rekonsiliasi saldo terjadwal (13).
   g. Menunggu keputusan pemilik: cross-dock (A-83), impor gudang/bin (O-12), OTP otomatis (O-15),
      `from_stock_status` baris lama (A-194, butuh data produksi).
3. **Akuntansi**, **kejadian HTTP ke sistem luar [F3]**, dan semua **[F2]** (WhatsApp, offline penuh, resep
   konversi, maintenance aset, BoQ, cycle count ABC, editor template, RFID) — hanya bila saya minta.

Setiap tugas selesai: laporkan singkat (jumlah uji, layar baru, asumsi baru), lalu lanjut ke berikutnya
tanpa menunggu saya.
```

## 3. Catatan untuk manusia

- Profil mesin rumah lengkap di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin): `C:\xampp3`, PHP 8.3.33 sudah `php` di PATH, MariaDB 10.4.32, `php artisan serve` :8000. Laragon (`C:\laragon\www\wms`, MySQL 8.4) tidak dipakai; MySQL 8.4 tetap standar produksi ([A-76](../wms/04-keputusan-dan-asumsi.md#a-76), [A-162](../wms/04-keputusan-dan-asumsi.md#a-162)).
- Isi kolom *Keputusan* di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) sebelum sesi dimulai supaya butir 1 bisa langsung dikerjakan; tanpa itu Claude akan bertanya.
- `phpunit.xml` memakai `memory_limit=512M` (dompdf); suite penuh ±10 menit di mesin kantor, lebih cepat di rumah. E2E rapuh bila mesin sibuk — jalankan satu per satu.
- Sebelum kembali ke kantor: minta Claude Code meng-commit & mem-push, lalu perbarui prompt arah rumah → kantor ([00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md)).
