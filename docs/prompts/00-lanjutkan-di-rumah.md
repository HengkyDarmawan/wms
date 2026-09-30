# Prompt: melanjutkan WMS di rumah (XAMPP3)

**Versi:** 3.0
**Tanggal:** 30 September 2026
**Status:** aktif — v3.0 ditulis ulang setelah sesi kantor 30 Sep 2026 dengan pola yang sama dengan [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md) v3.0: **sinkron & verifikasi → pekerjaan tanpa keputusan → daftar keputusan untuk pemilik**; data DEMO **tidak pernah** di-fresh/seed. Riwayat v2.x (penutupan Fase 1, Fase 2a) di git
**Dokumen terkait:** [README](../README.md) · [Laporan malam](../00-laporan-malam-2026-10-01.md) · [Setup lokal](../00-setup-lokal.md) · [Asumsi lanjutan](../wms/04b-asumsi-lanjutan.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: nyalakan MySQL di **XAMPP3 Control Panel** (`C:\xampp3`), buka Claude Code di `C:\xampp3\htdocs\wms`, tempel **seluruh blok §2**. Bagian §1 dan §3 untuk dibaca manusia.

---

## 1. Keadaan saat serah terima (30 Sep 2026)

| Hal | Keadaan |
|---|---|
| Git | Sesi kantor 30 Sep menambah dua commit di atas kerja malam (`<x-pilih>` empat pilihan terakhir; pengingat copot label A-362) — sudah di-push |
| Uji | 923+ hijau di XAMPP kantor; `_verify.py` OK; build OK. E2E tidak dijalankan |
| Data DEMO rumah | Isinya dari kerja malam (data demo + tata letak yang dicoba). Tidak di-fresh/seed tanpa izin pemilik |
| Menunggu pemilik | Daftar keputusan sesi kantor 30 Sep (asumsi terpenting A-355–A-398, A-307, A-308, T-01 portal Klien membuat REQ) |
| Jam mesin | Dokumen kerja malam bertanggal 1 Okt padahal kantor 30 Sep — periksa jam Windows di mesin rumah |

## 2. Prompt (tempel utuh ke Claude Code)

```
Mesin ini = profil RUMAH (CLAUDE.md & docs/00-setup-lokal.md): XAMPP3 di C:\xampp3, repo C:\xampp3\htdocs\wms,
PHP 8.3.33 = C:\xampp3\php\php.exe (`php` di PATH), MariaDB 10.4.32, mysql C:\xampp3\mysql\bin\mysql.exe (root
tanpa password), web `php artisan serve --host=127.0.0.1 --port=8000` (http://demo.wms.test:8000; Apache XAMPP3
memegang port 80). Laragon TIDAK dipakai. Generator dokumen: `py -3`.
Baca dulu: CLAUDE.md, docs/README.md (5 blok changelog teratas), docs/prompts/00-lanjutkan-di-rumah.md §1,
dan docs/wms/04b-asumsi-lanjutan.md bagian terakhir.

TAHAP 1 — SINKRON & VERIFIKASI (laporkan singkat, lalu lanjut)
1. `git status` harus bersih. Ada perubahan lokal → JANGAN dibuang; laporkan daftarnya dan BERHENTI.
2. `git pull origin main`; laporkan commit teratas.
3. `composer install`, `npm install`, `npm run build`, `php artisan migrate`, `php artisan tenants:migrate`.
   DILARANG `migrate:fresh`, `tenants:migrate-fresh`, `db:wipe`, atau seeder ke tenant DEMO kecuali saya minta
   eksplisit. Migrasi gagal karena data lama → laporkan & BERHENTI.
4. `php artisan test` penuh (satu proses) — harus hijau semua. Merah KHUSUS lingkungan rumah (MariaDB 10.4.32 /
   Windows / PHP) → perbaiki akar masalahnya tanpa melemahkan tes; selain itu laporkan & BERHENTI.
5. Cek port 8000 dulu; jalankan server bila belum. Cek cepat di browser TANPA menyimpan: layar yang disentuh
   commit terbaru (baca changelog). Catat galat JS/tampilan.

TAHAP 2 — PEKERJAAN TANPA KEPUTUSAN BARU (yang saya sebut; commit & push per poin)
Per poin: tes terkait + `php artisan test` penuh hijau, `npm run build`, Pint HANYA berkas yang diubah, dokumen
(spesifikasi, 04b, README changelog menyebut ID), commit, `git pull --rebase`, push. Jangan push bila ada tes merah.
Jangan mengedit kode aplikasi saat suite berjalan.

TAHAP 3 — DAFTAR KEPUTUSAN UNTUK PEMILIK (JANGAN ubah kode). Bahasa sederhana; tiap butir: pertanyaan · pilihan
· saranmu. Lalu BERHENTI.

Aturan tetap: larangan keras CLAUDE.md (tanpa harga di luar app/Domain/Purchasing, tanpa hapus fisik data yang
dipakai, stok hanya lewat StockLedger, status hanya dari Katalog, tanpa transisi lewat GET); satu file dokumen
≤ ±450 baris; nomor A-xx/TC berikutnya dicek dari dokumen; jangan menyentuh database di luar prefiks `wms_`.
Bila `git push` ditolak pengaman izin: laporkan, jangan mencari jalan pintas.
```

## 3. Catatan untuk manusia

- Sebelum kembali ke kantor: pastikan Claude melaporkan **push berhasil** dan `git status` bersih; di kantor jalankan
  prompt [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md).
- Ingin data DEMO kosong untuk latihan: minta Claude **secara eksplisit** memakai `BlankDemoSeeder`
  ([akun uji §6](../00-akun-uji.md)).
- E2E (`tests/e2e/*.mjs`) mengisi ulang data DEMO; jalankan hanya bila Anda mengizinkan data DEMO diganti.
