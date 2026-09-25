# WMS Proyek — konteks untuk agen AI

Repo ini berisi **dokumentasi desain** WMS SaaS multi-company (Bahasa Indonesia) di `docs/` — sumber kebenaran untuk BPMN, ERD, dan spesifikasi modul — **dan aplikasi Laravel 13** yang dibangun mengikuti dokumen itu, modul demi modul. Dokumen selalu menang: bila kode menyimpang, perbarui dokumen modulnya (naikkan versi + changelog `docs/README.md`), jangan diam-diam mengubah keputusan.

## Urutan baca (wajib sebelum mengerjakan apa pun)

1. `docs/README.md` — struktur, status, konvensi.
2. `docs/wms/03-glosarium.md` — istilah UI ↔ nama di kode. **Jangan mengarang istilah.**
3. `docs/wms/06-katalog-status-dan-enum.md` — satu-satunya sumber status & enum. **Jangan menambah status.**
4. `docs/wms/05-aturan-bisnis.md` — aturan `BR-xx`, relasi dokumen, matriks kejadian stok.
5. `docs/wms/01-blueprint.md` — gambaran produk; baca bagian yang relevan dengan tugas.
6. `docs/wms/04-keputusan-dan-asumsi.md` — cek apakah asumsi yang tugasmu andalkan sudah *Setuju* atau masih *Perlu validasi*.

## Aturan kerja

- **Bahasa:** dokumen & UI Bahasa Indonesia; nama kode Inggris `snake_case` (tabel) / `PascalCase` (model) sesuai glosarium.
- **ID stabil:** `D-xx` keputusan · `A-xx` asumsi · `O-xx` isu · `P-xx` prinsip · `NFR-xx` · `BR-<AREA>-nn` aturan · `TC-<MOD>-nn` kasus uji. Rujuk dengan link relatif beranchor, mis. `[A-30](docs/wms/04-keputusan-dan-asumsi.md#a-30)`.
- **Tag fase** `[F1]` `[F2]` `[F3]` menentukan apa yang dibangun sekarang; `[F2]`/`[F3]` dibangun sebagai stub (BR-GEN-10).
- **Larangan keras di kode WMS:** harga/nilai uang (D-07; satu-satunya pengecualian domain `app/Domain/Purchasing` — PO & harga beli vendor, D-28/D-29, A-208; layar, cetak, dan kejadian WMS tetap tanpa harga) · hapus fisik data yang sudah dipakai (P-03) · mengubah stok di luar `stock_movement` (P-01) · status/istilah di luar katalog & glosarium · transisi status lewat GET.
- **Mengubah dokumen:** naikkan `Versi` di kepala file, tambahkan baris di *Catatan perubahan* `docs/README.md` yang menyebut **ID** yang berubah (bukan narasi). Keputusan tidak diedit; buat keputusan baru dan isi *Diganti oleh*. Perubahan substantif yang belum disetujui pemilik produk dicatat sebagai asumsi baru `A-xx` berstatus *Perlu validasi*.
- **Ukuran:** satu file ≤ ±450 baris; spesifikasi modul memakai `docs/wms/_template-spesifikasi-modul.md`.
- **Diagram:** `.drawio` selalu didampingi bentuk teks (Mermaid `erDiagram` / tabel transisi) agar bisa dibaca agen.

## Lingkungan lokal

- **Dua profil mesin**, langkah lengkap di `docs/00-setup-lokal.md`. **Kantor = XAMPP:** PHP 8.3.33 di `C:\xampp\php-8.3.33` (sudah `php` di PATH; `C:\xampp\php\php.exe` 7.4 **tidak boleh dipakai**), MariaDB 10.4.27 ([A-76](docs/wms/04-keputusan-dan-asumsi.md#a-76)), web via `php artisan serve --port=8000` (`demo.wms.test:8000`, baris hosts manual). Di mesin kantor, baca `php83` di bawah sebagai `php`. `CACHE_STORE` wajib `array`/`redis` (stancl butuh cache bertag).
- **Rumah = XAMPP3** di `C:\xampp3` (repo `C:\xampp3\htdocs\wms`): PHP **8.3.33** = `C:\xampp3\php\php.exe` (sudah `php` di PATH), MariaDB 10.4.32 (A-76), Composer 2.8, Node 22; web via `php artisan serve --port=8000` (Apache XAMPP3 memegang port 80), baris hosts manual. Laragon (`C:\laragon`, klon lama) **tidak dipakai lagi**. MySQL **8.4 LTS** tetap standar produksi. Generator dokumen: `py -3`. Di kedua mesin, baca `php83` di bawah sebagai `php`.
- Aplikasi Laravel ada di root repo (`app/`, `routes/`, `resources/`, `database/`). Semua perintah PHP memakai `php83`:
  `php83 artisan migrate` (pusat) · `php83 artisan tenants:migrate` (semua company) · `php83 artisan db:seed` (pusat + company DEMO) ·
  `php83 artisan tenants:seed --class="Database\Seeders\Tenant\DemoSeeder"` · `php83 artisan test` · `npm run build`.
- Database: pusat `wms_central`, tenant `wms_tenant_<kode>`, uji `wms_central_test` / `wms_tenant_test_*`. MySQL lokal dipakai bersama proyek lain — **jangan menyentuh database di luar prefiks `wms_`**.
- Akun seed/demo hanya dari `docs/00-akun-uji.md`; seeder harus sama persis dengan berkas itu.
- `template/` adalah referensi UI NexaDash (statis), **tidak di-deploy**; aset yang dipakai sudah disalin ke `resources/` dan di-bundle Vite tanpa CDN.
- Struktur kode per domain: `app/Domain/<Modul>/{Models,Enums,Actions,Policies,Support,Notifications}` (AD-02); satu aksi = satu kelas Action bernama sesuai permission.
