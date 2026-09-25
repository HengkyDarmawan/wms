# Prompt: melanjutkan pekerjaan di kantor (XAMPP)

**Versi:** 1.0
**Tanggal:** 25 September 2026
**Status:** aktif — serah terima dari sesi rumah (XAMPP3) ke sesi kantor (XAMPP + MariaDB 10.4). Arah sebaliknya: [00-lanjutkan-di-rumah.md](00-lanjutkan-di-rumah.md) (arsip)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: setelah `git pull`, buka Claude Code di root repo lalu tempel **seluruh blok prompt di §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (25 Sep 2026, pagi)

| Hal | Keadaan |
|---|---|
| Branch `main` | Pekerjaan sesi rumah dibangun di atas `8a023b7`. **Harus di-commit dan di-push dari rumah dulu**; tanpa itu kantor hanya melihat keadaan lama |
| Fase 1 | Semua modul selesai: Access, Master, Warehouse, Stock, Request, Picking/Shipment, Receipt/Putaway, Approval, Count/Adjustment, Return/Transfer, Template, Issue, Konversi & Waste, Aset, Purchase Request, Platform penuh, Pendukung F1, dan penutup |
| Uji | **579 hijau** (MariaDB 10.4.32, XAMPP3). E2E pada demo segar: `alur-req-sj` 9/9, `alur-pendukung` 7/7, `ui-check` 67 cek termasuk layar Super Admin, 0 error console |
| Migrasi baru sejak `8a023b7` | Pusat `000020` (tagihan, login attempt, audit pusat), `000030` (nonce tautan akses dukungan), `000040`/`000050` (2FA Super Admin). Tenant `000130`–`000180` (konversi, aset, PRQ, notifikasi, `from_stock_status`, `two_factor_last_step`) |
| Asumsi menunggu validasi | A-72–A-126 dan A-150–A-205. Daftar kerja pemilik produk: [00-tinjauan-asumsi-2026-09-25.md](../00-tinjauan-asumsi-2026-09-25.md) (111 asumsi, ⚠ = paling berdampak) |
| Tinjauan kode 25 Sep | Dua putaran, semua temuan diperbaiki dengan uji — tabel di [laporan progres §5.4](../00-laporan-progres-2026-09-24.md) |
| Sisa yang disengaja | Pindai di form REQ/ISU; impor Excel vendor & saldo awal; isi balik `from_stock_status` baris lama (A-194); WhatsApp `[F2]`; landing page (Part 5) |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pekerjaan WMS di mesin kantor (XAMPP di C:\xampp, repo C:\xampp\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp\php-8.3.33 — JANGAN pakai C:\xampp\php\php.exe (7.4);
MariaDB 10.4.27, mysql di C:\xampp\mysql\bin\mysql.exe, root tanpa password;
web `php artisan serve --host=127.0.0.1 --port=8000`; Node 24, Composer 2.9).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-kantor.md,
docs/00-laporan-progres-2026-09-24.md (§4, §5.4), docs/00-tinjauan-asumsi-2026-09-25.md, dan
docs/wms/04-keputusan-dan-asumsi.md §2.12–§2.14.

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. `composer install`, `npm install`, `npm run build`.
2. `.env`: pastikan `APP_URL=http://wms.test:8000` dan `CACHE_STORE=array` (stancl/tenancy butuh cache bertag).
3. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate`.
4. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`
   (`--tenants` menerima id company, bukan kode).
5. `php artisan test` — harus 579 hijau. Kode tidak diubah demi MariaDB (A-76).
6. `py -3 docs/diagram/_verify.py` → HASIL: OK.
7. Jalankan `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console),
   `node tests/e2e/alur-req-sj.mjs` (9/9), `node tests/e2e/alur-pendukung.mjs` (7/7) — dua yang terakhir
   hanya lulus penuh pada demo yang baru di-seed.
Laporkan hasil langkah 0 sebelum lanjut.

## Cara kerja
- Satu tugas per giliran, berurutan. Perubahan fitur mengikuti pola modul: spesifikasi docs/wms/<nn>-*.md →
  migrasi → domain app/Domain/<Modul> (satu Action per permission) → rute GET/POST, Livewire, sidebar + Ctrl+K
  → uji tests/Feature/<Modul> per TC → dokumen (ERD via docs/diagram/_generate_erd.py, asumsi, README
  changelog, laporan progres, cek.md).
- Larangan keras CLAUDE.md: tanpa harga/uang, tanpa hapus fisik, stok hanya lewat
  app/Domain/Stock/Support/StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Pint HANYA pada berkas yang diubah (`vendor/bin/pint <berkas…>`), jangan per direktori.
- Boleh mendelegasikan ke subagen, lalu VERIFIKASI SENDIRI: `php artisan test`, `_verify.py`,
  `tenants:migrate`, `npm run build`, `node tests/e2e/ui-check.mjs`. Jangan jalankan dua suite uji
  bersamaan — database uji dipakai bersama.
- Keputusan yang tidak tertulis: ambil pilihan paling sesuai dokumen, catat sebagai asumsi "Perlu validasi".
- Jangan commit kecuali saya minta. Jangan menyentuh database di luar prefiks `wms_`.

## Jatah nomor
- Asumsi berikutnya: **A-206, A-207, …**
- Versi berikutnya: README 0.36→0.37, 04-keputusan 0.26→0.27, 06-katalog 0.17→0.18, model data 0.19→0.20,
  laporan progres 1.13→1.14, tinjauan asumsi 1.1→1.2.

## Urutan pekerjaan
1. **Terapkan keputusan pemilik produk** dari kolom *Keputusan* di docs/00-tinjauan-asumsi-2026-09-25.md:
   - `Setuju` → isi kolom *Validasi* asumsi itu di 04 (tanpa mengubah kode);
   - `Ubah: …` → ubah kode + uji + dokumen modulnya; perubahan substantif = asumsi/keputusan baru sesuai
     aturan CLAUDE.md (keputusan lama tidak diedit, isi *Diganti oleh*);
   - `Hapus` → buang perilakunya beserta ujinya, catat di changelog.
   Kerjakan yang bertanda ⚠ lebih dulu. Bila kolom *Keputusan* masih kosong, TANYAKAN saya dulu — jangan menebak.
2. **Sisa Fase 1 yang disengaja belum** (hanya setelah butir 1 atau bila saya minta):
   pindai di form REQ/ISU (A-201/A-203 sebagai pola); impor Excel vendor & saldo awal (pola
   app/Domain/Master/Support/ExcelRows + ImportBatch, A-192); isi balik `stock_movements.from_stock_status`
   bila sudah ada data produksi (A-194).
3. **Part 5 landing page** — hanya bila saya minta.

Setiap tugas selesai: laporkan singkat (jumlah uji, layar baru, asumsi baru), lalu lanjut ke berikutnya.
```

## 3. Catatan untuk manusia

- **Sebelum meninggalkan rumah:** minta Claude Code meng-commit dan mem-push pekerjaan sesi 24–25 Sep (belum ada commit sejak `8a023b7`).
- Profil mesin kantor lengkap di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin). Baris hosts `wms.test`, `demo.wms.test`, `auth.wms.test` sudah ada di kantor.
- Isi kolom *Keputusan* di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) sebelum sesi kantor dimulai supaya butir 1 bisa langsung dikerjakan.
- Worktree `C:\xampp\htdocs\wms-template` (branch `feat/template-label`) sudah di-merge; boleh dihapus (`git worktree remove`).
- `phpunit.xml` memakai `memory_limit=512M` karena render PDF (dompdf) di suite penuh; suite penuh ±4–5 menit.
