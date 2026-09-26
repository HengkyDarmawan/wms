# Prompt: melanjutkan WMS di rumah (XAMPP3)

**Versi:** 2.4
**Tanggal:** 26 September 2026
**Status:** aktif — serah terima sesi kantor 26 Sep 2026 (pagi: keputusan pemilik produk atas 31 asumsi ⚠ + tujuh tugas turunannya; sore: kompresi foto otomatis, impor struktur gudang, lalu — bila sempat — tinjauan asumsi sisa dan rapikan dokumen) ke sesi rumah. v2.1 (arsip) adalah serah terima kantor → rumah 25 Sep; arah sebaliknya: [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md) v2.1 (arsip); v2.3: nomor setelah A-257/A-258; v2.4: pekerjaan sore masuk §1/§2, php.ini batas unggah di Langkah 0, jatah nomor & angka uji dicek dari dokumen, urutan kerja dimulai dari tindak lanjut *Ubah*
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [Asumsi lanjutan](../wms/04b-asumsi-lanjutan.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: di rumah nyalakan MariaDB dari XAMPP3 Control Panel, `git pull`, buka Claude Code di `C:\xampp3\htdocs\wms`, lalu tempel **seluruh blok prompt di §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (26 Sep 2026, sore)

| Hal | Keadaan |
|---|---|
| Branch `main` | Pagi: `7cabe81` (sudah di-push). Pekerjaan sore di-commit & di-push sebelum pulang — lihat `git log -3` |
| Uji | Pagi 654 / 7.581; setelah kompresi foto **659 / 7.635**; impor struktur gudang menambah TC-WH-26–26d. Angka pasti = bagian terakhir [laporan progres §5](../00-laporan-progres-2026-09-24.md). `_verify.py` OK; `npm run build` OK |
| Migrasi baru | Pagi: tenant `000250_add_over_order_to_purchase_order_lines`, `000260_add_pickup_columns_to_shipments`, `000270_add_asset_onsite_transfer_columns`, `000280_add_layout_columns_to_warehouse_tables`. **Sore: tidak ada migrasi baru**; berkas baru `config/livewire.php` (batas unggah sementara 20 MB). Tidak ada migrasi pusat baru |
| Keputusan pemilik | Pagi: 31 asumsi ⚠ diputus (28 Setuju, A-229 sebagian, A-210 diubah); A-111 & A-116 diubah → dibangun. Sore: tinjauan asumsi tanpa ⚠ mungkin sudah dimulai — **cek kolom *Keputusan*** di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) dan daftar tindak lanjut *Ubah* di bagian akhirnya |
| Selesai pagi | PO lebih dari PRQ (A-246); SJ tanpa PCK & SJ jemput retur (A-247, A-248); transfer aset On-site antar proyek, pindahan sisa proyek, jejak lokasi, kirim tautan via WA (A-249–A-251); dokumen terkait & linimasa proyek (A-252); CNV potong banyak batang (A-253); denah gudang 2D, rak area, bin ikut terpakai, tanggal masuk FIFO (A-254–A-256) |
| Selesai sore | **Kompresi foto otomatis** ([A-23](../wms/04-keputusan-dan-asumsi.md#a-23), [A-257](../wms/04b-asumsi-lanjutan.md#a-257)): `ImageCompressor` di `StoreUpload`, semua jalur foto; mentah ≤ 20 MB → tersimpan ≤ 5 MB, 1920 px, JPEG 80; PNG transparan/tanda tangan/logo tetap PNG; PDF utuh (TC-FIL-03–03e). **Impor struktur gudang** ([A-258](../wms/04b-asumsi-lanjutan.md#a-258)): kartu *Struktur gudang* di `/imports`, zona–rak–level–bin untuk gudang yang sudah ada, semua-atau-tidak, kode ganda ditolak (TC-WH-26–26d). Rapikan dokumen 16 §2/§12 + status README: cek README changelog apakah sudah dikerjakan |
| Sisa lain | Cross-dock (A-83); pembuatan gudang lewat impor (O-12 — zona s.d. bin sudah, A-258); OTP otomatis (O-15); isi balik `from_stock_status` (A-194); tambah/ubah zona-rak-level langsung dari denah (kini lewat tab *Zona & rak*); php.ini kantor masih `upload_max_filesize = 5M` |

## 2. Prompt (tempel utuh ke Claude Code)

```
Lanjutkan pekerjaan WMS di mesin rumah (XAMPP3 di C:\xampp3, repo C:\xampp3\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp3\php\php.exe; MariaDB 10.4.32, mysql di
C:\xampp3\mysql\bin\mysql.exe, root tanpa password; web `php artisan serve --host=127.0.0.1 --port=8000`;
Node 22, Composer 2.8, `py -3`. Laragon TIDAK dipakai).
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-rumah.md,
docs/00-laporan-progres-2026-09-24.md (§4, §5.8 dan sesudahnya), docs/00-tinjauan-asumsi-2026-09-25.md,
docs/wms/04b-asumsi-lanjutan.md (A-246 dst.), dan 5 blok teratas changelog docs/README.md.
Periksa `git log -5` untuk tahu apa yang dikerjakan sesi kantor sore.

## Langkah 0 — siapkan lingkungan setelah git pull (jangan lewati)
1. Pastikan MariaDB XAMPP3 berjalan. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, `CACHE_STORE=array`, DB `127.0.0.1:3306` root tanpa password.
3. php.ini XAMPP3 (C:\xampp3\php\php.ini): kompresi foto (A-257) menerima unggahan mentah sampai 20 MB,
   jadi perlu `upload_max_filesize` ≥ 20M dan `post_max_size` ≥ 25M (docs/00-setup-lokal.md, batas unggah
   PHP). Periksa nilainya; TANYAKAN saya sebelum mengubah php.ini.
4. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate` (tenant sampai 000280).
5. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`.
6. `php artisan test` — semua hijau; jumlahnya harus sama dengan angka verifikasi di bagian terakhir
   laporan progres §5 (≥ 659). Kode tidak diubah demi MariaDB (A-76).
7. `py -3 docs/diagram/_verify.py` → HASIL: OK.
8. `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console; termasuk
   denah gudang, pindahan proyek, dokumen terkait, kartu impor struktur gudang),
   `node tests/e2e/alur-req-sj.mjs` (10/10), `node tests/e2e/alur-pendukung.mjs` (9/9) — berurutan pada
   demo segar; periksa baris LULUS/GAGAL, bukan kode keluar. Tunggu berdasarkan kondisi, bukan tidur tetap.
9. `php artisan stock:reconcile --tenants=1` → "saldo cocok dengan kartu stok".
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
- SELALU cek ulang di dokumen sebelum memakai nomor; bila berbeda dengan daftar ini, dokumen yang menang.
- Asumsi berikutnya: **A-259, A-260, …** (A-246–A-258 dipakai sesi kantor 26 Sep; bisa bertambah bila
  tinjauan asumsi sore menambah — lihat nomor terakhir di 04b).
- TC berikutnya: TC-PO-12, TC-RET-22, TC-SJ-20, TC-AST-17, TC-TRF-21, TC-DOC-04, TC-CNV-17, TC-WH-27,
  TC-RPT-11, TC-STK-36, TC-GRN-22, TC-ADJ-13, TC-NTF-12, TC-OPN-22, TC-ISU-19, TC-FIL-04.
- Versi berikutnya: ambil dari kepala tiap berkas (+1 di digit terakhir). Saat v2.4 ditulis: README 0.53,
  04 0.40, 04b 0.3, 06-katalog 0.21, 05 0.11, model data 0.22, laporan progres 1.23, tinjauan asumsi 1.13,
  arsip changelog 1.6 — sesi kantor sore mungkin sudah menaikkan sebagian.

## Yang SUDAH selesai (jangan dibangun ulang)
Semua 22 spesifikasi Fase 1 + Sisa Fase 1 2a–2f; sesi kantor 26 Sep pagi: PO lebih dari PRQ beralasan;
SJ tanpa PCK (SJ jemput retur, SJ antar site) dengan sopir & plat; transfer aset On-site antar proyek (TRF aset,
AST `transferred`, `asset_transferred`), pindahan sisa proyek dari hub, jejak lokasi aset; dokumen terkait di
14 layar detail + linimasa dokumen proyek; CNV potong banyak batang; denah gudang 2D (/warehouses/{id}/layout),
rak area, bin ikut terpakai, tanggal masuk FIFO, ubah bin di /bins. Sesi kantor 26 Sep sore: kompresi foto
otomatis (ImageCompressor di StoreUpload, A-257); impor struktur gudang (ImportWarehouseStructure, /imports,
A-258); rapikan dokumen 16/README bila tercatat di changelog.

## Urutan pekerjaan
1. **Tindak lanjut *Ubah*** dari tinjauan asumsi (baris berkeputusan `Ubah: …` di
   docs/00-tinjauan-asumsi-2026-09-25.md yang belum punya asumsi pengganti / belum dibangun): ubah kode + uji
   + dokumen; perubahan substantif = asumsi baru di 04b, isi *Diganti oleh* di asumsi lama. Baris `Hapus` →
   buang perilaku + uji. Bila tidak ada, lewati.
2. **Asumsi yang kolom *Keputusan*-nya masih kosong**: TANYAKAN saya dulu — sajikan 10–12 per putaran dalam
   bahasa sehari-hari (arti + contoh nyata di gudang/proyek, akibat bila ditolak, rekomendasimu), bernomor
   supaya saya bisa menjawab "1-8 setuju, 9 ubah: …". Setelah saya jawab, catat di kolom *Keputusan* tinjauan
   dan kolom *Validasi* 04/04b; *Ubah* dikerjakan seperti butir 1.
3. **Sisa kecil** (hanya bila saya minta): utang dokumen 16 §2/§12 bila belum; cross-dock (A-83); pembuatan
   gudang lewat impor (O-12); OTP otomatis (O-15); zona-rak-level dari denah; `from_stock_status` (A-194).
4. **Akuntansi**, **kejadian HTTP ke sistem luar [F3]**, dan semua **[F2]** (WhatsApp API, offline penuh,
   dll.) — hanya bila saya minta.

Setiap tugas selesai: laporkan singkat (jumlah uji, layar baru, asumsi baru, cara saya review di browser),
lalu lanjut ke berikutnya tanpa menunggu saya — kecuali butir 2 yang memang menunggu jawaban saya.
```

## 3. Catatan untuk manusia

- Profil mesin rumah di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin). Nyalakan MariaDB dari XAMPP3 Control Panel sebelum mulai (di kantor MariaDB sempat mati saat sesi dimulai).
- Naikkan batas unggah php.ini XAMPP3 (Langkah 0 butir 3) agar foto HP besar bisa diuji; di kantor php.ini masih `5M`, jadi uji manual foto > 5 MB belum bisa di kantor.
- Coba di browser demo:
  - Pagi: RET dijemput → SJ jemput (detail RET); hub proyek → *Pindahkan ke proyek lain*; gudang → *Denah gudang* → *Atur denah*; konversi Potong → *Salin pola batang 1*.
  - Sore: unggah foto HP besar di bukti terima atau foto item (hasil ≤ 5 MB); `/imports` → kartu *Struktur gudang* → unduh templat, isi beberapa baris, unggah, lalu cek di denah gudang.
- Asumsi yang belum diputus bisa diisi sendiri di [tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md), atau minta Claude menyajikannya per kelompok (Urutan pekerjaan butir 2).
- Sebelum kembali ke kantor: minta Claude Code meng-commit & mem-push, lalu perbarui prompt arah rumah → kantor ([00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md)).
