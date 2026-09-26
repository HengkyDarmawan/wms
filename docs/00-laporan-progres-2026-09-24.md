# Laporan Progres — 24 September 2026

**Versi:** 1.24
**Tanggal:** 26 September 2026
**Status:** potret keadaan setelah modul Picking/Shipment; v1.2: modul Receipt/Putaway selesai (363 uji hijau); v1.3: modul Approval selesai (392 uji hijau); v1.4: modul Count/Adjustment selesai (428 uji hijau); v1.5: modul Return/Transfer selesai (461 uji hijau); v1.7: modul Issue (pemakaian material di site) selesai (493 uji hijau); v1.8: modul Konversi & Waste selesai di mesin rumah XAMPP3 (513 uji hijau); v1.9: modul Aset dipinjamkan selesai (525 uji hijau); v1.10: modul Purchase Request selesai (537 uji hijau); v1.11: modul Platform penuh selesai (546 uji hijau); v1.12: Pendukung Fase 1 selesai (569 uji hijau); v1.13: tinjauan kode + 2FA Super Admin, pindai, pengingat tagihan (579 uji hijau, §5.4); v1.14: sesi kantor — pindai REQ/ISU, impor vendor & saldo awal, Purchasing inti Fase 1b, landing page (§5.5); v1.15: putaran navigasi, hub proyek, konversi per jenis, celah F1 (§5.6); v1.16: sesi rumah — lingkungan XAMPP3 & notifikasi §8 Sisa Fase 1 (§5.7); v1.17: lampiran generik (§5.7); v1.18: override bin beku & penuaan penggantian (§5.7); v1.19: laporan §9 modul 19–22 & short pick TRF (§5.7); v1.20: butir 2f (§5.7); diperbarui setiap modul selesai; v1.6: modul Template dokumen & label selesai (476 uji hijau); v1.21: sesi kantor 26 Sep 2026 — keputusan pemilik produk atas asumsi ⚠ dan tujuh tugas turunannya (§5.8, 654 uji hijau); v1.22: kompresi foto otomatis A-23/A-257 (§5.9, 659 uji hijau); v1.23: impor struktur gudang A-258 (§5.10, 663 uji hijau); v1.24: §1 ditandai sebagai potret 24 Sep, baris asumsi & kalimat penutupnya diperbarui
**Dokumen terkait:** [README](README.md) · [Setup lokal §5](00-setup-lokal.md#5-skenario-uji-manual) · [Blueprint §18](wms/01-blueprint.md#18-peta-modul--fase-rilis) · [Arsitektur §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4) · [Keputusan & Asumsi](wms/04-keputusan-dan-asumsi.md)

Semua skenario di dokumen dijalankan di aplikasi sungguhan dengan data demo yang baru di-seed: delapan skenario uji manual lewat Chrome headless, 321 uji otomatis, dan pencocokan setiap kasus uji `TC-xx` di spesifikasi dengan ujinya. Hasilnya dipakai untuk memetakan seberapa jauh Fase 1 sudah berjalan.

---

## 1. Ringkasan

> **Potret 24 Sep 2026** (setelah modul Picking/Shipment). Angka uji, cakupan TC, dan bug di tabel ini tidak diperbarui; keadaan terbaru ada di §4 (22 dari 24 butir selesai, 1 stub, 1 sebagian) dan §5.8–§5.10 (663 uji hijau).

| Ukuran | Hasil |
|---|---|
| Skenario E2E [00-setup-lokal §5](00-setup-lokal.md#5-skenario-uji-manual) | **9 dari 9 langkah lulus**, 0 error console |
| Uji otomatis | **321 lulus / 1.836 asersi**, 0 gagal (MariaDB 10.4) |
| Kasus uji `TC-xx` di spesifikasi 10–17 | **178 dari 178 punya uji otomatis**, semuanya lulus |
| Butir Fase 1 di [Blueprint §18](wms/01-blueprint.md#18-peta-modul--fase-rilis) | **13 selesai · 6 sebagian · 4 belum dibangun** (dari 23, satu 'selesai' berupa stub; v1.2 Penerimaan/QC/put-away/RTV; v1.3 Approval engine; v1.4 Stock opname; v1.5 Retur & transfer; v1.6 Template dokumen & label; v1.7 Pemakaian material di site; v1.8 Konversi material, offcut, waste; v1.9 Aset dipinjamkan; v1.10 Purchase Request manual; v1.11 Platform, trial, tagihan manual; v1.12 strategi pengambilan, notifikasi, laporan & dashboard, wizard, impor Excel, PWA) |
| Bug baru dari E2E | **1 berat** (§5.1), 1 ringan (§5.2) |
| Asumsi menunggu validasi | 26 Sep 2026: 31 asumsi ⚠ + A-111, A-116 diputus (§5.8), tinjauan asumsi tanpa ⚠ berjalan; sisa **106** (A-84 dst. + A-257, A-258) — [daftar kerja](00-tinjauan-asumsi-2026-09-25.md) |

Singkatnya: saat potret ini, alur keluar **REQ → approval → picking → surat jalan → bukti terima → selisih** berjalan dari ujung ke ujung, sedangkan barang masuk (GRN), approval berlapis, opname, retur/transfer, pemakaian, konversi, dan aset belum dibangun. Semuanya selesai sejak v1.2–v1.12 (§4).

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
| Login lokal, user, role & permission, cakupan | ✅ Selesai | [10-access](wms/10-access.md); 2FA (juga Super Admin, A-200), perangkat, akses dukungan (tautan sekali pakai, A-199) |
| Struktur organisasi | ✅ Selesai | [10-access](wms/10-access.md) |
| Master data, UoM dinamis, gudang & lokasi rak/bin | ✅ Selesai | [11-master](wms/11-master.md), [12-warehouse](wms/12-warehouse.md); label barcode selesai (18), ukuran final menunggu O-09; hub proyek `/projects/{id}` (A-228); Pengaturan company `/settings/company` (A-230) |
| Picking, pengiriman, bukti terima, DSC | ✅ Selesai | [15-picking-shipment](wms/15-picking-shipment.md); halaman penerima bertoken `/terima/{token}` + foto & tanda tangan bukti terima (A-231); OTP otomatis menunggu O-15 |
| Rencana kebutuhan material (stub) | ✅ Stub | tabel ada, layar Fase 2 |
| Platform, tenancy, trial, tagihan manual | ✅ Selesai | [17-platform-login](wms/17-platform-login.md) v0.2: buat company otomatis (DB, data acuan, undangan Admin), paket & trial, tagihan H-7 + bukti bayar + verifikasi, siklus harian jatuh tempo/tangguh/akhir, penangguhan manual, flag fitur (stub), masuk lewat akses dukungan hanya-baca; pengingat tagihan menunggu notifikasi, 2FA Super Admin belum |
| Kartu stok, reservasi dua tahap, strategi pengambilan | ✅ Selesai | ledger dan reservasi; alokasi PCK FIFO/FEFO/sisa potongan/manual, lot kedaluwarsa dilewati, alokasi keras tidak ganda ([27-pendukung-f1](wms/27-pendukung-f1.md), A-185) |
| Permintaan (internal + portal, non-katalog, gudang sumber) | ✅ Selesai | alur jalan; approval berlapis; konfirmasi/keberatan terima pemohon + konfirmasi otomatis ([27-pendukung-f1](wms/27-pendukung-f1.md), A-188) |
| Notifikasi in-app & email | ✅ Selesai | lonceng, halaman & preferensi per kejadian, email opsional, 20 kejadian (termasuk pengingat tagihan langganan, A-202; §8 modul 11/13/14/25 sejak A-233–A-237), pengingat harian ([27-pendukung-f1](wms/27-pendukung-f1.md), A-189); WhatsApp [F2] |
| Laporan & dashboard | ✅ Selesai | 41 laporan — 14 + 11 laporan §9 Stock/Request/Shipment (A-232) + 16 laporan §9 modul 19–22 (A-241) — termasuk laporan inti Blueprint §6.9a, ekspor Excel & PDF, Beranda antrean pekerjaan ([27-pendukung-f1](wms/27-pendukung-f1.md), A-186, A-190); grafik/tren [F2] |
| Integrasi Purchasing & Akuntansi | 🟡 Sebagian | outbox kejadian stok jalan; **Purchasing inti Fase 1b selesai** ([purchasing/02](purchasing/02-purchasing-inti.md): PO dari PRQ dengan harga beli, harga beli vendor, approval nilai PO, PO → catatan pemesanan → GRN, batal & tutup sisa, cetak PO; A-208–A-218); **Purchase Request manual selesai** ([26-purchase-request](wms/26-purchase-request.md): PRQ dari backorder REQ / titik pesan ulang / manual, approval opsional, catatan pemesanan per vendor, GRN merujuk catatan, reservasi ke REQ penunggu); endpoint masuk Purchasing `[F3]` belum |
| Wizard setup awal company | ✅ Selesai | 8 langkah dari data + persetujuan ketentuan sementara (O-11) ([27-pendukung-f1](wms/27-pendukung-f1.md), A-191) |
| Impor data master dari Excel | ✅ Selesai (lingkup F1) | item, proyek + klien baru, vendor, dan saldo awal lewat ADJ (semua-atau-tidak, A-207); bin lewat *Buat bin massal* ([27-pendukung-f1](wms/27-pendukung-f1.md), A-192) |
| Penerimaan (GRN), QC, put-away, RTV | ✅ Selesai | [19-receipt-putaway](wms/19-receipt-putaway.md): GRN vendor manual & transfer masuk, QC per baris, PUT dengan saran bin, RTV; cross-dock masih saran ([A-83](wms/04-keputusan-dan-asumsi.md#a-83)); GRN retur sejak modul Retur |
| Pemakaian material di site (ISU) | ✅ Selesai | [23-pemakaian](wms/23-pemakaian.md): ISU dari bin penyimpanan Gudang Site (habis pakai saja), konfirmasi → `material_consumed`, ISU pembalik dengan approval lapis minimum, laporan Material per Proyek dari kartu stok, cetak Bukti Pemakaian; foto pemakaian menunggu lampiran ([A-117](wms/04-keputusan-dan-asumsi.md#a-117)–[A-119](wms/04-keputusan-dan-asumsi.md#a-119), [A-150](wms/04-keputusan-dan-asumsi.md#a-150)–[A-152](wms/04-keputusan-dan-asumsi.md#a-152)) |
| Retur & transfer | ✅ Selesai | [22-retur-transfer](wms/22-retur-transfer.md): TRF antar gudang, antar proyek, dan antar titik dalam proyek (jalur ringan A-50), TRF otomatis dari backorder REQ sampai REQ terpenuhi lewat gudang tujuan, PCK/SJ/GRN transfer, TRF selesai saat GRN tujuan selesai; RET dari Gudang Site, aset On-site, barang terkirim ke klien, dan barang rusak ditinggal ekspedisi, SJ balik dari Gudang Site, GRN retur ke bin Retur, pemilahan layak/rusak/offcut/waste dengan `goods_returned`/`asset_returned`; portal klien; pemeriksaan aset menunggu modul Aset ([A-106](wms/04-keputusan-dan-asumsi.md#a-106)–[A-116](wms/04-keputusan-dan-asumsi.md#a-116)) |
| Konversi material, offcut, waste | ✅ Selesai | [24-konversi-waste](wms/24-konversi-waste.md): form per jenis dengan hitung otomatis (A-229: Potong satu batang → ukuran × jumlah, kerf & sisa otomatis; ganti kemasan susut → waste; rakit/bongkar bebas), neraca ukuran di server, offcut < minimum otomatis waste, potongan baru bersilsilah, approval opsional (hanya bila ada aturan), CNV pembalik (BR-CNV-05); BA waste dibuang/dijual scrap/dipakai ulang dengan bukti foto atau nomor BA; cetak Bukti Konversi & BA Waste; kolom konversi & waste di laporan Material per proyek. Resep `[F2]` stub |
| Aset dipinjamkan | ✅ Selesai | [25-aset](wms/25-aset.md): AST otomatis dari SJ aset & GRN retur, jatuh tempo & meter keluar, pemeriksaan grade + skor + catatan komponen + foto sebelum dipilah, state aset mengikuti kartu stok (BR-AST-01), aset hilang → ADJ `asset_lost` → dihapuskan, laporan aset dipinjamkan & sisa umur, cetak BA Serah Terima Aset. Jadwal maintenance `[F2]` stub; notifikasi jatuh tempo menunggu modul notifikasi |
| Approval engine | ✅ Selesai | [20-approval](wms/20-approval.md): aturan per jenis dokumen tanpa nilai uang, lapis & cara putus, SoD, snapshot, delegasi, eskalasi terjadwal + manual, simulasi, riwayat; REQ, RTV, ADJ, OPN, TRF, dan RET tersambung — sejak v1.10 semua jenis dokumen Katalog (termasuk ISU, CNV, WST, PRQ); WhatsApp stub Fase 2a; notifikasi stub ([A-91](wms/04-keputusan-dan-asumsi.md#a-91)) |
| Stock opname | ✅ Selesai | [21-opname-penyesuaian](wms/21-opname-penyesuaian.md): sesi bulanan/tahunan/ad-hoc/pemeriksaan mendadak, pembekuan bin, hitung buta di halaman ramah HP, toleransi ganda, hitung ulang orang berbeda, akar masalah, approval tingkat sesi (Auditor untuk tahunan/audit), ADJ per gudang diposting lewat buku besar, kunci periode bulanan, penanda hitung; ADJ manual dua lapis & pembalik; override SJ mendesak dan dashboard tren belum ([A-95](wms/04-keputusan-dan-asumsi.md#a-95)–[A-105](wms/04-keputusan-dan-asumsi.md#a-105)) |
| Template dokumen & label | ✅ Selesai | [18-template-dokumen-label](wms/18-template-dokumen-label.md); editor template [F2]; ukuran label menunggu O-09 (A-120); Surat Transfer, Bukti Retur, PRQ, GRN sejak 25 Sep (A-232) |
| PWA (installable, scan kamera, draf lokal) | ✅ Selesai | manifest + service worker + halaman offline, pindai kamera (BarcodeDetector), draf hitung opname & bukti terima ([27-pendukung-f1](wms/27-pendukung-f1.md), A-193); pindai di pencarian item/saldo/aset, bin tujuan put-away (A-201) dan bin → item di PCK (A-203), form REQ & ISU (A-206); offline penuh [F2] |
| Landing page produk | ✅ Selesai | Part 5: [30-landing-page](wms/30-landing-page.md) — Blade + Alpine, gaya indonesia.travel, paket dari DB, *Minta demo*, masuk ke company (A-220–A-225); brand & harga menunggu O-07/O-08 |
| Navigasi & rapi UI | ✅ Selesai | sidebar 12 grup lipat + filter menu (A-227), font Inter & warna tema, teks tanpa kode internal, tanggal-jam zona company, beranda portal klien dengan Stok On-site (§5.6) |

Urutan pembangunan berikutnya menurut [Arsitektur §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4) (Receipt/Putaway, Approval, Count/Adjustment, Return/Transfer, Issue, Conversion/Waste, Asset, PurchaseRequest, Platform, dan Pendukung F1 sudah selesai; RTV sudah ada sejak Receipt): penutup (E2E alur panjang, tinjauan asumsi).

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
- guard penutupan proyek BR-PRJ-02 belum ada ([11-master §13.4](wms/11-master.md#135-sisa-pekerjaan-modul-ini)).

### 5.4 Tinjauan kode 25 Sep 2026 (malam)

Tinjauan baca-kode atas berkas berisiko Platform & Pendukung. Semua temuan diperbaiki dengan uji; pilihan perilaku dicatat sebagai asumsi *Perlu validasi*.

| Temuan | Perbaikan | Rujukan |
|---|---|---|
| Galat SMTP saat notifikasi approval membatalkan keputusan approval | email dikirim setelah commit, galat hanya dicatat | A-199, TC-NTF-05 |
| Bayar setelah ditangguhkan → tagihan berikutnya langsung jatuh tempo | periode baru mulai hari verifikasi; jatuh tempo tagihan tidak pernah di masa lalu | A-195, TC-PLT-12 |
| Mode hanya-baca (ditangguhkan/diakhiri/akses dukungan) memblokir cari & halaman Livewire | update properti & pindah halaman lolos, aksi tetap 403 | A-196, TC-ACC-28g |
| Tautan akses dukungan bisa dipakai ulang 5 menit | nonce sekali pakai | A-199, TC-PLT-11 |
| Keberatan pada SJ gabungan bisa menyentuh baris REQ lain; kirim ganda | baris dibatasi ke REQ sendiri + kunci | A-197 |
| `still_needed` keberatan klien hilang diam-diam | baris REQ dibuka lagi; REQ `completed` → REQ baru | A-198, TC-REQ-33 |
| Checklist penutupan proyek melewatkan REQ menunggu keputusan & SJ disiapkan | diperluas, diperiksa dalam transaksi | A-187, TC-MST-20 |

Tinjauan kedua atas perbaikan malam itu:

| Temuan | Perbaikan | Rujukan |
|---|---|---|
| Baris REQ yang pernah punya PCK tidak pernah dipetik lagi (kurang ambil, `reship`, keberatan) → REQ tertahan | PCK memakai sisa terhitung | A-204, TC-PCK-17 |
| Kode 2FA Super Admin bisa ditebak dengan login ulang | kode salah ikut hitungan kunci akun | A-200, TC-PLT-13 |
| DSC bisa diselesaikan dua kali; konfirmasi otomatis bisa menimpa keberatan | kunci + periksa ulang | A-197 |
| Draf REQ proyek tertutup bisa diajukan; bin Gudang Site tetap aktif | tolak BR-PRJ-01; bin ikut nonaktif | A-187, TC-MST-20 |
| Pindai PCK item berlacak tidak memeriksa lot/serial | wajib nomor lot/serial/potongan | A-203, TC-PCK-18 |
| Satu company tanpa database menghentikan job harian semua company | lewati `provisioning`/`terminated`, galat per company | `OperatingCompanies` |

Sisa: `from_stock_status` baris lama tidak diisi balik (belum ada data produksi, A-194); kode TOTP yang dipakai ulang kini ditolak dan kode 2FA salah ikut kunci akun, juga untuk user tenant (A-205).

### 5.5 Sesi kantor 25 Sep 2026

| Pekerjaan | Hasil | Rujukan |
|---|---|---|
| Pindai di form REQ & ISU | kolom *Pindai item/barang*; `ScanCode` membaca kode, barcode, QR item/lot, nomor lot/serial/potongan | A-206, TC-REQ-34, TC-ISU-18 |
| Impor vendor & saldo awal | kartu impor baru; saldo awal = ADJ manual per gudang beralasan *Saldo awal*, tetap approval | A-207, TC-MST-24, TC-ADJ-12 |
| Purchasing inti Fase 1b | PO dari PRQ, harga beli vendor, approval nilai PO, PO → catatan pemesanan → GRN, ETA, batal, tutup sisa, cetak PO | [purchasing/02](purchasing/02-purchasing-inti.md), A-208–A-218, TC-PO-01–10, TC-VPR-01 |
| Landing page (Part 5) | halaman produk di domain pusat, paket, *Minta demo*, masuk ke company | [30-landing-page](wms/30-landing-page.md), A-220–A-225, TC-LND-01–06 |

Verifikasi 25 Sep 2026 (XAMPP kantor, MariaDB 10.4.27): **600 uji hijau / 6.279 asersi**; `_verify.py` OK; E2E pada demo segar `alur-req-sj` 9/9, `alur-pendukung` 8/8 (P8 = PO lewat layar), `ui-check` 74 cek termasuk landing & layar Purchasing — 0 error console.

Belum: keputusan pemilik produk atas kolom *Keputusan* di [tinjauan asumsi](00-tinjauan-asumsi-2026-09-25.md) (masih kosong); isi balik `from_stock_status` (A-194) menunggu data produksi; bagian Akuntansi sengaja tidak dikerjakan.

### 5.6 Putaran navigasi, hub proyek, konversi, celah F1 — 25 Sep 2026 (sore)

| Pekerjaan | Hasil | Rujukan |
|---|---|---|
| Hub proyek + sidebar | `/projects/{id}`: ringkasan, tombol aksi (REQ/TRF/ISU/CNV/RET prefill), 9 tab, tutup proyek berchecklist; sidebar 12 grup lipat + filter; tautan cepat di halaman gudang | A-227, A-228, TC-MST-25/25b |
| Konversi per jenis | `ConversionPlanner` satu sumber pratinjau & simpan (menutup bug neraca layar ≠ server); Potong satu batang, kerf & sisa otomatis; *Simpan & selesaikan/ajukan*; detail berkalimat + *Buat BA Waste* | A-229, TC-CNV-12/14/15, E2E P9 |
| Rapi UI kecil | `@fonts`, `theme-color`, kode internal keluar dari teks, `->lokal()`, beranda portal klien, klien dari daftar di form user, laporan asing → 404 | TC-MST-26, TC-ACC-UI-07, TC-RPT-01b, TC-PWA-01 |
| Bukti terima | halaman penerima bertoken (OTP → form → ringkasan; 410), foto serah terima + tanda tangan kanvas + foto rusak di layar driver, berkas dilayani berotorisasi; **bug**: tombol *Terbitkan tautan* memakai policy `ship` (hanya `prepared`) → tidak pernah aktif, kini `issueToken` | A-231, TC-SJ-05d/05d2/05e, E2E 5b |
| Pengaturan company | ambang hari/persen, saklar fitur (peringatan *dipakai n item*), zona waktu; hanya kunci yang berubah ditulis | A-230, TC-MST-27 |
| Laporan & cetak | 11 laporan §9 Stock/Request/Shipment dengan `PeriodFilter`; cetak TRF/RET/PRQ/GRN | A-232, TC-RPT-06, TC-TPL-15/16 |

Verifikasi 25 Sep 2026 sore (XAMPP kantor): **616 uji hijau / 6.744 asersi**; `_verify.py` OK; E2E pada demo segar `alur-req-sj` 10/10, `alur-pendukung` 9/9, `ui-check` 87 cek — 0 error console.

Temuan teknis yang layak diingat: (1) Livewire menjalankan semua hook `updated*` **setelah** seluruh properti dalam satu permintaan diset — `set()` massal dari E2E yang menyentuh `warehouse_id` dan `batang` sekaligus dibatalkan `kosongkanInput()`, jadi E2E mengirimnya dalam dua permintaan; (2) kunci kandidat stok di form Livewire memakai `_` (bukan `:`) karena kunci array Livewire tidak boleh memuat `:`; (3) server dev satu proses melambat saat mesin sibuk — helper `go()` E2E kini menunggu `readyState`/Livewire dan langkah yang menulis DB memantau barisnya, bukan tidur tetap; (4) `pint <direktori>` menyentuh berkas asing — hanya berkas yang diubah yang boleh di-Pint.

### 5.7 Sesi rumah (XAMPP3) — 25 Sep 2026 (malam)

| Pekerjaan | Hasil | Rujukan |
|---|---|---|
| Langkah 0 lingkungan | `composer`/`npm` install + build, migrasi pusat & tenant (000210), demo segar; 616 uji hijau, `_verify` OK, `ui-check` 0 error console, `alur-req-sj` & `alur-pendukung` lulus di MariaDB 10.4.32 | [A-76](wms/04-keputusan-dan-asumsi.md#a-76) |
| Notifikasi §8 (Sisa Fase 1 butir 2a) | 11 kejadian baru dari aksi & `notifications:daily` (`DailyReminders`: SLA tinjau, reservasi menggantung, aset jatuh tempo + PIC, sisa umur); job harian melewati company ditangguhkan; outbox gagal & bukti bayar → Super Admin ditunda | [A-233](wms/04-keputusan-dan-asumsi.md#a-233)–[A-237](wms/04-keputusan-dan-asumsi.md#a-237), TC-NTF-07–11 |
| Lampiran generik (butir 2b) | tabel `attachments`; foto pemakaian ISU, foto serah terima keluar AST, arsip PDF laporan opname saat sesi ditutup; `GET /attachments/{id}` berizin dokumen pemilik | [A-238](wms/04-keputusan-dan-asumsi.md#a-238), TC-ISU-18, TC-AST-13, TC-OPN-20 |
| Override bin beku & penuaan penggantian (butir 2c) | Kepala Gudang mengizinkan PCK `pending` mengambil dari bin beku (alasan wajib); angka sesi opname digeser, bin ⚑; `requests:expire-substitutions` tiap jam | [A-239](wms/04-keputusan-dan-asumsi.md#a-239), [A-240](wms/04-keputusan-dan-asumsi.md#a-240), TC-OPN-21, TC-REQ-20 |
| Laporan §9 modul 19–22 (butir 2d) | 16 laporan baru (penerimaan, Karantina, PUT, RTV; approval ×4; tren akurasi, selisih terbesar, akar masalah, ADJ per alasan; TRF terbuka, dalam perjalanan, retur per proyek, rusak di bin Retur) — total 41 | [A-241](wms/04-keputusan-dan-asumsi.md#a-241), TC-RPT-07–10 |
| Short pick TRF (butir 2e) | kekurangan PCK TRF → TRF backorder pengganti untuk REQ penunggu; SJ balik barang di tangan klien menunggu keputusan ([A-111](wms/04-keputusan-dan-asumsi.md#a-111)) | [A-242](wms/04-keputusan-dan-asumsi.md#a-242), TC-TRF-17 |
| Butir 2f | rekonsiliasi saldo terjadwal (lapor saja); bukti terima per unit serial/potongan; kelebihan terima GRN transfer/retur → ADJ `over_receipt`; transfer aset antar proyek menunggu keputusan ([A-116](wms/04-keputusan-dan-asumsi.md#a-116)) | [A-243](wms/04-keputusan-dan-asumsi.md#a-243)–[A-245](wms/04-keputusan-dan-asumsi.md#a-245), TC-STK-35, TC-SJ-18, TC-GRN-11 |

Verifikasi (XAMPP3 rumah): setelah 2a **621 uji hijau / 6.797 asersi**, setelah 2b **624 / 6.848**, setelah 2c **625 / 6.868**, setelah 2d+2e **630 / 7.090**, setelah 2f **632 / 7.118**; `_verify.py` OK; `ui-check` 0 error console.

### 5.8 Sesi kantor (XAMPP) — 26 Sep 2026

Pemilik produk meninjau 31 asumsi ⚠ di [tinjauan asumsi](00-tinjauan-asumsi-2026-09-25.md) v1.13 (28 Setuju, A-229 sebagian, A-210 diubah) dan menjawab dua keputusan terbuka (SJ balik barang di tangan klien, transfer aset antar proyek: **bangun**). Catatannya melahirkan fitur baru, dicatat sebagai [A-246–A-256](wms/04b-asumsi-lanjutan.md).

| Tugas | Hasil | Rujukan |
|---|---|---|
| 0. Langkah 0 | MariaDB 10.4.27 dinyalakan; install + build, migrasi 000220–000240, demo segar; 632 uji hijau, `_verify` OK, `ui-check` 0 error, E2E 10/10 & 9/9, saldo cocok | [A-76](wms/04-keputusan-dan-asumsi.md#a-76) |
| 1. Catat keputusan | kolom *Validasi* 04 v0.39 & *Keputusan* tinjauan v1.13; berkas lanjutan 04b; tabel §2.19 04 disambung | — |
| 2. PO lebih dari PRQ | alasan wajib (MOQ); kelebihan jadi stok biasa; GRN sampai jumlah PO | [A-246](wms/04b-asumsi-lanjutan.md#a-246), TC-PO-11 |
| 3. SJ jemput | SJ tanpa PCK dari proyek, sopir & plat wajib, tanpa pergerakan sampai GRN retur, asal per baris di layar & cetak | [A-247](wms/04b-asumsi-lanjutan.md#a-247), [A-248](wms/04b-asumsi-lanjutan.md#a-248), TC-RET-18–21, TC-SJ-19 |
| 4. Aset antar proyek | TRF aset + SJ antar site; satu pergerakan On-site → On-site, `asset_transferred`, AST `transferred` berantai; pindahan sisa proyek dari hub; jejak lokasi; kirim tautan via WA | [A-249](wms/04b-asumsi-lanjutan.md#a-249)–[A-251](wms/04b-asumsi-lanjutan.md#a-251), TC-AST-14–16, TC-TRF-18–20 |
| 5. Dokumen terkait | kartu asal & turunan di 14 layar detail; linimasa dokumen di tab Riwayat hub | [A-252](wms/04b-asumsi-lanjutan.md#a-252), TC-DOC-01–03 |
| 6. Potong banyak batang | satu CNV, pola per batang atau salin FIFO; kerf & sisa per batang | [A-253](wms/04b-asumsi-lanjutan.md#a-253), TC-CNV-15/16 |
| 7. Denah gudang 2D | ukuran/posisi opsional, warna status/umur, cari, geser grid, rak area, bin ikut terpakai, tanggal masuk FIFO, ubah bin | [A-254](wms/04b-asumsi-lanjutan.md#a-254)–[A-256](wms/04b-asumsi-lanjutan.md#a-256), TC-WH-21–25 |

Verifikasi (XAMPP kantor, MariaDB 10.4.27): setelah tugas 2–3 **638 uji / 7.277 asersi**, setelah tugas 4 **644 / 7.437**, akhir **654 / 7.581**; `_verify.py` OK; `npm run build` OK; E2E pada demo segar: alur-req-sj 10/10, alur-pendukung 9/9, ui-check 92 cek (termasuk denah, pindahan proyek, dokumen terkait) — 0 error console; klik & geser rak diuji di Chrome dengan peristiwa mouse sungguhan; `stock:reconcile` saldo cocok. Migrasi tenant baru: 000250–000280. Belum dikerjakan: 119 asumsi tanpa ⚠ (kolom *Keputusan* masih kosong).

### 5.9 Kompresi foto otomatis — 26 Sep 2026

| Tugas | Hasil | Rujukan |
|---|---|---|
| Kompresi foto (A-23) | satu titik `ImageCompressor` di `StoreUpload::handle()`, GD tanpa paket baru; semua jalur foto (item, tanda tangan, lampiran ISU/AST, inspeksi aset, BA waste, bukti terima SJ driver & tautan, keberatan, bukti bayar, logo); mentah ≤ 20 MB → tersimpan ≤ 5 MB, 1920 px, JPEG 80, putar EXIF; PNG transparan/tanda tangan/logo tetap PNG; PDF utuh | [A-23](wms/04-keputusan-dan-asumsi.md#a-23), [A-257](wms/04b-asumsi-lanjutan.md#a-257), TC-FIL-03–03e |

Verifikasi (XAMPP kantor): **659 uji / 7.635 asersi** hijau; `_verify.py` OK. php.ini kantor masih `upload_max_filesize = 5M`, jadi uji manual foto > 5 MB baru bisa setelah php.ini dinaikkan ([setup lokal](00-setup-lokal.md#batas-unggah-php)).

### 5.10 Impor struktur gudang — 26 Sep 2026

| Tugas | Hasil | Rujukan |
|---|---|---|
| Impor zona–rak–level–bin | kartu *Struktur gudang* di `/imports` + templat; gudang harus sudah ada; zona/rak/level baru dibuat otomatis lewat `SaveLocation`, bin lewat `SaveBin`; semua-atau-tidak, kode yang ada/ganda ditolak; `bin.manage` (Kepala Gudang kini bisa membuka `/imports`, hanya kartu ini); tombol di `/bins`, menu, palet | [A-258](wms/04b-asumsi-lanjutan.md#a-258), [O-12](wms/04-keputusan-dan-asumsi.md#o-12) sebagian, TC-WH-26–26d |

Verifikasi (XAMPP kantor, MariaDB 10.4.27): **663 uji / 7.681 asersi** hijau; `_verify.py` OK; `npm run build` OK; `ui-check` 94 cek (termasuk 6c kartu & templat impor struktur gudang) — 0 GAGAL, 0 error console. Impor gudang sendiri tetap menunggu O-12.

## 6. Cara mengulang

1. Data bersih: drop `wms_tenant_demo`, `php artisan migrate:fresh`, lalu urutan seed [00-setup-lokal §3](00-setup-lokal.md#3-database-dan-data-demo).
2. `php artisan serve --host=127.0.0.1 --port=8000`.
3. Skrip E2E ada di `tests/e2e/` (Node 22+ + Chrome, tanpa paket tambahan; `MYSQL_BIN` menunjuk `mysql.exe`): `ui-check.mjs`, `alur-req-sj.mjs` (10 langkah), `alur-pendukung.mjs` (9 langkah, dijalankan setelah `alur-req-sj`).
4. `php artisan test --log-junit` untuk hasil per uji.
