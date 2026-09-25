# Prompt: melanjutkan pekerjaan di kantor (XAMPP)

**Versi:** 2.0
**Tanggal:** 25 September 2026
**Status:** aktif — serah terima dari sesi rumah 25 Sep 2026 malam (XAMPP3 + MariaDB 10.4.32) ke sesi kantor (XAMPP + MariaDB 10.4.27). v1.x (arsip) adalah serah terima 25 Sep pagi yang semua butirnya sudah selesai; arah sebaliknya: [00-lanjutkan-di-rumah.md](00-lanjutkan-di-rumah.md) (arsip)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: di kantor, `git pull`, buka Claude Code di `C:\xampp\htdocs\wms`, lalu tempel **seluruh blok prompt di §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (25 Sep 2026, malam)

| Hal | Keadaan |
|---|---|
| Branch `main` | Semua pekerjaan sesi rumah di-commit & di-push: `3df7bdb` (2a–2e) dan `98632f8` (2f), ditambah commit prompt ini; lihat `git log -3` |
| Uji | **632 hijau / 7.118 asersi** (MariaDB 10.4.32, XAMPP3 rumah). `_verify.py` OK; `ui-check` 87 cek, 0 error console. `alur-req-sj` 10/10 dan `alur-pendukung` 9/9 terakhir dijalankan di awal sesi (sebelum perubahan) — jalankan ulang di langkah 0 |
| Migrasi baru sejak `5c44d91` | Tenant `000220_create_attachments_table`, `000230_add_freeze_override_to_pick_tasks`, `000240_add_over_receipt_columns`. Tidak ada migrasi pusat baru |
| Selesai sesi rumah | Sisa Fase 1 butir 2a–2f: notifikasi §8 + pengingat harian `DailyReminders` (A-233–A-237); lampiran generik — foto ISU, foto serah terima AST, arsip PDF opname (A-238); `requests:expire-substitutions` tiap jam (A-239); override bin beku untuk SJ mendesak (A-240); 16 laporan §9 modul 19–22, total 41 laporan (A-241); short pick PCK TRF → TRF backorder pengganti (A-242); `stock:reconcile` 02:00 (A-243); bukti terima per unit serial/potongan (A-244); kelebihan terima GRN transfer/retur → ADJ `over_receipt` (A-245) |
| Menunggu keputusan pemilik | **SJ balik** untuk barang di tangan klien (mengubah [A-111](../wms/04-keputusan-dan-asumsi.md#a-111): butuh SJ tanpa PCK); **transfer aset On-site antar proyek** (mengubah [A-116](../wms/04-keputusan-dan-asumsi.md#a-116)/BR-RET-02: transisi AST baru + makna kejadian `asset_returned`/`asset_checked_out`); **150 asumsi** di tinjauan asumsi v1.12 — kolom *Keputusan* masih kosong |
| Sisa lain | 2g: cross-dock (A-83), impor gudang/bin (O-12), OTP otomatis (O-15), isi balik `from_stock_status` (A-194); utang dokumen kecil: [16 §2/§12](../wms/16-shared-laporan-berkas.md) (tabel izin & centang definisi selesai) belum mencerminkan 41 laporan; kompresi foto (A-23) belum ada |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pekerjaan WMS di mesin kantor (XAMPP di C:\xampp, repo C:\xampp\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp\php-8.3.33 — JANGAN pakai C:\xampp\php\php.exe (7.4);
MariaDB 10.4.27, mysql di C:\xampp\mysql\bin\mysql.exe, root tanpa password;
web `php artisan serve --host=127.0.0.1 --port=8000`; Node 24, Composer 2.9, `py -3`).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-kantor.md,
docs/00-laporan-progres-2026-09-24.md (§4, §5.7), docs/00-tinjauan-asumsi-2026-09-25.md, dan
docs/wms/04-keputusan-dan-asumsi.md §2.20.

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, `CACHE_STORE=array` (stancl/tenancy butuh cache bertag).
3. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate` (ada migrasi tenant 000220–000240).
4. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`
   (`--tenants` menerima id company, bukan kode).
5. `php artisan test` — harus 632 hijau. Kode tidak diubah demi MariaDB (A-76).
6. `py -3 docs/diagram/_verify.py` → HASIL: OK.
7. Jalankan `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console),
   `node tests/e2e/alur-req-sj.mjs` (10/10), `node tests/e2e/alur-pendukung.mjs` (9/9) — dua yang terakhir
   hanya lulus penuh pada demo yang baru di-seed dan dijalankan berurutan; skrip selalu keluar 0, jadi
   periksa baris LULUS/GAGAL, bukan kode keluar.
8. `php artisan stock:reconcile --tenants=1` → "saldo cocok dengan kartu stok".
Laporkan hasil langkah 0 sebelum lanjut.

## Cara kerja
- Satu tugas per giliran, berurutan. Perubahan fitur mengikuti pola modul: spesifikasi docs/wms/<nn>-*.md →
  migrasi → domain app/Domain/<Modul> (satu Action per permission) → rute GET/POST, Livewire, sidebar + Ctrl+K
  → uji tests/Feature/<Modul> per TC → dokumen (ERD via docs/diagram/_generate_erd.py, asumsi baru,
  README changelog dengan ID, laporan progres §5, §13 spesifikasi).
- Larangan keras CLAUDE.md: tanpa harga/uang di luar app/Domain/Purchasing, tanpa hapus fisik, stok hanya lewat
  app/Domain/Stock/Support/StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Pint HANYA pada berkas yang diubah (`vendor/bin/pint <berkas…>`), jangan per direktori.
- Jangan jalankan dua suite uji bersamaan (database uji dipakai bersama); E2E jangan bersamaan dengan suite,
  dan jangan mengedit kode aplikasi saat E2E berjalan (server membaca working tree).
- Boleh mendelegasikan ke subagen, lalu VERIFIKASI SENDIRI: `php artisan test`, `_verify.py`,
  `tenants:migrate`, `npm run build`, `node tests/e2e/ui-check.mjs`.
- Keputusan yang tidak tertulis: ambil pilihan paling sesuai dokumen, catat sebagai asumsi "Perlu validasi".
  Jangan berhenti untuk bertanya kecuali keputusan bisnis besar (mis. mengubah mesin status/kejadian stok).
- Jangan commit kecuali saya minta. Jangan menyentuh database di luar prefiks `wms_`.
- Setiap file dokumen ≤ 450 baris; bila README penuh, pindahkan blok changelog tertua ke
  docs/00-catatan-perubahan-arsip.md (naikkan versinya, sebut blok yang dipindah).

## Jatah nomor
- Asumsi berikutnya: **A-246, A-247, …** (A-233–A-245 dipakai sesi rumah 25 Sep malam, §2.20).
- TC berikutnya: TC-RPT-11, TC-STK-36, TC-SJ-19, TC-GRN-22, TC-ADJ-13, TC-AST-14, TC-TRF-18, TC-RET-18,
  TC-NTF-12, TC-OPN-22, TC-ISU-19.
- Versi berikutnya: README 0.50→0.51, 04-keputusan 0.38→0.39, 06-katalog 0.20→0.21, model data 0.21→0.22,
  laporan progres 1.20→1.21, tinjauan asumsi 1.12→1.13, arsip changelog 1.4→1.5.

## Yang SUDAH selesai (jangan dibangun ulang)
Semua 22 spesifikasi Fase 1 (docs/wms/10–27, 30; docs/purchasing/01–02) punya kode + uji, ditambah Sisa Fase 1
butir 2a–2f dari sesi rumah 25 Sep malam: notifikasi §8 & pengingat harian; lampiran generik (tabel
attachments, GET /attachments/{id} berizin); penuaan penggantian tiap jam; override bin beku (PCK); 41 laporan;
short pick TRF → TRF pengganti; rekonsiliasi saldo harian; bukti terima per unit; kelebihan terima → ADJ.

## Urutan pekerjaan
1. **Terapkan keputusan pemilik produk** dari kolom *Keputusan* di docs/00-tinjauan-asumsi-2026-09-25.md
   (150 asumsi): `Setuju` → isi kolom *Validasi* di 04; `Ubah: …` → ubah kode + uji + dokumen (perubahan
   substantif = asumsi/keputusan baru; keputusan lama tidak diedit, isi *Diganti oleh*); `Hapus` → buang
   perilaku + uji, catat di changelog. Kerjakan yang bertanda ⚠ lebih dulu. Bila kolomnya masih kosong,
   TANYAKAN saya dulu — jangan menebak.
2. **Keputusan terbuka** (tanyakan bila belum saya jawab): SJ balik barang di tangan klien (A-111);
   transfer aset On-site antar proyek (A-116). Bangun hanya setelah ada keputusan.
3. **Sisa kecil** (bila saya minta): utang dokumen 16 §2/§12; 2g — cross-dock (A-83), impor gudang/bin
   (O-12), OTP otomatis (O-15), `from_stock_status` baris lama (A-194, butuh data produksi).
4. **Akuntansi**, **kejadian HTTP ke sistem luar [F3]**, dan semua **[F2]** — hanya bila saya minta.

Setiap tugas selesai: laporkan singkat (jumlah uji, layar baru, asumsi baru), lalu lanjut ke berikutnya
tanpa menunggu saya.
```

## 3. Catatan untuk manusia

- Profil mesin kantor lengkap di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin). Baris hosts `wms.test`, `demo.wms.test`, `auth.wms.test` sudah ada di kantor.
- Isi kolom *Keputusan* di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) (150 baris, yang ⚠ dulu) dan jawab dua keputusan terbuka (SJ balik, transfer aset antar proyek) sebelum sesi dimulai; tanpa itu Claude akan bertanya.
- Job terjadwal kini: `approval:escalate` & `requests:expire-substitutions` tiap jam, `subscriptions:cycle` 00:30, `deliveries:auto-confirm` 01:00, `stock:reconcile` 02:00, `purchase-requests:reorder` 06:00, `notifications:daily` 07:00 — semuanya melewati company yang ditangguhkan (A-236).
- `phpunit.xml` memakai `memory_limit=512M` (dompdf); suite penuh ±4,5 menit di rumah, lebih lama di kantor. E2E rapuh bila mesin sibuk — jalankan satu per satu.
- Sebelum kembali ke rumah: minta Claude Code meng-commit & mem-push, lalu perbarui prompt arah kantor → rumah ([00-lanjutkan-di-rumah.md](00-lanjutkan-di-rumah.md)).
