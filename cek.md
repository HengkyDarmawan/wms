# Baca ini dulu

Berkas ini dulu berisi salinan prompt sesi lama yang menyatakan *"proyek ini belum berisi kode"* dan *"jangan membuat kerangka Laravel"*. Keduanya sudah tidak benar sejak 23 September 2026, dan membiarkannya membuat siapa pun yang membacanya lebih dulu salah mengira keadaan proyek.

**Keadaan sekarang, 24 September 2026 (sore):**

- Aplikasi Laravel 13 ada di repo ini (`app/`, `routes/`, `resources/`, `database/`).
- Modul **Access**, **Master**, **Warehouse**, **Stock**, **Request**, **Picking/Shipment**, **Receipt/Putaway**, **Approval**, dan **Count/Adjustment** (stock opname & penyesuaian stok) selesai untuk Fase 1. Modul berikutnya menurut Arsitektur §12: **Return/Transfer**.
- 428 uji hijau (MariaDB 10.4 di kantor). Dokumentasi 45 berkas, tanpa link rusak.
- Asumsi A-72–A-105 menunggu validasi pemilik produk.

**Mulai dari sini:**

| Mau apa | Baca |
|---|---|
| Mengerjakan apa pun di repo ini | [`CLAUDE.md`](CLAUDE.md), lalu urutan baca di dalamnya |
| Menjalankan aplikasi dan mengetes di browser | [`docs/00-setup-lokal.md`](docs/00-setup-lokal.md) |
| Melihat status dan riwayat perubahan | [`docs/README.md`](docs/README.md) |
| Tahu apa yang menunggu keputusan pemilik produk | [A-72–A-76](docs/wms/04-keputusan-dan-asumsi.md#25-baru-dari-pencocokan-dokumen-dengan-kode--24-sep-2026), [A-77–A-105](docs/wms/04-keputusan-dan-asumsi.md#26-baru-dari-pembangunan-modul-lanjutan--24-sep-2026) |
| Melanjutkan ke modul berikutnya | Spesifikasi modul terakhir sebagai pola: [`docs/wms/21-opname-penyesuaian.md`](docs/wms/21-opname-penyesuaian.md); dokumen baru memasang penangan approval-nya ([`20-approval.md`](docs/wms/20-approval.md) §3.9) |
| Prompt sesi lanjutan | [`docs/prompts/`](docs/prompts/) |

Prompt audit dokumentasi yang dulu ada di sini tersimpan utuh di [`docs/prompts/00-lanjutkan-audit.md`](docs/prompts/00-lanjutkan-audit.md), lengkap dengan catatan bahwa konteksnya sudah lewat.
