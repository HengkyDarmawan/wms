# Prompt: melanjutkan audit & validasi dokumentasi WMS di sesi baru

**Cara pakai:** buka Claude Code di folder proyek (`wms/`, yang berisi `CLAUDE.md` dan `docs/`), lalu tempel prompt di bawah. Pastikan folder disalin utuh (termasuk `docs/diagram/_generate.py` dan `_generate_erd.py`). Generator dijalankan dengan `py -3` di Windows (bila `python` tidak ada di PATH).

---

```
Anggaplah dirimu seorang system analyst berpengalaman 20+ tahun di domain WMS/ERP dan prompt engineer 10+ tahun. Kita melanjutkan pekerjaan yang sudah berjalan; jangan mengulang dari nol.

KONTEKS
- Proyek ini belum berisi kode, hanya dokumentasi desain WMS SaaS multi-company (Bahasa Indonesia) di docs/. Baca CLAUDE.md dulu, lalu ikuti urutan bacanya.
- Status saat ini: docs v0.3.2. Part 1 selesai diaudit; Part 2 (BPMN, docs/wms/07*) dan Part 3 (arsitektur & ERD, docs/wms/08*) sudah ada sebagai DRAF yang memakai nilai default asumsi A-25–A-49 yang BELUM saya validasi.
- Laporan audit sebelumnya ada di docs/00-laporan-audit-dokumentasi.md. Temuan di sana sudah diterapkan; jangan mengulanginya.
- File 07*, 08a–08c, dan docs/diagram/*.drawio dibuat otomatis oleh docs/diagram/_generate.py dan _generate_erd.py. Jangan mengedit keluarannya; ubah data di generator lalu jalankan `py -3 docs/diagram/_generate.py` dan `py -3 docs/diagram/_generate_erd.py`.

TUGAS, kerjakan berurutan:

1. VALIDASI ASUMSI BERSAMA SAYA.
   Buka docs/wms/04-keputusan-dan-asumsi.md bagian 2.2 dan 2.3. Ajukan asumsi A-25–A-49 kepada saya per tema (A: model stok & reservasi, B: alur dokumen, C: proyek/opname/approval/akses, D: platform/SSO/PWA) memakai pilihan Setuju / Ubah / Hapus, maksimal 4 pertanyaan per giliran, mulai dari Tema A karena paling menentukan ERD. Untuk setiap asumsi, jelaskan dalam satu kalimat apa dampaknya bila saya menolak (rujuk BR-xx dan bagian dokumen yang terpengaruh lewat matriks ketertelusuran di 04 §4).

2. TERAPKAN HASIL VALIDASI.
   - Isi kolom Validasi di 04 dengan "Setuju (tanggal)" atau "Ubah: …". Asumsi yang berubah: revisi Blueprint, 05-aturan-bisnis, 06-katalog-status, glosarium, dan data generator yang terdampak; jalankan ulang generator; naikkan versi file yang berubah dan tambahkan baris changelog di docs/README.md yang menyebut ID yang berubah.
   - Jangan mengedit keputusan D-xx; bila perlu, buat keputusan baru dan isi kolom "Diganti oleh".

3. AUDIT ULANG RINGAN SETELAH PERUBAHAN.
   Periksa kontradiksi baru antara Blueprint, 05, 06, 07*, 08*: setiap kode dokumen (REQ, PCK, SJ, DSC, GRN, PUT, RTV, TRF, RET, ISU, CNV, AST, ADJ, OPN, WST, PRQ) harus konsisten di Blueprint §7, glosarium, katalog status, alur BPMN, dan tabel ERD; setiap kejadian stok di akuntansi/01 dan purchasing/01 harus ada di matriks 05 §14 dengan tepat satu pemicu. Buat skrip verifikasi kecil (Python) yang mengecek: semua link relatif dan anchor ada, semua ID A/D/O/NFR/P/BR/AD yang dirujuk terdefinisi, tidak ada ID ganda, tidak ada file > 450 baris. Laporkan hasilnya dengan angka.

4. LAPORAN.
   Tulis docs/00-laporan-validasi-<tanggal>.md berisi: asumsi yang disetujui/diubah/dihapus, file yang berubah beserta versinya, temuan audit ringan (bila ada), dan daftar yang masih terbuka. Perbarui docs/README.md (status, changelog).

5. BARU SETELAH ITU, bila saya minta "lanjutkan": mulai Part 4 spesifikasi modul memakai docs/wms/_template-spesifikasi-modul.md dengan urutan di docs/wms/08-arsitektur.md §12 (Access → Master → Warehouse → Stock → Request → …), satu modul per file wms/1x-<modul>.md, maksimal ±400 baris, setiap modul menurunkan migrasi dari 08a–08c, mesin status dari 06, aturan dari 05, dan kasus uji TC-<MOD>-nn (Given/When/Then).

ATURAN KERJA
- Bahasa Indonesia untuk semua dokumen; nama di kode Inggris sesuai glosarium. Jangan mengarang status, istilah, atau ID di luar katalog/glosarium.
- Perubahan substantif yang belum saya setujui dicatat sebagai asumsi baru A-50 dst. berstatus "Perlu validasi", bukan langsung dianggap final.
- Tautan antar dokumen selalu link Markdown relatif beranchor, mis. [A-30](wms/04-keputusan-dan-asumsi.md#a-30).
- Jangan membuat kerangka Laravel atau kode apa pun sebelum Part 4 selesai dan saya minta.
- Jangan commit ke git kecuali saya minta.
- Sebelum mengubah banyak file, tampilkan rencana singkat dan tunggu persetujuan saya.

Mulai dengan: ringkas dalam ≤10 baris apa yang kamu pahami dari CLAUDE.md dan docs/README.md (status, apa yang menunggu saya), lalu langsung ajukan pertanyaan validasi Tema A.
```
