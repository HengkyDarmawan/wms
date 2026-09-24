# Laporan Progres — 24 September 2026

**Versi:** 1.6
**Tanggal:** 24 September 2026
**Status:** potret keadaan setelah modul Picking/Shipment; v1.2: modul Receipt/Putaway selesai (363 uji hijau); v1.3: modul Approval selesai (392 uji hijau); v1.4: modul Count/Adjustment selesai (428 uji hijau); v1.5: modul Return/Transfer selesai (461 uji hijau); diperbarui setiap modul selesai; v1.6: modul Template dokumen & label selesai (476 uji hijau)
**Dokumen terkait:** [README](README.md) · [Setup lokal §5](00-setup-lokal.md#5-skenario-uji-manual) · [Blueprint §18](wms/01-blueprint.md#18-peta-modul--fase-rilis) · [Arsitektur §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4) · [Keputusan & Asumsi](wms/04-keputusan-dan-asumsi.md)

Semua skenario di dokumen dijalankan di aplikasi sungguhan dengan data demo yang baru di-seed: delapan skenario uji manual lewat Chrome headless, 321 uji otomatis, dan pencocokan setiap kasus uji `TC-xx` di spesifikasi dengan ujinya. Hasilnya dipakai untuk memetakan seberapa jauh Fase 1 sudah berjalan.

---

## 1. Ringkasan

| Ukuran | Hasil |
|---|---|
| Skenario E2E [00-setup-lokal §5](00-setup-lokal.md#5-skenario-uji-manual) | **9 dari 9 langkah lulus**, 0 error console |
| Uji otomatis | **321 lulus / 1.836 asersi**, 0 gagal (MariaDB 10.4) |
| Kasus uji `TC-xx` di spesifikasi 10–17 | **178 dari 178 punya uji otomatis**, semuanya lulus |
| Butir Fase 1 di [Blueprint §18](wms/01-blueprint.md#18-peta-modul--fase-rilis) | **10 selesai · 6 sebagian · 7 belum dibangun** (dari 23, satu 'selesai' berupa stub; v1.2 Penerimaan/QC/put-away/RTV; v1.3 Approval engine; v1.4 Stock opname; v1.5 Retur & transfer; v1.6 Template dokumen & label) |
| Bug baru dari E2E | **1 berat** (§5.1), 1 ringan (§5.2) |
| Asumsi menunggu validasi | A-72–A-94 |

Singkatnya: alur keluar **REQ → approval → picking → surat jalan → bukti terima → selisih** berjalan dari ujung ke ujung. Separuh besar Fase 1 lainnya belum dibangun: barang masuk (GRN), approval berlapis, opname, retur/transfer, pemakaian, konversi, dan aset.

## 2. Hasil E2E

Dijalankan dengan skrip CDP di Chrome headless terhadap `php artisan serve`. Form Livewire diisi lewat `$wire` di halaman sungguhan, jadi setiap aksi melewati endpoint HTTP dan middleware tenant yang sebenarnya. Setiap langkah dibuktikan dua kali: dari layar (tangkapan layar dan teks) dan dari database.

| No | Akun | Langkah | Hasil | Bukti |
|---|---|---|---|---|
| 1 | admin | Buka Saldo stok | LULUS | 4 item tampil; BAUT-M12 CKG 1.000 |
| 2 | pemohon.prj001 | Buat REQ PRJ-001, 50 BAUT-M12, ajukan | LULUS | `REQ/PRJ-001/2609/0001` → *under_review* (belum ada gudang sumber, [TC-REQ-06](wms/14-request.md)) |
| 2b | kagudang.ckg | Tinjau: gudang sumber CKG, cara *Stok tersedia*, kirim ke approval | LULUS | → *pending_approval* |
| 3 | admin | Setujui | LULUS | → *approved*; 1 reservasi lunak; tersedia CKG tampil 950 |
| 4 | kagudang.ckg | Buat PCK dari REQ, mulai, catat 50, selesai | LULUS | `PCK/CKG/2609/0001` *completed*; 50 di `CKG-STG` |
| 5 | kagudang.ckg | Susun SJ (kendaraan sendiri, B 9001 XX, Gani), berangkatkan | LULUS | `SJ/CKG/2609/0001` *shipped*; 50 di `CKG-TRANSIT`; kejadian `goods_shipped` |
| 6 | driver1 | Bukti terima: baik 48, kurang 2 | LULUS | SJ *partially_delivered*; `DSC/CKG/2609/0001` *open*; 48 keluar ledger (jual putus), 2 tetap di `CKG-TRANSIT` ([BR-SJ-10](wms/05-aturan-bisnis.md#br-sj)) |
| 7 | klien1 | Portal | LULUS | REQ PRJ-001 tampil |
| 8 | klien2 | Portal | LULUS | REQ tersebut tidak tampil ([A-21](wms/04-keputusan-dan-asumsi.md#a-21)) |
| 9 | admin | *Probe:* batalkan REQ yang barangnya sudah diterima | **GAGAL** | REQ berubah *cancelled*; seharusnya ditolak BR-REQ-09 → §5.1 |

Tabel skenario di [00-setup-lokal §5](00-setup-lokal.md#5-skenario-uji-manual) diperbaiki mengikuti alur sebenarnya: ada langkah tinjau oleh Kepala Gudang, karena REQ internal tanpa gudang sumber masuk *Ditinjau*.

## 3. Cakupan kasus uji

| Spesifikasi | TC terdefinisi | Punya uji | Lulus |
|---|---|---|---|
| [10-access](wms/10-access.md) | 27 | 27 | 27 |
| [11-master](wms/11-master.md) | 23 | 23 | 23 |
| [12-warehouse](wms/12-warehouse.md) | 20 | 20 | 20 |
| [13-stock](wms/13-stock.md) | 34 | 34 | 34 |
| [14-request](wms/14-request.md) | 26 | 26 | 26 |
| [15-picking-shipment](wms/15-picking-shipment.md) | 27 | 27 | 27 |
| [16-shared-laporan-berkas](wms/16-shared-laporan-berkas.md) | 15 | 15 | 15 |
| [17-platform-login](wms/17-platform-login.md) | 6 | 6 | 6 |
| **Jumlah** | **178** | **178** | **178** |

Delapan ID ada di kode uji tetapi tidak ada di tabel §10 spesifikasinya: TC-ACC-28, TC-ACC-29, TC-ACC-30, TC-PCK-09, TC-SJ-15, TC-SJ-16, TC-SJ-17, TC-DSC-06. Ujinya ada dan lulus; hanya tabel dokumennya yang perlu ditambah baris.

Uji otomatis **tidak** menangkap bug §5.1. TC-REQ-15b mengisi `qty_shipped` secara manual, bukan lewat alur pengiriman.

## 4. Peta progres Fase 1

Sumber baris: [Blueprint §18](wms/01-blueprint.md#18-peta-modul--fase-rilis). Status dinilai dari kode di `app/Domain`, §13 tiap spesifikasi, dan E2E di atas.

| Butir Fase 1 | Status | Keterangan |
|---|---|---|
| Login lokal, user, role & permission, cakupan | ✅ Selesai | [10-access](wms/10-access.md); 2FA, perangkat, akses dukungan |
| Struktur organisasi | ✅ Selesai | [10-access](wms/10-access.md) |
| Master data, UoM dinamis, gudang & lokasi rak/bin | ✅ Selesai | [11-master](wms/11-master.md), [12-warehouse](wms/12-warehouse.md); label barcode selesai (18), ukuran final menunggu O-09 |
| Picking, pengiriman, bukti terima, DSC | ✅ Selesai | [15-picking-shipment](wms/15-picking-shipment.md); halaman penerima bertoken belum (O-06) |
| Rencana kebutuhan material (stub) | ✅ Stub | tabel ada, layar Fase 2 |
| Platform, tenancy, trial, tagihan manual | 🟡 Sebagian | tenancy, login Super Admin, gerbang langganan; **belum** buat company (`CreateTenant`) dan tagihan ([17-platform-login](wms/17-platform-login.md)) |
| Kartu stok, reservasi dua tahap, strategi pengambilan | 🟡 Sebagian | ledger dan reservasi selesai; alokasi picking masih urut kode bin, **FEFO/FIFO/potongan terdekat belum** (`CreatePickTask::alokasikan`) |
| Permintaan (internal + portal, non-katalog, gudang sumber) | 🟡 Sebagian | alur jalan; bug §5.1 diperbaiki; konfirmasi/keberatan terima klien belum; approval berlapis lewat mesin approval (v1.3) |
| Notifikasi in-app & email | 🟡 Sebagian | hanya undangan dan akun terkunci |
| Laporan & dashboard | 🟡 Sebagian | 7 dari 19 laporan §9; ekspor PDF belum; Beranda masih kartu statis (§5.2) |
| Integrasi Purchasing & Akuntansi | 🟡 Sebagian | outbox kejadian stok jalan; Purchase Request manual belum |
| Wizard setup awal company | ⬜ Belum | |
| Impor data master dari Excel | ⬜ Belum | |
| Penerimaan (GRN), QC, put-away, RTV | ✅ Selesai | [19-receipt-putaway](wms/19-receipt-putaway.md): GRN vendor manual & transfer masuk, QC per baris, PUT dengan saran bin, RTV; cross-dock masih saran ([A-83](wms/04-keputusan-dan-asumsi.md#a-83)); GRN retur sejak modul Retur |
| Pemakaian material di site (ISU) | ⬜ Belum | |
| Retur & transfer | ✅ Selesai | [22-retur-transfer](wms/22-retur-transfer.md): TRF antar gudang, antar proyek, dan antar titik dalam proyek (jalur ringan A-50), TRF otomatis dari backorder REQ sampai REQ terpenuhi lewat gudang tujuan, PCK/SJ/GRN transfer, TRF selesai saat GRN tujuan selesai; RET dari Gudang Site, aset On-site, barang terkirim ke klien, dan barang rusak ditinggal ekspedisi, SJ balik dari Gudang Site, GRN retur ke bin Retur, pemilahan layak/rusak/offcut/waste dengan `goods_returned`/`asset_returned`; portal klien; pemeriksaan aset menunggu modul Aset ([A-106](wms/04-keputusan-dan-asumsi.md#a-106)–[A-116](wms/04-keputusan-dan-asumsi.md#a-116)) |
| Konversi material, offcut, waste | ⬜ Belum | fitur pembeda utama |
| Aset dipinjamkan | ⬜ Belum | |
| Approval engine | ✅ Selesai | [20-approval](wms/20-approval.md): aturan per jenis dokumen tanpa nilai uang, lapis & cara putus, SoD, snapshot, delegasi, eskalasi terjadwal + manual, simulasi, riwayat; REQ, RTV, ADJ, OPN, TRF, dan RET tersambung; WhatsApp stub Fase 2a; notifikasi stub ([A-91](wms/04-keputusan-dan-asumsi.md#a-91)) |
| Stock opname | ✅ Selesai | [21-opname-penyesuaian](wms/21-opname-penyesuaian.md): sesi bulanan/tahunan/ad-hoc/pemeriksaan mendadak, pembekuan bin, hitung buta di halaman ramah HP, toleransi ganda, hitung ulang orang berbeda, akar masalah, approval tingkat sesi (Auditor untuk tahunan/audit), ADJ per gudang diposting lewat buku besar, kunci periode bulanan, penanda hitung; ADJ manual dua lapis & pembalik; override SJ mendesak dan dashboard tren belum ([A-95](wms/04-keputusan-dan-asumsi.md#a-95)–[A-105](wms/04-keputusan-dan-asumsi.md#a-105)) |
| Template dokumen & label | ✅ Selesai | [18-template-dokumen-label](wms/18-template-dokumen-label.md); editor template [F2]; ukuran label menunggu O-09 (A-120) |
| PWA (installable, scan kamera, draf lokal) | ⬜ Belum | |
| Landing page produk | ⬜ Belum | Part 5 |

Urutan pembangunan berikutnya menurut [Arsitektur §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4) (Receipt/Putaway, Approval, Count/Adjustment, dan Return/Transfer sudah selesai): Issue → Conversion/Waste → Asset → PurchaseRequest/VendorReturn → Platform.

## 5. Temuan dari E2E

### 5.1 Pengiriman tidak mencatat balik ke baris REQ (berat)

> **Diperbaiki 24 Sep 2026** — `RequestFulfillment` ([14-request §13](wms/14-request.md#13-catatan-implementasi-24-september-2026)); uji TC-REQ-27–29; asumsi [A-77](wms/04-keputusan-dan-asumsi.md#a-77).

| Hal | Isi |
|---|---|
| Gejala | Setelah SJ dikirim dan 48 unit diterima, baris REQ tetap `qty_shipped = 0`, `qty_received = 0`, `qty_reserved = 50`, status baris *open*; REQ tetap *in_progress* |
| Akibat | (1) REQ yang barangnya sudah di site **bisa dibatalkan** — probe no. 9, melanggar [BR-REQ-09](wms/05-aturan-bisnis.md#br-req); (2) REQ tidak pernah menjadi *partially_fulfilled* atau *completed*; (3) *Tutup dengan sisa* dan pelepasan reservasi memakai `outstandingQty()` yang salah; (4) layar REQ tidak menunjukkan berapa yang sudah dikirim |
| Penyebab | `ShipShipment` dan `ConfirmDelivery` (`app/Domain/Shipment/Actions`) tidak memperbarui `material_request_lines`. Komentar di `CancelRequest` masih berbunyi "modul `shipment` belum ada" |
| Kenapa lolos uji | TC-REQ-15b menulis `qty_shipped` langsung dengan `forceFill`; tidak ada uji yang menjalankan REQ → SJ → bukti terima lalu memeriksa REQ |
| Usulan | `ShipShipment` menambah `qty_shipped` dan `ConfirmDelivery` menambah `qty_received` per baris REQ asal (lewat `pick_task_lines.source_line_id`), lalu status REQ diturunkan dari jumlah itu ([Katalog §2.1](wms/06-katalog-status-dan-enum.md)). Plus uji alur penuh. **Belum dikerjakan; menunggu persetujuan** |

### 5.2 Kartu "Modul berikutnya" di Beranda basi (ringan)

Beranda masih menulis Master, Gudang, dan Stok sebagai modul berikutnya, padahal ketiganya sudah jadi. Laporan Beranda §9 belum dibangun.

### 5.3 Temuan terdahulu yang masih terbuka

Dua belas selisih kode dengan aturan di [16-shared §13](wms/16-shared-laporan-berkas.md) dan [17-platform-login §13](wms/17-platform-login.md). Antara lain:
- `terminated` lebih longgar dari BR-SUB-03;
- menghapus company ikut menghapus database-nya (BR-SUB-01, P-03);
- login Super Admin tanpa log percobaan, penguncian, dan 2FA;
- guard penutupan proyek BR-PRJ-02 belum ada ([11-master §13.4](wms/11-master.md#134-sisa-pekerjaan-modul-ini)).

## 6. Cara mengulang

1. Data bersih: drop `wms_tenant_demo`, `php artisan migrate:fresh`, lalu urutan seed [00-setup-lokal §3](00-setup-lokal.md#3-database-dan-data-demo).
2. `php artisan serve --host=127.0.0.1 --port=8000`.
3. Jalankan skrip E2E (Node 24 + Chrome, tanpa paket tambahan). Skrip belum disimpan di repo; bila diinginkan, bisa dijadikan `tests/e2e/` dengan perintah npm sendiri.
4. `php artisan test --log-junit` untuk hasil per uji.
