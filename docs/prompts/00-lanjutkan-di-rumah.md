# Prompt: melanjutkan pembangunan Fase 1 di rumah (XAMPP3)

**Versi:** 1.9
**Tanggal:** 25 September 2026
**Status:** arsip — semua butir §2 selesai 25 Sep 2026; lanjutan di kantor: [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md). Semula: serah terima dari sesi kantor (XAMPP) ke sesi rumah (XAMPP3 + MariaDB 10.4; v1.2: mesin rumah pindah dari Laragon ke `C:\xampp3`; v1.3: langkah 0 lulus dan modul Konversi & Waste selesai — jatah nomor dan urutan sisa diperbarui; v1.4: modul Aset selesai; v1.5: modul Purchase Request selesai — jatah nomor diperbarui; v1.6: modul Platform penuh selesai; v1.7: Pendukung F1 selesai)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: setelah `git pull`, buka Claude Code di root repo lalu tempel **seluruh blok prompt di §2**. Bagian §1 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (24 Sep 2026, sore)

| Hal | Keadaan |
|---|---|
| Branch `main` | Semua modul di bawah sudah di-commit dan di-push; **493 uji hijau** di MariaDB 10.4 (kantor). Mesin rumah kini juga MariaDB 10.4 (XAMPP3), jadi hasilnya setara |
| Modul selesai Fase 1 | Access, Master, Warehouse, Stock, Request, Picking/Shipment, Shared (laporan & berkas), Platform-login, **Penerimaan/QC/put-away/RTV (19)**, **Approval (20)**, **Opname & Penyesuaian (21)**, **Retur & Transfer (22)**, **Pemakaian ISU (23)**, **Template dokumen & label (18, sesi paralel)** |
| Perbaikan penting | Pengiriman kini mencatat `qty_shipped`/`qty_received` ke baris REQ (`RequestFulfillment`); Stok Tersedia hanya dari bin `storage` (A-85); aset CSS/JS halaman company (`asset_helper_tenancy=false`); perilaku template NexaDash (`resources/js/globals.js`) |
| Branch `wip/konversi-waste` | Modul 6 **setengah jadi, belum diuji**: migrasi `000130`, enum/model/support `app/Domain/Conversion`, plus `docs/wip-konversi-waste-rencana.md` berisi rancangan lengkap, rencana asumsi A-153–A-161, permission, dan urutan sisa langkah |
| Asumsi menunggu validasi | A-72–A-119 dan A-150–A-152 (modul ini), A-120–A-126 (modul Template) |
| Laporan progres | Fase 1: 11 selesai · 6 sebagian · 6 belum (dari 23 butir Blueprint §18) |
| Uji browser | `tests/e2e/ui-check.mjs` (semua menu) dan `tests/e2e/alur-req-sj.mjs` (skenario §5) — lihat `tests/e2e/README.md` |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pembangunan WMS Fase 1 di mesin rumah (XAMPP3 di C:\xampp3: PHP 8.3.33 = `php`,
MariaDB 10.4.32, web `php artisan serve --host=127.0.0.1 --port=8000`).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-rumah.md,
docs/00-laporan-progres-2026-09-24.md, dan docs/wms/04-keputusan-dan-asumsi.md §2.6–§2.8.

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, pastikan `CACHE_STORE=array`. `database`/`file`
   membuat halaman company galat karena stancl/tenancy butuh cache bertag.
3. Database belum ada: buat `wms_central` dan `wms_central_test` dengan `C:\xampp3\mysql\bin\mysql.exe`, lalu
   ikuti 00-setup-lokal §3 (migrate, ProductionSeeder, PlatformDemoSeeder). Bila sudah ada: `php artisan migrate`
   lalu `php artisan tenants:migrate`.
4. `php artisan tenants:seed --tenants=<id DEMO> --class="Database\Seeders\Tenant\DemoSeeder"`
   (`--tenants` menerima id company, bukan kode).
5. `php artisan test` — semua harus hijau. Kode tidak diubah demi MariaDB (A-76).
6. `py -3 docs/diagram/_verify.py` → HASIL: OK.
7. Baris hosts (Administrator): `127.0.0.1 wms.test`, `demo.wms.test`, `auth.wms.test`. Jalankan
   `php artisan serve --host=127.0.0.1 --port=8000`, lalu `node tests/e2e/ui-check.mjs` (0 error console) dan
   `MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe node tests/e2e/alur-req-sj.mjs` pada data demo yang baru di-seed.
Laporkan hasil langkah 0 sebelum lanjut.

## Cara kerja (sama dengan sesi kantor)
- Satu modul per giliran, berurutan. Per modul: spesifikasi docs/wms/<nn>-<modul>.md dari template
  (format contoh: 23-pemakaian.md) → migrasi tenant → domain app/Domain/<Modul> (satu Action per permission)
  → permission & role di ReferenceSeeder + hitungan per modul di DemoSeederTest → rute GET, layar Livewire,
  sidebar + palet Ctrl+K → uji tests/Feature/<Modul> untuk setiap TC **plus satu uji rantai lintas modul**
  → dokumen (ERD via docs/diagram/_generate_erd.py, Katalog enum saja, asumsi baru, README changelog,
  laporan progres §4, cek.md).
- Boleh mendelegasikan satu modul ke satu subagen, lalu VERIFIKASI SENDIRI sesudahnya: `php artisan test`,
  `_verify.py`, `tenants:migrate`, `npm run build`, dan `node tests/e2e/ui-check.mjs`.
- Larangan keras CLAUDE.md: tanpa harga/uang, tanpa hapus fisik, stok hanya lewat
  app/Domain/Stock/Support/StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Keputusan yang tidak tertulis di dokumen: ambil pilihan paling sesuai dokumen, catat sebagai asumsi
  "Perlu validasi". Jangan berhenti untuk bertanya kecuali benar-benar keputusan bisnis besar.
- Jangan commit kecuali saya minta. Jika sesi masuk plan mode di tengah jalan, tulis status ke berkas rencana.

## Jatah nomor (dikoordinasikan dengan sesi modul Template — patuhi)
- Asumsi berikutnya: **A-206, A-207, …** (A-120–A-149 milik modul Template; A-153–A-162 Konversi & Waste; A-163–A-169 Aset; A-170–A-175 Purchase Request; A-176–A-184 Platform; A-185–A-194 Pendukung F1 & penutup; A-195–A-205 tinjauan kode, 2FA Super Admin, pindai, pengingat tagihan, petik ulang sisa, pengetatan 2FA).
- Versi berikutnya: README 0.36→0.37, 04-keputusan 0.26→0.27, 06-katalog 0.17→0.18, model data 0.19→0.20.

## Urutan sisa pekerjaan
1. ~~**Konversi & Waste**~~ — **selesai 24 Sep 2026** (24-konversi-waste.md v0.2, 513 uji hijau). Catatan lama: spesifikasi 24-konversi-waste.md, migrasi 000130. Mulai dari branch
   `wip/konversi-waste`: gabungkan ke main (`git merge wip/konversi-waste`), baca
   docs/wip-konversi-waste-rencana.md (rancangan + A-153–A-161 + sisa langkah), selesaikan, lalu hapus berkas
   rencana WIP itu. Catatan dari rancangan: tiga uji lama perlu disesuaikan saat handler/print type ada
   (TC-APR-17 dan TC-TPL-04 memakai `waste_disposal` sebagai contoh tipe yang belum tersambung; TC-ACC-27b
   menghitung permission per modul). Kolom Waste/Dikonversi masuk laporan Material per proyek.
2. ~~**Aset dipinjamkan**~~ — **selesai 24 Sep 2026** (25-aset.md v0.2). Catatan lama: 25-aset.md, migrasi 000140. BR-AST-01–08, KS §2.11, A-66; lengkapi
   stub pemeriksaan aset saat retur (A-116, 22-retur-transfer §13); BA serah terima via modul Template.
3. ~~**Purchase Request**~~ — **selesai 25 Sep 2026** (26-purchase-request.md v0.1, 537 uji hijau). Catatan lama: 26-purchase-request.md, migrasi 000150. KS §2.15, BR-REQ-11 (titik pesan ulang,
   job harian), A-47/A-51–A-53; sambungkan baris REQ bersumber `purchase` (BR-REQ-05) dan handler approval PRQ.
4. ~~**Platform penuh**~~ — **selesai 25 Sep 2026** (17-platform-login.md v0.2, 546 uji hijau; migrasi pusat 000020, tanpa tabel tenant baru). Catatan lama: lanjutan 17-platform-login.md, migrasi 000160. `CreateTenant` + seed acuan otomatis
   (TenancyServiceProvider belum menyeed), tagihan/verifikasi transfer manual, trial, suspend/terminate sesuai
   BR-SUB; tutup temuan 17 §13 (terminated terlalu longgar, TenantDeleted menghapus DB, login Super Admin
   tanpa penguncian).
5. ~~**Pendukung F1**~~ — **selesai 25 Sep 2026** (27-pendukung-f1.md v0.3; impor Excel item & proyek + klien; tinjauan kode malam 25 Sep: A-195–A-199; 2FA Super Admin A-200, pindai A-201 & A-203, pengingat tagihan A-202). Catatan lama: strategi FEFO/FIFO/potongan terdekat di CreatePickTask; notifikasi in-app & email;
   laporan §9 yang tersisa + ekspor PDF; wizard setup awal company; impor Excel master; PWA installable +
   scan kamera + draf lokal; Beranda dinamis (kartu "Modul berikutnya" masih basi); guard penutupan proyek
   BR-PRJ-02; konfirmasi/keberatan terima pemohon BR-REQ-10 (lalu tinjau ulang A-77).
   (Template dokumen & label SUDAH selesai — jangan dibangun ulang.)
6. ~~**Penutup**~~ — **selesai 25 Sep 2026** (uji rantai penuh `tests/Feature/FullLifecycleTest` TC-E2E-01, `tests/e2e/alur-pendukung.mjs` P1–P7, `ui-check.mjs` + layar Super Admin, [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md)). Catatan lama: perluas tests/e2e menjadi alur panjang: GRN → QC → put-away → REQ → approval → PCK → SJ →
   terima → ISU → konversi pipa → retur sisa → opname → penyesuaian → aset pinjam & kembali; perbarui laporan
   progres sampai semua butir Fase 1 selesai (atau stub yang disengaja); tabel ringkas semua asumsi
   "Perlu validasi" untuk saya tinjau.

Setiap modul selesai: laporkan singkat (jumlah uji, halaman baru, asumsi baru), lalu lanjut ke berikutnya
tanpa menunggu saya.
```

## 3. Catatan untuk manusia

- Mesin kantor: XAMPP + MariaDB 10.4, `php artisan serve` port 8000 ([00-setup-lokal](../00-setup-lokal.md)). Mesin rumah: XAMPP3 (`C:\xampp3`, PHP 8.3.33 = `php`, MariaDB 10.4.32), juga `php artisan serve` port 8000 karena Apache XAMPP3 memegang port 80. Laragon lama (`C:\laragon\www\wms`) tidak dipakai lagi.
- `phpunit.xml` memakai `memory_limit=512M` karena render PDF (dompdf) di suite penuh.
- Worktree `C:\xampp\htdocs\wms-template` (branch `feat/template-label`) hanya ada di mesin kantor dan sudah di-merge; boleh dihapus (`git worktree remove`).
