# Spesifikasi Modul — `shared` (Kerangka Laporan & Penyimpanan Berkas)

**Versi:** 0.27
**Tanggal:** 30 September 2026
**Status:** selesai Fase 1 — v0.27: `<x-pilih>` menerima opsi `disabled` dan kunci `galat`; empat pilihan terakhir (bin di detail Put-away, bin Picking, bin tujuan Pilah retur, batang Konversi) memakai `<x-pilih>` (§6.5, TC-PIL-10); v0.26: penyaring laporan memakai `<x-pilih>` — gudang/klien/kategori/jenis dokumen dimuat sekaligus, proyek & vendor dicari ke server lewat `Report::pilihanPenyaring`; akun Klien tanpa pilihan ([A-396](04b-asumsi-lanjutan.md#a-396), §6.2, TC-RPT-14, TC-RPT-14b); v0.25: `<x-pilih>` di sel tabel minimal 14rem ([A-393](04b-asumsi-lanjutan.md#a-393), §6.5 no. 7); v0.24: label nilai dari server `labelPilihan` ([A-392](04b-asumsi-lanjutan.md#a-392), §6.5, TC-PIL-09); v0.23: teks nilai terpilih disembunyikan saat mengetik di `<x-pilih>` ([A-390](04b-asumsi-lanjutan.md#a-390), §6.5); v0.22: pola UI **komponen pilihan** `<x-pilih>` & cari ke server (§6.5, [A-383](04b-asumsi-lanjutan.md#a-383), [A-384](04b-asumsi-lanjutan.md#a-384), TC-PIL-01–08); dokumen ini **mencatat kode yang sudah ada** (dibangun tanpa spesifikasi); laporan modul Stock, Request, dan Picking/Shipment selesai 25 Sep 2026 ([A-232](04-keputusan-dan-asumsi.md#a-232), §3.2); laporan modul 19–22 selesai 25 Sep 2026 (16 laporan, [A-241](04-keputusan-dan-asumsi.md#a-241)); total 44 laporan terdaftar; v0.3: laporan kedelapan *Material per proyek* dari modul Issue ([23-pemakaian](23-pemakaian.md) §9); v0.4: laporan itu menambah kolom konversi & waste ([24-konversi-waste](24-konversi-waste.md) §9, [A-161](04-keputusan-dan-asumsi.md#a-161)); unggah bukti BA waste memakai `StoreUpload`; v0.5: laporan kesembilan *Aset dipinjamkan* ([25-aset](25-aset.md) §9); v0.6: Beranda antrean pekerjaan, lima laporan inti Blueprint §6.9a (total 14), ekspor PDF semua laporan ([27-pendukung-f1](27-pendukung-f1.md), [A-186](04-keputusan-dan-asumsi.md#a-186), [A-190](04-keputusan-dan-asumsi.md#a-190)); v0.13: kompresi foto otomatis di server ([A-23](04-keputusan-dan-asumsi.md#a-23), [A-257](04b-asumsi-lanjutan.md#a-257), §6.4); v0.14: diselaraskan dengan kode — §2 memuat 41 laporan per permission, §3.2 diperbaiki (+5 laporan inti), ekspor PDF di §6, §10 TC-RPT-01, §12 dan §13.1 no. 1 & 3 ditandai selesai; v0.15: *Daftar item* memakai kolom & saring Jenis barang; kolom potong/offcut/kerf mengikuti saklar per potong ([A-283](04b-asumsi-lanjutan.md#a-283), [A-284](04b-asumsi-lanjutan.md#a-284), §10 TC-RPT-01d, TC-RPT-11); v0.16: *Barang karantina menurut umur* ikut barang Rusak di bin Karantina (kolom Kondisi); *Penerimaan per vendor* berkolom Rusak & Kurang ([A-295](04b-asumsi-lanjutan.md#a-295)); v0.20: laporan ke-44 **Rekap pengiriman per klien** dan kunci `required` pada penyaring ([A-329](04b-asumsi-lanjutan.md#a-329), [A-330](04b-asumsi-lanjutan.md#a-330); §2, §3.2, §6.2, §10 TC-RPT-13); v0.21: *Rekap pengiriman per klien* hanya memuat SJ yang sudah berangkat (menurut tanggal berangkat) dan memotong proyek sesuai cakupan pembaca ([A-351](04b-asumsi-lanjutan.md#a-351), TC-RPT-13d)
**Modul:** `shared` (`app/Domain/Shared`)
**Fase:** F1
**Dokumen terkait:** [Blueprint §6.9a](01-blueprint.md#69a-laporan-inti-fase-1) · [Arsitektur §2 (AD-09, AD-10)](08-arsitektur.md#2-keputusan-arsitektur) · [Blueprint §16 (NFR-14)](01-blueprint.md#16-kebutuhan-non-fungsional) · [A-68](04-keputusan-dan-asumsi.md#a-68) · [Aturan Bisnis §BR-ACC](05-aturan-bisnis.md#br-acc) · [Glosarium](03-glosarium.md)
**Ketergantungan modul:** `access` (permission & cakupan), `master`, `warehouse` (sumber data laporan). Dipakai oleh semua modul yang punya §9 *Laporan & dashboard* atau yang menyimpan berkas.

---

## 1. Tujuan & lingkup

Modul ini bukan modul bisnis; ia menyediakan dua layanan bersama yang dipakai modul lain:

- **Kerangka laporan.** Setiap laporan di §9 spesifikasi modul berbentuk sama: tabel, beberapa penyaring, tombol ekspor. Laporan didefinisikan sebagai **data** (satu kelas turunan `Report`), lalu satu layar dan satu jalur ekspor Excel dan PDF melayani semuanya. Menambah laporan tidak menambah layar.
- **Penyimpanan berkas.** Satu kelas `StoreUpload` untuk menyimpan, menghapus, dan mengalirkan berkas unggahan (tanda tangan, foto item) di disk privat per company, disajikan lewat route berotorisasi.

**Tidak termasuk:** dashboard/grafik, impor Excel (`import_batches`, AD-09), penyimpanan S3 produksi ([O-14](04-keputusan-dan-asumsi.md#o-14)).

## 2. Aktor & permission

Modul ini **tidak mendaftarkan permission sendiri**. Izin diperiksa per laporan memakai permission `view` milik modul sumber datanya (`permission()` tiap kelas di `Reports/Definitions`; 44 laporan di `ReportRegistry::REPORTS`):

| Laporan | Permission | Modul pemilik permission |
|---|---|---|
| `user-role-cakupan`, `log-login` | `user.view` | `access` |
| `daftar-item`, `item-sementara` | `item.view` | `master` |
| `daftar-proyek` | `project.view` | `master` |
| `daftar-gudang` | `warehouse.view` | `warehouse` |
| `daftar-bin` | `bin.view` | `warehouse` |
| `saldo-stok`, `mutasi-periode`, `kartu-stok`, `titik-pesan-ulang` | `stock.view` | `stock` |
| `reservasi-menggantung` | `reservation.view` | `stock` |
| `permintaan-terbuka`, `daftar-req`, `req-menunggu-tinjau`, `baris-tanpa-sumber`, `penggantian-menunggu` | `request.view` | `request` |
| `short-pick` | `pick.view` | `picking` |
| `daftar-pengiriman`, `posisi-rusak-selisih`, `kinerja-pengiriman`, `rekap-pengiriman-klien` | `shipment.view` | `shipment` |
| `penerimaan-vendor`, `karantina-umur`, `barang-bermasalah-vendor` | `receipt.view` | `receipt` |
| `put-tertunda` | `putaway.view` | `putaway` |
| `rtv-terbuka` | `vendor_return.view` | `vendor_return` |
| `tugas-approval-terbuka`, `dokumen-menunggu-approval`, `waktu-putus-approval`, `eskalasi-approval` | `approval_rule.view` | `approval` ([A-241](04-keputusan-dan-asumsi.md#a-241)) |
| `akurasi-stok`, `tren-akurasi`, `top-selisih`, `akar-masalah` | `count.view` | `count` |
| `adj-per-alasan` | `adjustment.view` | `adjustment` |
| `trf-terbuka`, `barang-dalam-perjalanan` | `transfer.view` | `transfer` |
| `retur-per-proyek`, `rusak-bin-retur` | `return.view` | `return` |
| `material-per-proyek` | `issue.view` | `issue` |
| `konversi-waste` | `conversion.view` | `conversion` |
| `aset-dipinjamkan` | `asset.view` | `asset` |

Menu *Laporan* (`/reports`) tampil untuk semua user internal; isinya hanya laporan yang permission-nya dipegang user (`ReportRegistry::availableTo`).

Berkas: tanda tangan sendiri selalu boleh dibuka; tanda tangan orang lain menuntut `user.view`; foto item mengikuti policy `view`/`update` model `Item`.

## 3. Entitas & data

Tidak ada tabel baru. Laporan membaca tabel modul lain lewat model Eloquent, sehingga global scope `ScopedToUser` ([BR-ACC-05](05-aturan-bisnis.md#br-acc)) ikut berlaku — model `Warehouse` dan `Bin` memakainya. Berkas disimpan sebagai **path** di kolom model pemiliknya (`users.signature_path`, `items.photo_path`), bukan di tabel `attachments` ERD. Sejak 25 Sep 2026 tabel `attachments` ada untuk lampiran dokumen (foto ISU, foto serah terima AST keluar, arsip PDF opname) lewat `Shared\Attachments\Support\AttachmentStore` dan `GET /attachments/{id}` berizin dokumen pemiliknya ([A-238](04-keputusan-dan-asumsi.md#a-238)).

### 3.1 Kelas inti

| Kelas | Berkas | Peran |
|---|---|---|
| `Report` (abstrak) | `app/Domain/Shared/Reports/Report.php` | Kontrak satu laporan: `key()`, `title()`, `permission()`, `description()`, `columns()` (kunci ⇒ judul), `rows(array $filters)`, `filters()` (kunci ⇒ label + pilihan; tanpa pilihan = isian teks), `fileName()` (`<key>-YmdHis`) |
| `ReportRegistry` | `app/Domain/Shared/Reports/ReportRegistry.php` | Daftar statis kelas laporan; `all()`, `availableTo(User)`, `find(key)` |
| `ReportExport` | `app/Domain/Shared/Reports/ReportExport.php` | Satu kelas ekspor `maatwebsite/excel` untuk semua laporan (AD-09): `FromCollection`, `WithHeadings`, `ShouldAutoSize`, baris judul tebal |
| `ReportViewer` | `app/Domain/Shared/Livewire/ReportViewer.php` | Komponen Livewire layar laporan; penyaring terikat ke query string (`#[Url]`) |
| `ReportController` | `app/Http/Controllers/Shared/ReportController.php` | `index`, `show`, `export`, `pdf` |
| `StoreUpload` | `app/Domain/Shared/Files/StoreUpload.php` | `handle()` (satu pintu semua foto; `keepFormat` untuk tanda tangan & logo), `handleDataUrl()`, `delete()`, `exists()`, `stream()`, `size()`, `mimeType()`; konstanta `MAKSIMUM_MENTAH` 20 MB, `MAKSIMUM_BYTE` 5 MB, `ATURAN_FOTO` |
| `ImageCompressor` | `app/Domain/Shared/Files/ImageCompressor.php` | Kompresi foto dengan GD ([A-257](04b-asumsi-lanjutan.md#a-257)): putar sesuai EXIF, sisi terpanjang 1920 px, JPEG/WEBP kualitas 80, PNG transparan tetap PNG |
| `FileController` | `app/Http/Controllers/Shared/FileController.php` | Mengalirkan tanda tangan dan foto item setelah izin diperiksa |

### 3.2 Definisi laporan terdaftar (`Reports/Definitions`)

| Kunci | Kelas | Judul | Permission | Kolom | Penyaring | Asal |
|---|---|---|---|---|---|---|
| `user-role-cakupan` | `UserRoleScopeReport` | Pengguna × role × cakupan | `user.view` | pengguna, email, role, cakupan (nama gudang/proyek, bukan id), berlaku dari, berlaku sampai, atasan langsung, status pengguna | status pengguna (aktif/nonaktif), jenis cakupan | [10-access §9](10-access.md#9-laporan--dashboard) *User × role × cakupan* |
| `log-login` | `LoginLogReport` | Log masuk 30 hari | `user.view` | waktu (zona waktu company), email, pengguna, alamat IP, kanal, hasil | hasil, email mengandung | [10-access §9](10-access.md#9-laporan--dashboard) *Log login 30 hari* |
| `daftar-item` | `ItemListReport` | Daftar item | `item.view` | kode, nama, kategori, satuan dasar, mode pelacakan, kepemilikan, titik pesan ulang, stok minimum, status | status, mode pelacakan, kepemilikan | [11-master §9](11-master.md#9-laporan--dashboard) *Daftar item* |
| `item-sementara` | `ProvisionalItemReport` | Item sementara | `item.view` | kode, nama, kategori, satuan dasar, dibuat, umur (hari) | — (selalu `status = provisional`) | [11-master §9](11-master.md#9-laporan--dashboard) *Item sementara* |
| `daftar-proyek` | `ProjectListReport` | Daftar proyek | `project.view` | kode, nama, klien (*Proyek Internal* bila internal), PIC, mulai, target selesai, jumlah Gudang Site, status | status, klien | [11-master §9](11-master.md#9-laporan--dashboard) *Proyek* |
| `daftar-gudang` | `WarehouseListReport` | Daftar gudang | `warehouse.view` | kode, nama, tipe, gudang induk, proyek, kepala gudang, jumlah zona, jumlah bin, status | tipe, status (aktif/nonaktif) | [12-warehouse §9](12-warehouse.md#9-laporan--dashboard) *Daftar gudang* |
| `daftar-bin` | `BinListReport` | Daftar bin | `bin.view` | kode bin, gudang, jenis, kategori penyimpanan, penegakan kapasitas, kapasitas jumlah, kapasitas berat, proyek, perlu dihitung, status | gudang, jenis, status, kategori penyimpanan | [12-warehouse §9](12-warehouse.md#9-laporan--dashboard) *Daftar bin* |
| `material-per-proyek` | `ProjectMaterialReport` | Material per proyek | `issue.view` | proyek, kode & nama item, satuan, diminta, terkirim, terpakai, diretur, di Gudang Site, aset di proyek (dibaca dari kartu stok, [A-151](04-keputusan-dan-asumsi.md#a-151)) | proyek (dalam cakupan), item mengandung | [23-pemakaian §9](23-pemakaian.md#9-laporan--dashboard), Blueprint §9 *Material per proyek* |
| `aset-dipinjamkan` | `LoanedAssetReport` | Aset dipinjamkan | `asset.view` | AST, proyek, item, serial, keluar, jatuh tempo, lewat (hari), hari pakai, meter keluar, status | proyek, hanya lewat jatuh tempo | [25-aset §9](25-aset.md#9-laporan--dashboard), BR-AST-06 |
| `saldo-stok` | `StockBalanceReport` | Saldo stok | `stock.view` | gudang, bin, kode & nama item, lot/serial/potongan, kondisi, jumlah, satuan, jumlah potong | gudang, kondisi, kode item | [27-pendukung-f1 §9](27-pendukung-f1.md#9-laporan--dashboard), [A-190](04-keputusan-dan-asumsi.md#a-190) |
| `mutasi-periode` | `StockMovementPeriodReport` | Mutasi periode | `stock.view` | gudang, kode & nama item, satuan, saldo awal, masuk, keluar, saldo akhir | dari & sampai tanggal, gudang | [27-pendukung-f1 §9](27-pendukung-f1.md#9-laporan--dashboard), [A-190](04-keputusan-dan-asumsi.md#a-190) |
| `permintaan-terbuka` | `OpenRequestReport` | Permintaan terbuka & barang rusak | `request.view` | jenis (baris REQ/DSC), dokumen, proyek, item, status, sisa/jumlah, dibutuhkan, lewat/umur (hari) | hanya yang lewat tanggal / DSC > 3 hari | [27-pendukung-f1 §9](27-pendukung-f1.md#9-laporan--dashboard), [A-190](04-keputusan-dan-asumsi.md#a-190) |
| `konversi-waste` | `ConversionWasteReport` | Konversi & waste | `conversion.view` | proyek, item input, jumlah CNV, input, output, offcut, waste, kerf, % waste | proyek (dalam cakupan) | [27-pendukung-f1 §9](27-pendukung-f1.md#9-laporan--dashboard), [A-190](04-keputusan-dan-asumsi.md#a-190) |
| `akurasi-stok` | `StockAccuracyReport` | Akurasi stok | `count.view` | nomor OPN, jenis, gudang, direkonsiliasi, status, baris dihitung, baris cocok, akurasi %, selisih kecil/sedang/besar | — | [27-pendukung-f1 §9](27-pendukung-f1.md#9-laporan--dashboard), [A-190](04-keputusan-dan-asumsi.md#a-190) |
| `kartu-stok` | `StockCardReport` | Kartu stok | `stock.view` | waktu, item, dokumen, asal, tujuan, jumlah, satuan, kondisi, pelaku | kode item, gudang, rentang tanggal (bawaan bulan berjalan; ≤ 2.000 baris) | [13-stock §9](13-stock.md#9-laporan--dashboard) |
| `reservasi-menggantung` | `StaleReservationReport` | Reservasi menggantung | `reservation.view` | dokumen (nomor REQ/TRF/RET), item, gudang, bin, jumlah, satuan, umur | gudang, umur minimal (bawaan `reservation_alert_days`) | [13-stock §9](13-stock.md#9-laporan--dashboard), BR-STK-16 |
| `titik-pesan-ulang` | `ReorderPointReport` | Stok di bawah titik pesan ulang | `stock.view` | item, nama, kategori, tersedia, titik pesan ulang, selisih, satuan | gudang, kategori | [13-stock §9](13-stock.md#9-laporan--dashboard), BR-REQ-11 |
| `daftar-req` | `RequestListReport` | Daftar permintaan material | `request.view` | nomor, dibuat, proyek, pemohon, status, jumlah baris, dibutuhkan | status, proyek, pemohon, rentang tanggal | [14-request §9](14-request.md#9-laporan--dashboard) |
| `req-menunggu-tinjau` | `RequestReviewQueueReport` | Permintaan menunggu tinjauan | `request.view` | nomor, proyek, pemohon, status, umur, SLA terlampaui (`review_sla_days`) | umur minimal | [14-request §9](14-request.md#9-laporan--dashboard), BR-REQ-14 |
| `baris-tanpa-sumber` | `UnsourcedLineReport` | Baris permintaan tanpa sumber | `request.view` | REQ, proyek, status REQ, item, jumlah, satuan, dibutuhkan | proyek | [14-request §9](14-request.md#9-laporan--dashboard) |
| `penggantian-menunggu` | `PendingSubstitutionReport` | Penggantian item menunggu tanggapan | `request.view` | REQ, proyek, item asal, item pengganti, jumlah, diganti, tenggat, lewat tenggat | proyek, tenggat sebelum | [14-request §9](14-request.md#9-laporan--dashboard), BR-REQ-13 |
| `daftar-pengiriman` | `ShipmentListReport` | Daftar pengiriman | `shipment.view` | nomor, gudang, tujuan, cara kirim, status, tanggal kirim, diterima, penerima | status, tujuan, cara kirim, gudang, rentang tanggal | [15-picking-shipment §9](15-picking-shipment.md#9-laporan--dashboard) |
| `short-pick` | `ShortPickReport` | Short pick | `pick.view` | PCK, gudang, selesai, item, bin, dialokasikan, diambil, selisih, alasan | gudang, rentang tanggal | [15-picking-shipment §9](15-picking-shipment.md#9-laporan--dashboard), BR-SJ-01 |
| `posisi-rusak-selisih` | `DamagedGoodsPositionReport` | Posisi barang rusak & selisih | `shipment.view` | DSC, SJ, gudang, proyek, item, jenis, jumlah, disposisi, status DSC, umur | gudang, umur minimal, termasuk yang selesai | [15-picking-shipment §9](15-picking-shipment.md#9-laporan--dashboard), BR-SJ-06/10 |
| `kinerja-pengiriman` | `DeliveryPerformanceReport` | Kinerja pengiriman | `shipment.view` | per gudang (+ total): dikirim, diterima utuh, bersisa, masih di jalan, dibatalkan, rata-rata hari, utuh (%) | gudang, rentang tanggal (berangkat) | [15-picking-shipment §9](15-picking-shipment.md#9-laporan--dashboard) |
| `rekap-pengiriman-klien` | `ClientShipmentRecapReport` | Rekap pengiriman per klien | `shipment.view` | tanggal kirim, tanggal diterima, No. SJ, proyek, No. PO klien, No. GR klien, kode & nama item, dikirim, diterima baik, rusak, kurang, satuan; ditutup baris **TOTAL per item** | **klien (wajib)**, proyek klien itu, tujuan (Proyek klien / Gudang Site), rentang tanggal | [11-master §6](11-master.md#6-layar), [A-329](04b-asumsi-lanjutan.md#a-329) |
| `penerimaan-vendor` | `VendorReceiptReport` | Penerimaan per vendor | `receipt.view` | GRN, vendor, gudang, tanggal terima, SJ vendor, jumlah baris, total diterima, status | gudang, vendor, rentang tanggal (terima) | [19 §9](19-receipt-putaway.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `barang-bermasalah-vendor` | `VendorProblemReport` | Barang bermasalah per vendor | `receipt.view` | vendor, item, satuan, diterima, rusak saat terima, kurang, ditolak QC, rusak saat dikirim, retur rusak/waste, % bermasalah, diretur ke vendor, dilacak lewat | gudang, vendor, periode (tanggal terima GRN asal) | [19 §9](19-receipt-putaway.md), [A-303](04b-asumsi-lanjutan.md#a-303) |
| `riwayat-harga-beli` | `Purchasing\Reports\PurchasePriceHistoryReport` | Riwayat harga beli | `po.view` | tanggal PO, nomor PO, vendor, item, jumlah, satuan, harga satuan, PPN, status — satu-satunya laporan bernilai uang (domain Purchasing, D-07) | kode item, vendor, periode (bawaan 12 bulan) | [purchasing/02 §9](../purchasing/02-purchasing-inti.md), [A-309](04b-asumsi-lanjutan.md#a-309) |
| `karantina-umur` | `QuarantineAgeReport` | Barang di Karantina menurut umur | `receipt.view` | gudang, bin, item, lot/serial/potongan, jumlah, satuan, dokumen masuk, masuk, umur | gudang, umur minimal | [19 §9](19-receipt-putaway.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `put-tertunda` | `PendingPutawayReport` | Put-away tertunda | `putaway.view` | PUT, GRN, gudang, petugas, dibuat, umur, jumlah baris, status | gudang | [19 §9](19-receipt-putaway.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `rtv-terbuka` | `OpenVendorReturnReport` | Retur ke vendor terbuka | `vendor_return.view` | RTV, vendor, gudang, GRN asal, jumlah, status, diajukan, umur | gudang | [19 §9](19-receipt-putaway.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `tugas-approval-terbuka` | `OpenApprovalTaskReport` | Tugas approval terbuka | `approval_rule.view` | approver, jenis dokumen, nomor, lapis, delegasi dari, ditugaskan, umur (jam), tenggat, lewat tenggat | gudang, jenis dokumen | [20 §9](20-approval.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `dokumen-menunggu-approval` | `PendingApprovalDocumentReport` | Dokumen menunggu approval | `approval_rule.view` | per jenis: dokumen menunggu, tugas terbuka, lewat tenggat, dokumen tertua, umur tertua | gudang | [20 §9](20-approval.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `waktu-putus-approval` | `ApprovalDecisionTimeReport` | Waktu putus approval | `approval_rule.view` | jenis dokumen, lapis, keputusan, setuju, tolak, rata-rata (jam), maksimum (jam) | gudang, jenis dokumen, rentang tanggal (putus) | [20 §9](20-approval.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `eskalasi-approval` | `ApprovalEscalationReport` | Eskalasi approval | `approval_rule.view` | waktu, jenis dokumen, nomor, lapis, dari approver, ke approver, oleh, sebab | gudang, jenis dokumen, rentang tanggal | [20 §9](20-approval.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `tren-akurasi` | `AccuracyTrendReport` | Tren akurasi stok | `count.view` | bulan, gudang, sesi, baris dihitung, baris cocok, akurasi %, selisih besar | gudang, rentang tanggal (bawaan 12 bulan) | [21 §9](21-opname-penyesuaian.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `top-selisih` | `TopVarianceReport` | Selisih opname terbesar | `count.view` | OPN, gudang, bin, item, lot/serial/potongan, sistem, hitung, selisih, selisih %, kelas, akar masalah (100 teratas) | gudang, rentang tanggal | [21 §9](21-opname-penyesuaian.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `akar-masalah` | `RootCauseReport` | Akar masalah selisih | `count.view` | akar masalah, jumlah baris, sesi, total kurang, total lebih, total selisih | gudang, rentang tanggal | [21 §9](21-opname-penyesuaian.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `adj-per-alasan` | `AdjustmentByReasonReport` | Penyesuaian per alasan | `adjustment.view` | alasan, jumlah ADJ, jumlah baris, total masuk, total keluar (jumlah, tanpa nilai uang) | gudang, rentang tanggal (posting) | [21 §9](21-opname-penyesuaian.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `trf-terbuka` | `OpenTransferReport` | Transfer terbuka | `transfer.view` | TRF, dari, ke, status, diminta, dikirim, diterima, diajukan, umur | gudang (asal atau tujuan) | [22 §9](22-retur-transfer.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `barang-dalam-perjalanan` | `InTransitStockReport` | Barang dalam perjalanan | `transfer.view` | gudang asal, item, lot/serial/potongan, kondisi, jumlah, satuan, SJ, tujuan, TRF, umur | gudang asal | [22 §9](22-retur-transfer.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `retur-per-proyek` | `ReturnByProjectReport` | Retur per proyek | `return.view` | proyek, RET, tanggal, status, item, diajukan, diterima, layak, rusak, offcut (panjang), waste | proyek, rentang tanggal (RET dibuat) | [22 §9](22-retur-transfer.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |
| `rusak-bin-retur` | `DamagedReturnBinReport` | Barang rusak di bin Retur | `return.view` | gudang, bin, item, lot/serial/potongan, jumlah, satuan, dokumen masuk, masuk, umur | gudang | [22 §9](22-retur-transfer.md#9-laporan--dashboard), [A-241](04-keputusan-dan-asumsi.md#a-241) |

Tidak ada kolom nilai uang di laporan mana pun ([D-07](04-keputusan-dan-asumsi.md#d-07)).

## 4. Mesin status

Tidak ada. Modul ini tidak memiliki dokumen berstatus.

## 5. Aturan bisnis yang berlaku

| Rujukan | Catatan implementasi |
|---|---|
| [BR-ACC-05](05-aturan-bisnis.md#br-acc) | Laporan memakai `Model::query()` tanpa `withoutGlobalScopes()` pada data utama, jadi cakupan gudang user berlaku di `daftar-gudang` dan `daftar-bin`. `UserRoleScopeReport` dan `ProjectListReport` memakai `withoutGlobalScopes()` **hanya** untuk memetakan id gudang ke nama / menghitung Gudang Site |
| [BR-GEN-09](05-aturan-bisnis.md#br-gen) | Setiap layar dan ekspor memeriksa `hasPermission($laporan->permission())`; tanpa izin → 403 |
| [D-07](04-keputusan-dan-asumsi.md#d-07) | Tidak ada nilai uang di definisi laporan maupun ekspor |
| AD-09 ([Arsitektur §2](08-arsitektur.md#2-keputusan-arsitektur)) | Ekspor Excel lewat `maatwebsite/excel`; satu kelas `ReportExport` |
| AD-10, NFR-14, [A-68](04-keputusan-dan-asumsi.md#a-68) | Berkas di disk `local` yang dipisah per company, batas 5 MB tersimpan, disajikan lewat route berotorisasi |
| [A-23](04-keputusan-dan-asumsi.md#a-23), [A-257](04b-asumsi-lanjutan.md#a-257) | Foto mentah ≤ 20 MB dikompres otomatis di server menjadi ≤ 5 MB (§6.4) |

## 6. Layar

### 6.1 Daftar laporan — `GET /reports` — `ReportController@index`

Kartu per laporan (judul, deskripsi, tombol *Buka*) untuk laporan yang boleh dibuka user. Kosong → "Belum ada laporan yang boleh Anda buka." View `resources/views/shared/reports/index.blade.php`. Menu *Laporan* di sidebar dan palet perintah.

### 6.2 Satu laporan — `GET /reports/{report}` — `ReportController@show` + `ReportViewer`

View `resources/views/shared/reports/show.blade.php` memuat komponen `ReportViewer` (`resources/views/livewire/shared/report-viewer.blade.php`).

| Bagian | Perilaku |
|---|---|
| Penyaring | Dibangun dari `filters()`: pilihan → select, tanpa pilihan → isian teks. Kunci `cari` → `<x-pilih>` dimuat sekaligus (gudang, klien, kategori, jenis dokumen); kunci `server` (tanpa `options`) → `<x-pilih server>` dengan daftar dari `Report::pilihanPenyaring($kunci)` = query lama (proyek dalam cakupan, semua vendor), dipakai isian awal dan `cariPilihan`; akun Klien tidak mendapat pilihan dari server ([A-396](04b-asumsi-lanjutan.md#a-396)). Nilai disimpan di query string; aksi `bersihkanFilter` mengosongkan semuanya. Penyaring ber-`required` ditandai `*` dan tabelnya menampilkan "Pilih … dulu" selama belum diisi ([A-330](04b-asumsi-lanjutan.md#a-330)) |
| Tabel | Kolom dari `columns()`; layar **dibatasi 500 baris** pertama, jumlah total ditampilkan |
| Ekspor | Tautan *Excel* ke `/reports/{report}/export` dan *Ekspor PDF* ke `/reports/{report}/pdf`, keduanya dengan penyaring yang sama |
| Izin | Diperiksa di `mount()` **dan** di setiap `render()` (403) |

### 6.3 Ekspor — `GET /reports/{report}/export` — `ReportController@export`

Mengunduh `<key>-YmdHis.xlsx` berisi **seluruh** baris (tanpa batas 500). Nilai penyaring dari query `filters[...]` dipaksa ke string. Setiap ekspor dicatat ke log aktivitas `report` (pelaku, kunci laporan, penyaring). `LoginLogReport` sendiri membatasi 5.000 baris terbaru.

**PDF — `GET /reports/{report}/pdf` — `ReportController@pdf`** (25 Sep 2026, [A-190](04-keputusan-dan-asumsi.md#a-190)). Satu format untuk semua laporan: view `shared/reports/pdf.blade.php` (judul, nama company, waktu cetak zona company, jumlah baris, penyaring yang dipakai, tabel seluruh baris) dirender `PdfRenderer` ke A4 mendatar dan dialirkan sebagai `<key>-YmdHis.pdf`. Izin sama dengan layar (403); dicatat ke log aktivitas `report` dengan `format = pdf`. Uji TC-RPT-05.

### 6.4 Berkas

| Route | Aksi | Izin |
|---|---|---|
| `POST /profile/signature`, `DELETE /profile/signature` | unggah/hapus tanda tangan sendiri (`ProfileController`) | user yang login |
| `POST /items/{item}/photo`, `DELETE /items/{item}/photo` | unggah/hapus foto item (`ItemPhotoController`, log aktivitas `master`) | policy `update` Item |
| `GET /files/signature/{user}` | alirkan tanda tangan | diri sendiri atau `user.view` |
| `GET /files/item-photo/{item}` | alirkan foto item | policy `view` Item |

Semua route di grup `auth` + `internal` di `routes/tenant.php`.

**Kompresi foto otomatis (26 Sep 2026, [A-23](04-keputusan-dan-asumsi.md#a-23), [A-257](04b-asumsi-lanjutan.md#a-257)).** Semua penyimpanan foto lewat `StoreUpload::handle()`, jadi kompresinya satu titik: foto item, tanda tangan berkas, lampiran (`AttachmentStore`: foto ISU, serah terima AST), inspeksi aset, BA waste, bukti terima SJ (`ProofFiles`: layar driver dan `/terima/{token}`), keberatan penerima, bukti bayar langganan, dan logo kop. Urutannya:

1. Mentah > 20 MB ditolak; MIME dibaca dari isi.
2. Gambar dikirim ke `ImageCompressor`: bila sisi terpanjang > 1920 px, ukuran > 2 MB, atau orientasi EXIF ≠ normal, gambar diputar, diperkecil ke 1920 px, lalu disimpan JPEG kualitas 80 (70/60 bila perlu). WEBP tetap WEBP. PNG transparan, tanda tangan, dan logo (`keepFormat`) tetap PNG; PNG buram menjadi JPEG. Selain itu berkas disimpan apa adanya.
3. Hasil > 5 MB ditolak. Ekstensi berkas mengikuti MIME hasil.

Tanda tangan kanvas (`handleDataUrl`) selalu PNG dan tidak dikompres. PDF tidak disentuh. `AttachmentStore` mencatat `size_bytes` dan `mime` dari berkas tersimpan. Validasi form memakai `StoreUpload::ATURAN_FOTO` (`image|mimes:jpg,jpeg,png,webp|max:20480`); `config/livewire.php` menaikkan batas unggah sementara Livewire ke 20 MB. php.ini perlu dinaikkan ([setup lokal](../00-setup-lokal.md#batas-unggah-php)).

### 6.5 Komponen pilihan — `<x-pilih>` ([A-383](04b-asumsi-lanjutan.md#a-383), [A-384](04b-asumsi-lanjutan.md#a-384))

Satu pola untuk semua kotak pilihan tunggal (pilihan ganda: `<x-pilih-tag>`, [A-350](04b-asumsi-lanjutan.md#a-350)). Hanya **cara memilih** yang berubah; nilai yang dikirim ke aksi sama dengan `<select>` biasa.

| Jenis daftar | Contoh | Pakai |
|---|---|---|
| (i) pendek / tetap | enum status, jenis, alasan, zona waktu, ≤ ±8 pilihan | `<select class="form-select">` biasa |
| (ii) master kecil–sedang | gudang, unit, jabatan, role, klien, kategori, satuan | `<x-pilih :options="...">` dimuat sekaligus (`group` untuk kelompok) |
| (iii) daftar besar | item, bin, vendor, pengguna, proyek | `<x-pilih server :options="$pilihan->awalDengan($nilai)">` + trait `CariPilihan` |

**Isian komponen:** `model` (juga baris `rows.3.item_id`), `options` (`value`, `text`, `badge`?, `sub`?, `group`?, `disabled`? — opsi redup yang tidak bisa dipilih, mis. batang di bin beku), `galat` (kunci galat bila beda dari `model`, mis. `batang` ↔ `rencana.batang`), `kosong`, `placeholder`, `label` + `wajib` (label terhubung ke kotak cari) atau `aria`, `live`, `disabled`, `kecil`, `dialog` (dropdown di `<body>`, di atas modal), `server` + `kunci` (daftar induk, mis. id gudang), `kelompok` (urutan kelompok tetap). Galat `@error(model)` tampil di bawah kotak dan diteruskan ke `aria-invalid`. Selama kata cari diketik, teks nilai terpilih disembunyikan; sisa ketikan dikosongkan saat kotak ditinggal ([A-390](04b-asumsi-lanjutan.md#a-390)). HP: tinggi sentuh ≥ 40 px, dropdown tidak keluar layar.

**Aturan keamanan cari ke server (wajib):**

1. **Satu query**: daftar ditulis sekali sebagai `Shared\Pilihan\Pilihan` (atau sumber baku `SumberPilihan::pengguna/proyek/proyekSemuaStatus/pemohon/item/vendor/bin`) dan dipakai render, `cariPilihan`, serta validasi simpan (`->aturan()`). Layar boleh mempersempit (`->saring()`), tidak memperluas.
2. `pilihanServer($model)` hanya mengembalikan daftar untuk model yang dinyatakan (lainnya `null` → hasil kosong) dan **mengulang otorisasi layar** seperti aksi simpan.
3. Cakupan BR-GEN-09/BR-ACC-05 ikut query (global scope `ScopedToUser`, `Project::dalamCakupan`); akun Klien tidak mendapat pengguna internal, vendor, atau bin; proyek hanya milik kliennya ([A-354](04b-asumsi-lanjutan.md#a-354)).
4. Nilai terpilih di luar cakupan tidak diberi label dan ditolak saat simpan; id bukan angka ("5abc") ditolak.
5. Minimal 2 huruf, ±30 hasil, 60 pencarian/menit per pengguna; tanpa route baru (method Livewire `#[Json]`, tanpa render).
6. Nilai yang diisi server (pindai, baris bergeser) dan belum ada di kotak diberi label lewat `labelPilihan($model, $nilai)` — `pilihanServer` yang sama, jadi nilai di luar daftar tetap tanpa label ([A-392](04b-asumsi-lanjutan.md#a-392)). Baris dinamis: isian awal `Pilihan::awalPerBaris`, `kunci` = jumlah baris.
7. Di sel tabel (`.table .nx-pilih`, `td .nx-pilih`) kotak minimal 14rem supaya teks nilai/placeholder tidak patah per kata di HP; tabel baris dokumen sudah bergulir mendatar ([A-393](04b-asumsi-lanjutan.md#a-393)).

## 7. Kejadian stok & integrasi

Tidak ada. Modul ini tidak menulis stok.

## 8. Notifikasi

Tidak ada.

## 9. Laporan & dashboard

Lihat §3.2 untuk laporan yang sudah terdaftar dan §13.4 untuk yang belum.

## 10. Kasus uji

Uji ada di `tests/Feature/Shared`. ID memakai akhiran huruf untuk varian dalam satu TC.

| ID | Given | When | Then | Rujukan |
|---|---|---|---|---|
| TC-RPT-01 | Admin Company dan Driver | `ReportRegistry::availableTo` | 44 laporan terdaftar; Admin membuka 44; Driver 13: `daftar-item`, `item-sementara`, `daftar-proyek`, `daftar-gudang`, `saldo-stok`, `mutasi-periode`, `kartu-stok`, `titik-pesan-ulang`, `daftar-pengiriman`, `short-pick`, `posisi-rusak-selisih`, `kinerja-pengiriman`, `rekap-pengiriman-klien` | BR-GEN-09 |
| TC-RPT-01b | Driver tanpa `user.view`; Admin Company | buka `/reports`, `/reports/daftar-item`, `/reports/log-login`, `/reports/log-login/export`; Admin buka `/reports/tidak-ada` (+ `/export`) | dua pertama 200; dua berikutnya 403; kunci tak dikenal 404 | BR-GEN-09 |
| TC-RPT-01c | Kepala Gudang bercakupan gudang CKG | isi `user-role-cakupan` | cakupan tampil sebagai "Gudang: Gudang Utama Cakung", bukan id | — |
| TC-RPT-01d | Data master demo (4 item) | `daftar-item` dengan jenis *Barang berkedaluwarsa*; jenis *Jenis khusus* | 1 baris (`SEMEN-PCC-50`) dari 4; 0 baris | [A-283](04b-asumsi-lanjutan.md#a-283) |
| TC-RPT-01e | Admin Company | ekspor `daftar-item` | 200, `content-type` spreadsheetml, nama berkas memuat `daftar-item` | AD-09 |
| TC-RPT-01f | Dua gudang, Kepala Gudang hanya di CKG | isi `daftar-gudang` | hanya CKG | BR-ACC-05 |
| TC-RPT-11 | Saklar `piece` mati; item lama per potong bersaldo | kolom *Saldo stok*, *Konversi & waste*, *Retur per proyek*; nyalakan saklar | tanpa jumlah potong, offcut, kerf (label *Lot / serial*), saldo pipa tetap terbaca; lalu kolom itu kembali | [A-284](04b-asumsi-lanjutan.md#a-284) |
| TC-RPT-12 | kabel 24 dari vendor, 8 kembali lewat retur penjualan dipilah rusak | isi `barang-bermasalah-vendor` | baris vendor × kabel: diterima 24, retur rusak 8, 33,3 %, dilacak "GRN, label" | [A-303](04b-asumsi-lanjutan.md#a-303) |
| TC-RPT-13 | Klien dengan satu proyek & satu Gudang Site; dua SJ (ke proyek 50 pcs dengan bukti 48/2/0, ke Gudang Site 30 pcs tanpa bukti) | isi tanpa klien; isi dengan klien; saring Tujuan & proyek; klien lain; buka layar, ekspor Excel & PDF; buka tanpa `shipment.view` | tanpa klien 0 baris; dua baris + satu baris TOTAL (dikirim 80, baik 48); SJ tanpa bukti berkolom `—`; saringan memisahkan dua jalur; proyek klien lain 0 baris; layar & kedua ekspor 200; tanpa izin 403; tidak ada kolom nilai uang | [A-329](04b-asumsi-lanjutan.md#a-329), [A-330](04b-asumsi-lanjutan.md#a-330), [D-07](04-keputusan-dan-asumsi.md#d-07) |
| TC-RPT-13d | SJ berangkat 10 pcs dan SJ disusun belum berangkat 7 pcs; pembaca bercakupan proyek lain milik klien yang sama | isi rekap; buka sebagai pembaca itu | hanya SJ berangkat; pembaca melihat kosong dan pilihan proyeknya hanya proyeknya | [A-351](04b-asumsi-lanjutan.md#a-351), BR-ACC-05 |
| TC-RPT-14 | Dua proyek; pemohon internal bercakupan satu proyek; vendor nonaktif | buka *Daftar REQ*, cari proyek, isi proyek lain lewat URL, cari penyaring enum; Admin di *Retur per proyek* & *Barang bermasalah per vendor*; driver mencari vendor | pemohon hanya proyeknya (awal & cari), proyek lain tanpa label, penyaring non-server kosong; Admin semua proyek; vendor nonaktif ditemukan; driver kosong | [A-396](04b-asumsi-lanjutan.md#a-396), BR-ACC-05 |
| TC-RPT-14b | Akun Klien tanpa penugasan proyek (id cakupan kosong) ber-`request.view`/`return.view` | buka *Daftar REQ* & *Retur per proyek*, cari & minta label proyek | isian awal kosong, hasil cari kosong, label null | [A-396](04b-asumsi-lanjutan.md#a-396), [A-354](04b-asumsi-lanjutan.md#a-354) |
| TC-RIW-01 | Item diubah (riwayat spatie `updated`) | buka tab Riwayat detail item | tampil "diubah", bukan "updated"; deskripsi buatan aplikasi tidak berubah | CLAUDE.md (UI Bahasa Indonesia) |
| TC-RPT-06 | Fixture transfer (stok awal, TRF → SJ diterima utuh), reservasi lunak 10 hari & baru, `reorder_point` 250 | buka & ekspor 11 laporan baru; `rows()` kartu stok, titik pesan ulang, reservasi menggantung, kinerja & daftar pengiriman; driver | semua 200; kartu memuat mutasi awal; selisih 150; hanya reservasi 10 hari; 1 dikirim & 1 utuh; driver boleh `kartu-stok`, 403 `short-pick` | A-232 |
| TC-RPT-02–05, 07–10 | — | — | dicatat di tempat lain: 02–05 laporan inti & PDF semua laporan ([27-pendukung-f1 §10](27-pendukung-f1.md), `CoreReportTest`); 07 Receipt/Putaway, 08 Approval, 10 Retur/Transfer (`ModuleReportTest`); 09 Count/Adjustment (`CountReportTest`) | A-190, A-241 |
| TC-PIL-01 | — | render `<x-pilih>` dengan kelompok, sub, badge, galat, mode server/kecil/dialog/disabled | label `for` kotak cari; `is-invalid` + pesan ber-id; kelompok urut Unit ini → Unit induk → Unit lain walau kosong; teks di-escape; kunci kotak ikut daftar (biasa) atau `kunci` (server) | [A-383](04b-asumsi-lanjutan.md#a-383) |
| TC-PIL-02 | Pengguna aktif, nonaktif, akun Klien | `cariPilihan` sumber pengguna oleh Admin; oleh Klien | hanya internal aktif; Klien mendapat kosong | [A-384](04b-asumsi-lanjutan.md#a-384) |
| TC-PIL-03 | Proyek A & B; pemohon bercakupan A; Klien A | cari "beta"/"CARI" | pemohon & Klien hanya A walau B cocok; Admin keduanya | A-384, A-354 |
| TC-PIL-04 | Item aktif & nonaktif | cari; 1 huruf; `%` | hanya aktif; kosong; kosong | A-384 |
| TC-PIL-05 | Vendor aktif & nonaktif | cari oleh staf; oleh Klien | hanya aktif; kosong | A-384, A-310 |
| TC-PIL-06 | Bin di gudang A & B; staf bercakupan A | cari bin A; bin B lewat id gudang dari browser; Klien | A saja; kosong; kosong | A-384, BR-ACC-05 |
| TC-PIL-07 | Pemohon bercakupan A | label & `awalDengan` proyek B; simpan B; simpan A | tanpa label; ditolak; tersimpan | A-384 |
| TC-PIL-08 | Admin | cari model tak dinyatakan; 61 pencarian dalam semenit | kosong; ke-61 kosong | A-384 |
| TC-PIL-09 | Pemohon bercakupan P1; item aktif & nonaktif | `labelPilihan` untuk item aktif, item nonaktif, proyek di luar cakupan, model tak dinyatakan, id "5abc" | hanya item aktif berlabel; lainnya null | [A-392](04b-asumsi-lanjutan.md#a-392) |
| TC-PIL-10 | — | render `<x-pilih>` dengan opsi `disabled`, `galat="rencana.batang"`, dan model baris `isian.7.bin_id` | `<option … disabled>`; tanda & pesan galat dari kunci `galat` (galat pada model saja tidak menandai); id & kunci unik per baris | [A-383](04b-asumsi-lanjutan.md#a-383) |
| TC-FIL-01 | Staf gudang | unggah lalu hapus tanda tangan | path tersimpan, bisa dibuka lewat `/files/signature/{id}`, lalu null | A-68 |
| TC-FIL-01b | Tanda tangan milik staf | Driver lalu Admin membukanya | Driver 403, Admin 200 | A-68 |
| TC-FIL-01c | — | unggah PDF sebagai tanda tangan | galat validasi, tidak tersimpan | NFR-14 |
| TC-FIL-01d | — | unggah gambar 21.000 KB | galat validasi, tidak tersimpan | NFR-14, A-257 |
| TC-FIL-01e | Admin Company, item `BAUT-M12` | unggah, buka, hapus foto item | tersimpan, 200, lalu null | A-68 |
| TC-FIL-01f | Staf gudang (hanya `item.view`) | unggah foto item | 403 | BR-GEN-09 |
| TC-FIL-01g | — | `StoreUpload::handle` langsung | path fisik memuat pengenal `tenant` dan folder `signatures` | A-68 |
| TC-FIL-02 | Tenancy aktif | `asset()` untuk build Vite dan logo | URL tidak memuat `/tenancy/assets/` | A-68 |
| TC-FIL-02b | — | buka halaman login company | 200; logo dari `/img/logo.svg`, tidak ada `/tenancy/assets/` | A-68 |
| TC-FIL-03 | Admin Company, item `BAUT-M12` | unggah foto JPEG 3000×2250 > 5 MB | diterima; tersimpan JPEG ≤ 5 MB, 1920×1440 | A-23, A-257 |
| TC-FIL-03b | Lampiran ISU; SJ | foto besar lewat `AttachmentStore` dan `ProofFiles` (+ tanda tangan kanvas) | `size_bytes`/`mime` = berkas tersimpan; foto ≤ 5 MB dan ≤ 1920 px; tanda tangan tetap PNG | A-23, A-257 |
| TC-FIL-03c | — | JPEG berorientasi EXIF 6 | terbaca tanpa ekstensi `exif`; hasil diputar (lebar ↔ tinggi) | A-257 |
| TC-FIL-03d | — | PNG transparan besar; tanda tangan PNG lewat Profil; PNG buram besar | PNG 1920 px dengan alpha; tetap PNG; menjadi JPEG ≤ 5 MB | A-257 |
| TC-FIL-03e | — | PDF; foto kecil; mentah > 20 MB | PDF & foto kecil byte-identik; > 20 MB ditolak; batas Livewire & `ATURAN_FOTO` 20 MB | A-257 |

## 11. Di luar lingkup modul ini

Grafik/dashboard per peran; impor Excel dan pratinjau `import_batches` (AD-09); kompresi foto sisi klien di PWA (AD-10; kompresi server sudah ada, §6.4); penyimpanan S3 produksi ([O-14](04-keputusan-dan-asumsi.md#o-14)).

## 12. Definisi selesai

- [x] Kontrak `Report`, `ReportRegistry`, `ReportExport`, `ReportViewer`
- [x] Tiga route laporan dan menu *Laporan*
- [x] Laporan §9 modul Access, Master, Warehouse (7 definisi)
- [x] `StoreUpload` dan route berkas berotorisasi
- [x] TC-RPT-01 dan TC-FIL-01 (beserta variannya) lulus
- [x] Laporan §9 modul Issue dan Aset (`material-per-proyek`, `aset-dipinjamkan`)
- [x] Lima laporan inti Blueprint §6.9a ([A-190](04-keputusan-dan-asumsi.md#a-190), TC-RPT-02–04)
- [x] Laporan §9 modul Stock, Request, Picking/Shipment — 11 definisi ([A-232](04-keputusan-dan-asumsi.md#a-232), TC-RPT-06)
- [x] Laporan §9 modul 19–22 — 16 definisi ([A-241](04-keputusan-dan-asumsi.md#a-241), TC-RPT-07–10)
- [x] Ekspor PDF semua laporan (§6.3, [A-190](04-keputusan-dan-asumsi.md#a-190), TC-RPT-05)
- [x] Kunci laporan tak dikenal → 404 (TC-RPT-01b)
- [x] Kompresi foto otomatis di server (§6.4, [A-257](04b-asumsi-lanjutan.md#a-257), TC-FIL-03)
- [x] Rekap pengiriman per klien & penyaring wajib ([A-329](04b-asumsi-lanjutan.md#a-329), [A-330](04b-asumsi-lanjutan.md#a-330), TC-RPT-13)
- [ ] Penyaring §9 yang belum ada (§13.1 no. 2)
- [ ] Kolom *Rencana* `[F2]` di *Material per proyek* (§13.4)

## 13. Status & catatan implementasi (24 September 2026)

Kode modul ini dibangun lebih dulu daripada dokumennya, bersamaan dengan modul Access, Master, dan Warehouse. Dokumen ini ditulis belakangan untuk mencatat apa yang ada; tidak ada fitur yang ditambahkan di sini.

### 13.1 Penyimpangan dari dokumen lain

1. ~~**Hanya ekspor Excel.** [Blueprint §6.9a](01-blueprint.md#69a-laporan-inti-fase-1) menuntut ekspor Excel **dan PDF**; kode baru menyediakan Excel.~~ **Sudah diperbaiki 25 Sep 2026:** ekspor PDF semua laporan (§6.3, [A-190](04-keputusan-dan-asumsi.md#a-190), TC-RPT-05).
2. **Penyaring berbeda dari §9 modul asal:**
   - *User × role × cakupan* ([10-access §9](10-access.md#9-laporan--dashboard)) meminta penyaring role, gudang/proyek, status; kode menyediakan status pengguna dan **jenis** cakupan (bukan gudang/proyek tertentu), tanpa penyaring role.
   - *Log login 30 hari* meminta penyaring user; kode menyediakan *email mengandung*.
   - *Daftar item* ([11-master §9](11-master.md#9-laporan--dashboard)) meminta penyaring kategori; kode menyediakan status, mode pelacakan, kepemilikan (tanpa kategori). Kolom *stok minimum* ditambahkan.
   - *Item sementara* meminta kolom *dibuat dari REQ*; kode menampilkan tanggal dibuat, kategori, dan satuan dasar, tanpa nomor REQ asal.
   - *Daftar gudang* ([12-warehouse §9](12-warehouse.md#9-laporan--dashboard)) meminta penyaring proyek; kode hanya tipe dan status.
   - *Daftar bin* menambah kolom penegakan kapasitas, kapasitas berat, proyek, dan penanda hitung.
3. ~~**Kunci laporan tidak dikenal menghasilkan 500, bukan 404.** `ReportRegistry::find()` melempar `InvalidArgumentException` dan tidak dipetakan di `bootstrap/app.php`.~~ **Sudah diperbaiki 25 Sep 2026:** `ReportRegistry::find()` melempar `NotFoundHttpException`, yang dipakai layar, ekspor Excel, dan PDF, jadi ketiganya menjawab 404 (TC-RPT-01b menguji layar dan ekspor Excel).
4. **Berkas disimpan sebagai path di model pemilik** untuk berkas lama (tanda tangan, foto item, bukti terima, WST); lampiran dokumen baru memakai tabel `attachments` ([A-238](04-keputusan-dan-asumsi.md#a-238)).

### 13.2 Keputusan implementasi

1. **Laporan sebagai data, bukan layar.** Modul baru menambahkan kelas di `Reports/Definitions` dan mendaftarkannya di `ReportRegistry::REPORTS`; tidak membuat route atau komponen sendiri.
2. **Layar dibatasi 500 baris, ekspor tidak.** Laporan besar tidak membuat halaman berat, tetapi data lengkap tetap bisa diambil.
3. **Tipe berkas dibaca dari isi**, bukan dari nama atau header klien. Hanya `image/jpeg`, `image/png`, `image/webp`; berkas lama dengan ekstensi lain dibuang saat diganti. Controller juga memvalidasi `StoreUpload::ATURAN_FOTO` (`image|mimes:jpg,jpeg,png,webp|max:20480`; sebelum 26 Sep 2026 `max:5120`), lalu foto dikompres menjadi ≤ 5 MB ([A-257](04b-asumsi-lanjutan.md#a-257)).
4. **Disk `local` sudah dipisah per company** oleh `FilesystemTenancyBootstrapper` (`config/tenancy.php`), sesuai [A-68](04-keputusan-dan-asumsi.md#a-68); berkas tidak pernah ada di `public/`.

### 13.3 Layar yang sudah ada

| Layar | Route | Komponen | Isi |
|---|---|---|---|
| Daftar laporan | `/reports` | view `shared.reports.index` | Kartu laporan yang boleh dibuka |
| Satu laporan | `/reports/{report}` | Livewire `ReportViewer` | Penyaring, tabel ≤ 500 baris, tombol ekspor |
| Ekspor | `/reports/{report}/export` | `ReportController@export` | Unduhan `.xlsx` |
| Ekspor PDF | `/reports/{report}/pdf` | `ReportController@pdf` | PDF A4 mendatar |

Uji: `tests/Feature/Shared/ReportTest.php` (TC-RPT-01, 01b–01f), `PieceSwitchTest.php` (TC-RPT-11), `CoreReportTest.php` (TC-RPT-02–05), `ExtendedReportTest.php` (TC-RPT-06), `ModuleReportTest.php` (TC-RPT-07, 08, 10), `CountReportTest.php` (TC-RPT-09) dan `tests/Feature/Shared/FileUploadTest.php` (TC-FIL-01, 01b–01g), `tests/Feature/Shared/ImageCompressionTest.php` (TC-FIL-03, 03b–03e), `tests/Feature/Shared/TenantAssetUrlTest.php` (TC-FIL-02, 02b).

**Aset aplikasi vs berkas company (24 Sep 2026).** `config/tenancy.php` `asset_helper_tenancy` dimatikan. Saat aktif, `asset()` dan `@vite` di halaman company menunjuk `/tenancy/assets/…`, yang dilayani dari storage company. Akibatnya build Vite dan logo 404 dan seluruh halaman company tampil tanpa gaya. Aset aplikasi bersifat global (`public/`). Berkas milik company tidak pernah lewat `asset()`: berkas itu disajikan route berotorisasi `/files/…` ([A-68](04-keputusan-dan-asumsi.md#a-68)), jadi tidak ada yang hilang.

**Dokumen terkait (26 Sep 2026, [A-252](04b-asumsi-lanjutan.md#a-252)):** `Shared\Support\DocumentLineage` menelusuri asal & turunan satu dokumen dari tautan skema yang ada (FK, `source_type/source_id`, baris SJ ↔ PCK/RET/TRF, catatan pemesanan ↔ PO ↔ GRN, pembalik ISU/CNV/ADJ, rantai AST) dan komponen `<x-related-documents>` menampilkannya di detail REQ, PCK, SJ, GRN, PUT, RTV, RET, TRF, PRQ, PO, ISU, CNV, ADJ, AST (bukan portal). Hanya dokumen yang lolos policy `view` **dan** global scope cakupan (BR-ACC-05); PO hanya bagi `po.view`. `Shared\Support\ProjectDocumentTimeline` membangun linimasa dokumen proyek untuk tab *Riwayat* hub. Tabel `document_timelines` (BR-GEN-05) tetap belum dibangun. Uji TC-DOC-01–03.

### 13.4 Sisa pekerjaan

~~Laporan §9 Stock, Request, Picking/Shipment~~ — **selesai 25 Sep 2026** ([A-232](04-keputusan-dan-asumsi.md#a-232)): sebelas definisi baru di §3.2 memakai penyaring standar `Concerns\PeriodFilter` (rentang tanggal zona company, gudang dalam cakupan); uji TC-RPT-06 (`ExtendedReportTest`).

*Material per proyek* ([23-pemakaian §9](23-pemakaian.md#9-laporan--dashboard)) terdaftar sejak modul Issue; kolom *Dikonversi*, *Hasil konversi*, *Waste*, *Waste didisposisi* ditambah modul Konversi & Waste; *Rencana* `[F2]`-nya belum.

Masih belum: penyaring §9 yang hilang (§13.1 no. 2). Ekspor PDF ada sejak 25 Sep 2026 (§6.3). Kunci laporan tidak dikenal kini **404** (`ReportRegistry::find` melempar `NotFoundHttpException`, 25 Sep 2026; TC-RPT-01b). Prompt lanjutan: [prompts/16-shared](../prompts/16-shared.md).

### 13.6 Deskripsi riwayat bawaan (28 September 2026)

`Shared\Support\ActivityText::label()` menampilkan deskripsi bawaan spatie/activitylog `created`/`updated`/`deleted`/`restored` sebagai "dibuat"/"diubah"/"dihapus"/"dipulihkan" di semua daftar riwayat (20 tampilan detail dokumen, item, gudang, pengguna, company). Nilai di basis data tetap. Uji TC-RIW-01.
