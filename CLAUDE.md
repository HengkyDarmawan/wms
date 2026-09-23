# WMS Proyek — konteks untuk agen AI

Repo ini **belum berisi kode**. Isinya dokumentasi desain WMS SaaS multi-company (Bahasa Indonesia) yang menjadi sumber kebenaran untuk BPMN, ERD, spesifikasi modul, dan implementasi Laravel 13 nanti.

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
- **Larangan keras di kode WMS:** harga/nilai uang (D-07) · hapus fisik data yang sudah dipakai (P-03) · mengubah stok di luar `stock_movement` (P-01) · status/istilah di luar katalog & glosarium · transisi status lewat GET.
- **Mengubah dokumen:** naikkan `Versi` di kepala file, tambahkan baris di *Catatan perubahan* `docs/README.md` yang menyebut **ID** yang berubah (bukan narasi). Keputusan tidak diedit; buat keputusan baru dan isi *Diganti oleh*. Perubahan substantif yang belum disetujui pemilik produk dicatat sebagai asumsi baru `A-xx` berstatus *Perlu validasi*.
- **Ukuran:** satu file ≤ ±450 baris; spesifikasi modul memakai `docs/wms/_template-spesifikasi-modul.md`.
- **Diagram:** `.drawio` selalu didampingi bentuk teks (Mermaid `erDiagram` / tabel transisi) agar bisa dibaca agen.

## Lingkungan lokal

- XAMPP di `C:\xampp`; Apache memakai PHP 8.3, `php` di terminal masih 7.4 — pakai `C:\xampp\php-8.3\php.exe` (lihat memori proyek).
- Belum ada `composer.json`; jangan membuat kerangka Laravel sebelum Part 3 selesai dan diminta.
