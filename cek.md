# Baca ini dulu

Berkas ini dulu berisi salinan prompt sesi lama yang menyatakan *"proyek ini belum berisi kode"* dan *"jangan membuat kerangka Laravel"*. Keduanya sudah tidak benar sejak 23 September 2026, dan membiarkannya membuat siapa pun yang membacanya lebih dulu salah mengira keadaan proyek.

**Keadaan sekarang, 25 September 2026:**

- Aplikasi Laravel 13 ada di repo ini (`app/`, `routes/`, `resources/`, `database/`).
- Modul **Access**, **Master**, **Warehouse**, **Stock**, **Request**, **Picking/Shipment**, **Receipt/Putaway**, **Approval**, **Count/Adjustment** (stock opname & penyesuaian stok), **Return/Transfer** (TRF & retur dari proyek), **Template dokumen & label**, **Issue** (pemakaian material di site), **Conversion/Waste** (konversi material & berita acara waste), **Asset** (aset dipinjamkan), **PurchaseRequest** (permintaan pembelian), **Platform** (company, langganan, tagihan), dan **Pendukung F1** (strategi pengambilan, notifikasi, laporan & Beranda, wizard, impor Excel, PWA) selesai untuk Fase 1, termasuk **penutup** (uji rantai penuh, E2E, tinjauan kode, [tinjauan asumsi](docs/00-tinjauan-asumsi-2026-09-25.md)).
- 579 uji hijau (MariaDB 10.4 di kantor dan di mesin rumah XAMPP3). Dokumentasi 54 berkas Markdown diperiksa `_verify.py`, tanpa link rusak.
- Asumsi A-72–A-119, A-120–A-126, dan A-150–A-205 menunggu validasi pemilik produk — daftar kerjanya [docs/00-tinjauan-asumsi-2026-09-25.md](docs/00-tinjauan-asumsi-2026-09-25.md).

**Mulai dari sini:**

| Mau apa | Baca |
|---|---|
| Mengerjakan apa pun di repo ini | [`CLAUDE.md`](CLAUDE.md), lalu urutan baca di dalamnya |
| Menjalankan aplikasi dan mengetes di browser | [`docs/00-setup-lokal.md`](docs/00-setup-lokal.md) |
| Melihat status dan riwayat perubahan | [`docs/README.md`](docs/README.md) |
| Tahu apa yang menunggu keputusan pemilik produk | [A-72–A-76](docs/wms/04-keputusan-dan-asumsi.md#25-baru-dari-pencocokan-dokumen-dengan-kode--24-sep-2026), [A-77–A-116](docs/wms/04-keputusan-dan-asumsi.md#26-baru-dari-pembangunan-modul-lanjutan--24-sep-2026), [A-120–A-126](docs/wms/04-keputusan-dan-asumsi.md#27-baru-dari-modul-template-dokumen--label--24-sep-2026), [A-117–A-119 & A-150–A-152](docs/wms/04-keputusan-dan-asumsi.md#28-baru-dari-modul-issue-pemakaian-material-di-site--24-sep-2026), [A-153–A-162](docs/wms/04-keputusan-dan-asumsi.md#29-baru-dari-modul-konversi--waste--24-sep-2026), [A-163–A-169](docs/wms/04-keputusan-dan-asumsi.md#210-baru-dari-modul-aset-dipinjamkan--24-sep-2026), [A-170–A-175](docs/wms/04-keputusan-dan-asumsi.md#211-baru-dari-modul-purchase-request--25-sep-2026), [A-176–A-184](docs/wms/04-keputusan-dan-asumsi.md#212-baru-dari-modul-platform-penuh--25-sep-2026), [A-185–A-194](docs/wms/04-keputusan-dan-asumsi.md#213-baru-dari-pendukung-fase-1--25-sep-2026), [A-195–A-205](docs/wms/04-keputusan-dan-asumsi.md#214-baru-dari-tinjauan-kode-pendukung--platform--25-sep-2026) |
| Melanjutkan ke modul berikutnya | Spesifikasi modul terakhir sebagai pola: [`docs/wms/27-pendukung-f1.md`](docs/wms/27-pendukung-f1.md); dokumen baru memasang penangan approval-nya ([`20-approval.md`](docs/wms/20-approval.md) §3.9) |
| Prompt sesi lanjutan | [`docs/prompts/00-lanjutkan-di-kantor.md`](docs/prompts/00-lanjutkan-di-kantor.md) (serah terima terbaru); semua prompt di [`docs/prompts/`](docs/prompts/) |

Prompt audit dokumentasi yang dulu ada di sini tersimpan utuh di [`docs/prompts/00-lanjutkan-audit.md`](docs/prompts/00-lanjutkan-audit.md), lengkap dengan catatan bahwa konteksnya sudah lewat.
