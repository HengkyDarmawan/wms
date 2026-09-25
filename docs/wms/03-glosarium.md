# Glosarium

**Versi:** 0.9
**Tanggal:** 24 September 2026
**Status:** istilah dari A-29–A-49 berlaku (validasi 23 Sep 2026); istilah yang bergantung pada [A-50](04-keputusan-dan-asumsi.md#a-50) mengikuti status validasinya; v0.7: istilah mesin approval (Tugas Approval, Cara Putus, Jenis Approver, Approver Cadangan, Simulasi Aturan) dari modul [20-approval](20-approval.md); v0.8: istilah cetak (Layout Induk, Template Dokumen, Label, Blok Tanda Tangan — [18](18-template-dokumen-label.md)); v0.9: istilah Purchasing inti Fase 1b (Purchase Order, Baris PO, Harga Beli Vendor, Harga Satuan, Nilai PO — [purchasing/02](../purchasing/02-purchasing-inti.md)); satu-satunya istilah bernilai uang di aplikasi ([A-208](04-keputusan-dan-asumsi.md#a-208))
**Dokumen terkait:** [Blueprint](01-blueprint.md) · [Katalog Status & Enum](06-katalog-status-dan-enum.md) (nilai status **tidak** diulang di sini) · [Aturan Bisnis](05-aturan-bisnis.md)

Istilah di bawah **wajib dipakai sama persis** di UI, dokumen, dan kode. Kolom *Nama di kode* adalah acuan penamaan tabel/model/variabel (Inggris, `snake_case` untuk tabel; model = bentuk `PascalCase` tunggal). Kolom *Rujukan* menunjuk bagian Blueprint (BP) atau aturan bisnis (BR).

Perubahan v0.3: `return` diganti `goods_return` (kata kunci PHP); `stock_ledger` hanya nama konsep, tabelnya `stock_movement`; ±50 istilah ditambah; dikelompokkan per tema.

---

## 1. Platform & langganan

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Company | `company` / `tenant` | Pelanggan SaaS; punya database sendiri | BP 6.1 |
| Super Admin | `platform_admin` | Pengelola platform (pemilik produk) | BP 4.1 |
| Admin Company | `company_admin` | Pengelola pengaturan satu company | BP 4.2 |
| Paket | `plan` | Jenis langganan bulanan | BP 14 |
| Langganan | `subscription` | Status berlangganan company (`subscription_status`) | BP 14, BR-SUB-01 |
| Tagihan Langganan *(baru)* | `subscription_invoice` | Tagihan bulanan company ke platform | BP 14 |
| Bukti Bayar Langganan *(baru)* | `subscription_payment` | Bukti transfer yang diunggah Admin Company & diverifikasi Super Admin | BP 14 |
| Masa Tenggang | `grace_period` | Periode setelah jatuh tempo sebelum ditangguhkan | BR-SUB-01 |
| Akses Dukungan | `support_access` | Izin sementara Super Admin membuka data company | BR-SUB-04 |
| Feature Flag *(baru)* | `feature_flag` | Fitur yang dinyalakan platform per company (DB pusat) | BP 6.1 |
| Pengaturan Company *(baru)* | `company_setting` | Pengaturan umum company (zona waktu, format nomor, kapasitas bin) di DB tenant | BP 6.1 |
| Pengaturan Fitur | `feature_setting` | Fitur stok yang dinyalakan company (lapis 1 dari P-08) | BP 6.4 |
| Zona Waktu Company *(baru)* | `timezone` | WIB/WITA/WIT untuk tampilan; simpan UTC | BR-GEN-07 |
| Undangan User *(baru)* | `user_invitation` | Tautan bertoken untuk user baru mengatur password | BP 13 |
| Wizard Setup *(baru)* | `setup_wizard` | Panduan setup awal company | BP 18 |
| Impor Excel *(baru)* | `import_batch`, `import_row` | Sesi impor master + baris dengan hasil validasi | BP 6.3a |

## 2. Organisasi, gudang, lokasi

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Unit Organisasi | `org_unit` | Divisi/departemen | BP 6.2 |
| Jabatan | `position` | Posisi dalam struktur organisasi, dipakai approval | BP 6.2 |
| Tipe Gudang | `warehouse_type` | Utama, Cabang, Site, atau tipe buatan company | BP 6.2 |
| Gudang | `warehouse` | Tempat penyimpanan fisik; bisa punya induk | BP 6.2 |
| Gudang Site | `warehouse` (type = site) | Gudang di lokasi proyek, terikat satu proyek; satu proyek boleh punya beberapa (titik/segmen lokasi) | BP 6.2, A-40 |
| Gudang Sumber / Tujuan *(baru)* | `source_warehouse_id`, `dest_warehouse_id` | Gudang pemenuh permintaan / gudang tujuan transfer | BR-REQ-04 |
| Zona | `zone` | Area di dalam gudang | BP 6.3 |
| Rak | `rack` | Rak di dalam zona | BP 6.3 |
| Level | `rack_level` | Tingkat rak | BP 6.3 |
| Bin | `bin` | Lokasi terkecil tempat stok disimpan; jenis = `bin_type` | BP 6.3 |
| Bin Penerimaan *(baru)* | `bin` (type = receiving) | Dock masuk, tempat GRN diposting | BR-GRN-01 |
| Loading Area | `bin` (type = staging) | Barang disiapkan sebelum dimuat | BP 6.3 |
| Karantina QC | `bin` (type = quarantine) | Barang menunggu/gagal QC | BP 6.3 |
| Bin Retur *(baru)* | `bin` (type = return) | Barang retur menunggu pemilahan | BR-RET-04 |
| Bin Waste *(baru)* | `bin` (type = waste) | Sisa tidak layak pakai menunggu Berita Acara Waste | BP 6.7 |
| Lokasi Virtual | `bin` (type = in_transit \| on_site) | *Dalam Perjalanan* (milik gudang asal) dan *On-site Proyek* (satu per proyek, hanya aset) | BR-STK-13, BR-STK-14 |
| Kategori Penyimpanan | `storage_category` | Syarat khusus bin (barang panjang, kimia, dll.) | BP 6.3 |
| Status Bin *(baru)* | `bin_status` | Aktif / Dibeku (opname) / Nonaktif | BP 6.3 |

## 3. Item & satuan

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Item / Barang | `item` | Master barang; status `item_status` (aktif / sementara / nonaktif) | BP 6.4 |
| Item Sementara *(baru)* | `item` (status = provisional) | Item yang dibuat dari baris non-katalog, harus dilengkapi Admin | BR-REQ-03 |
| Kategori Barang | `item_category` | Pengelompokan item; membawa default penyimpanan, strategi, ambang toleransi | BP 6.3a |
| Model Kepemilikan | `ownership_model` | `consumable`, `asset`, `both` | BP 6.4 |
| Kepemilikan Baris *(baru)* | `line_ownership` | Pilihan `buy` / `loan` per baris permintaan untuk item `both` | BR-REQ-06 |
| Mode Pelacakan | `tracking_mode` | `none`, `lot`, `serial`, `piece` | BP 6.4 |
| Lot / Batch | `lot` | Kelompok barang dengan asal/tanggal produksi sama | BP 6.4 |
| Serial Number | `serial` | Identitas unik satu unit | BP 6.4 |
| Potongan | `piece` | Satu batang/lembar fisik berukuran tertentu | BP 6.7 |
| Ukuran Potongan *(baru)* | `piece_length` | Panjang (atau dimensi) satu potongan dalam satuan dasar | BR-STK-09 |
| Panjang Minimum Offcut *(baru)* | `min_offcut_length` | Batas sisa yang masih menjadi offcut | BR-CNV-03 |
| Satuan | `uom` | Unit of measure | BP 6.5 |
| Kategori Satuan | `uom_category` | Jumlah, panjang, berat, volume, luas | BP 6.5 |
| Satuan Dasar | `base_uom` | Satuan penyimpanan stok untuk satu item | BP 6.5 |
| Konversi Satuan | `uom_conversion` | Faktor antar satuan (global atau khusus item) | BP 6.5 |
| Titik Pesan Ulang | `reorder_point` | Batas stok yang memicu draf Purchase Request harian | BP 6.4, BR-REQ-11 |
| Kelas ABC | `abc_class` | Klasifikasi item untuk cycle count | BP 9 |
| Vendor | `vendor` | Pemasok barang; jenis `vendor_type` (perusahaan / toko / toko online / perorangan) | BP 6.3a, A-52 |
| Vendor Sementara *(baru)* | `vendor` (status = provisional) | Vendor yang dibuat cepat saat memesan, dilengkapi Admin kemudian | A-53 |
| Vendor Tetap *(baru)* | `item_vendor` | Vendor pilihan per item (prioritas, tanpa harga) yang disarankan saat memesan | A-52 |
| Klien | `client` | Pemilik proyek, pelanggan dari company | BP 6.3a |
| Kendaraan *(baru)* | `vehicle` | Kendaraan pengiriman milik company | BP 6.3a |
| Ekspedisi Pihak Ketiga *(baru)* | `carrier` | Jasa pengiriman luar; SJ mencatat nama & resi | BR-SJ-07 |
| Nomor Resi *(baru)* | `tracking_no` | Nomor lacak kurir/ekspedisi pada SJ atau catatan pemesanan | BR-SJ-07, A-51 |
| Alasan *(baru)* | `reason_code` | Master alasan untuk tolak/batal/penyesuaian/waste/short pick/selisih; wajib `*` saat tolak/batal | BR-GEN-02, BR-GEN-11 |
| Keterangan *(baru)* | `notes` | Teks bebas **opsional** pada dokumen dan pada aksi tolak/batal | BR-GEN-11 |

## 4. Stok

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Kartu Stok | `stock_movement` (konsep: *ledger*) | Baris permanen setiap mutasi stok; append-only | BR-STK-01 |
| Saldo Stok | `stock_balance` | Jumlah per item × bin × lot/serial/potongan × kondisi | BR-STK-02 |
| Kondisi Stok *(baru)* | `stock_status` | Tersedia / Karantina / Rusak | BR-STK-02 |
| Reservasi *(baru)* | `stock_reservation` | Jumlah yang dijanjikan untuk dokumen; lunak (item × gudang) atau keras (bin/lot/serial/potongan) | BR-STK-03 |
| Alokasi *(baru)* | `allocation` | Reservasi keras yang menunjuk bin/lot/serial/potongan | BR-STK-04 |
| Stok Tersedia | `available_qty` | Saldo *Tersedia* − reservasi aktif | BR-STK-03 |
| Dicadangkan | `reserved_qty` | Jumlah reservasi aktif (turunan, bukan status) | BR-STK-03 |
| Reservasi Menggantung *(baru)* | `stale_reservation` (laporan) | Reservasi aktif tanpa PCK lebih dari N hari | BR-STK-16 |
| Tanggal Kunci Stok *(baru)* | `stock_lock_date` | Batas periode; mutasi sebelum tanggal ini ditolak | BR-STK-15 |
| Strategi Pengambilan | `removal_strategy` | FIFO, FEFO, Manual, Sisa potongan dulu | BP 6.6 |
| Backorder | `backorder` | Sisa permintaan yang belum bisa dipenuhi dari stok; wajib punya sumber (TRF/PRQ) | BR-REQ-05 |
| Cross-dock | `cross_dock` | Barang masuk langsung ke Loading Area tanpa put-away | BR-SJ-03 |
| Kekurangan Pick *(baru)* | `short_pick` | Fisik kurang dari alokasi saat picking | BR-SJ-02 |
| Penanda Hitung *(baru)* | `count_flag` | Tanda bin perlu dihitung (dari short pick/selisih) | BR-SJ-02 |
| Terkirim ke Klien | `delivered_to_client` | Riwayat barang jual-putus yang sudah keluar dari stok company | BR-SJ-04 |
| Kejadian Stok | `stock_event` | Pesan ke modul lain setiap ada pergerakan ledger | BR §14 |
| Kejadian Pembalik *(baru)* | `reverses_event_id` | Rujukan kejadian yang dibalik | BR-GEN-03 |
| Dokumen Pembalik *(baru)* | `reversal_of_id` | Rujukan dokumen asal pada dokumen pembalik | BR-GEN-03 |
| Dokumen Sumber *(baru)* | `source_type`, `source_id` | Relasi polimorfik anak → induk | BR §1 |

## 5. Dokumen transaksi

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Permintaan Material | `material_request` (`REQ`) | Pengajuan barang dari pemohon internal atau klien | KS 2.1 |
| Pemohon *(baru)* | `requester` | User yang mengajukan (internal atau klien) | BP 4.2 |
| Tanggal Dibutuhkan *(baru)* | `required_date` | Tanggal barang harus tiba, per baris | BR-REQ-01 |
| Tanggal Janji *(baru)* | `promised_date` | Tanggal kirim yang dijanjikan staf per baris, tampil di portal | BR-REQ-14 |
| REQ Tambahan *(baru)* | `material_request` (origin = supplement) | Permintaan lanjutan dari klien setelah REQ induk disetujui | BR-REQ-12 |
| Pecah Baris *(baru)* | `split_from_line_id` | Satu baris dibagi ke beberapa gudang sumber | BR-REQ-04 |
| Penggantian Item *(baru)* | `substitution` (`substitution_response`) | Baris klien dipetakan ke item lain; klien boleh menolak dalam batas keberatan | BR-REQ-13 |
| Batas Keberatan Pengganti *(baru)* | `substitution_objection_days` | Lama klien boleh menolak pengganti (default 1 hari) | BR-REQ-13 |
| Permintaan Pembatalan *(baru)* | `cancel_request` | Klien meminta baris dibatalkan setelah disetujui; dikonfirmasi staf | BR-REQ-15 |
| SLA Tinjau *(baru)* | `review_sla_days` | Batas waktu staf meninjau permintaan klien (default 1 hari kerja) | BR-REQ-14 |
| Item Non-katalog | `non_catalog_line` | Baris permintaan untuk barang yang belum ada di master | BR-REQ-03 |
| Baris Dokumen *(baru)* | `<dokumen>_line` | Baris item pada dokumen (mis. `material_request_line`) | BR §1 |
| Tugas Picking | `pick_task` (`PCK`) | Tugas mengambil barang dari bin ke Loading Area | KS 2.2 |
| Pengiriman / Surat Jalan | `shipment` (`SJ`) | Dokumen pengiriman barang | KS 2.3 |
| Konfirmasi Muat *(baru)* | `load_confirmation` | Catatan timeline saat SJ dikirim (foto opsional) | KS 2.3 |
| Cara Kirim *(baru)* | `shipment_method` | Kendaraan sendiri / ekspedisi / diantar sendiri; field wajib mengikuti pilihan | BR-SJ-07, A-57 |
| Diantar Sendiri *(baru)* | `shipment_method = self_delivered` (SJ), `self_delivered` (RET) | Tanpa driver & kendaraan company; cukup nama pembawa | BR-SJ-07, KS 2.8, A-50 |
| Gabung Pengiriman *(baru)* | `shipment` (n PCK) | Satu SJ memuat beberapa PCK/REQ ke tujuan yang sama | BR-SJ-09 |
| Bukti Terima | `proof_of_delivery` | Satu per SJ: foto, tanda tangan, GPS; per baris jumlah baik/rusak/kurang; per unit untuk serial & potongan | BR-SJ-05, A-64 |
| Keberatan Terima *(baru)* | `receipt_dispute` (`receipt_confirmation`) | Pemohon menolak sebagian bukti terima (kurang/rusak) dalam batas konfirmasi; membuka DSC | BR-REQ-10, A-63 |
| Batas Konfirmasi Terima *(baru)* | `receipt_confirm_days` | Lama pemohon boleh konfirmasi/keberatan (default 3 hari) | BR-REQ-10 |
| Tautan Bukti Terima *(baru)* | `delivery_token` | Token sekali pakai + OTP untuk penerima tanpa akun | BR-SJ-05 |
| Konfirmasi Terima Pemohon *(baru)* | `receipt_confirmation` | Konfirmasi pemohon setelah bukti terima driver | BR-REQ-10 |
| Selisih Pengiriman *(baru)* | `delivery_discrepancy` (`DSC`) | Dokumen penyelesaian barang kurang atau rusak saat tiba | KS 2.4, BR-SJ-10 |
| Jenis Selisih *(baru)* | `discrepancy_type` | Kurang / Rusak per baris DSC | BR-SJ-10 |
| Kirim Pengganti *(baru)* | `reship` | Disposisi DSC: jumlah kembali ke backorder REQ dan dikirim ulang dari stok | BR-SJ-10 |
| Keputusan Klien *(baru)* | `client_decision` | Masih perlu / tidak perlu sisa barang pada DSC | BR-SJ-10 |
| Laporan Posisi Barang Rusak & Selisih *(baru)* | `damage_position_report` | Rusak dalam perjalanan, di bin Retur, diklaim, DSC terbuka | BR-SJ-10 |
| Penerimaan Barang | `goods_receipt` (`GRN`) | Dokumen barang masuk (vendor, transfer, retur) | KS 2.5 |
| Hasil QC *(baru)* | `qc_result` | Lolos / Karantina / Ditolak per baris GRN | BR-GRN-02 |
| Put-away | `putaway_task` (`PUT`) | Tugas menaruh barang ke bin | KS 2.6 |
| Retur ke Vendor *(baru)* | `vendor_return` (`RTV`) | Pengembalian barang gagal QC ke vendor | KS 2.16 |
| Transfer | `transfer` (`TRF`) | Dokumen niat pemindahan antar gudang, antar proyek, atau antar Gudang Site dalam satu proyek | KS 2.7, A-50 |
| Retur | `goods_return` (`RET`) | Pengembalian barang dari proyek/klien | KS 2.8 |
| Pemilahan Retur | `return_sorting` | Layak, rusak, offcut, waste | BR-RET-04 |
| Pemakaian Material *(baru)* | `material_issue` (`ISU`) | Barang habis pakai di Gudang Site dipakai proyek | KS 2.9 |
| Konversi Material | `conversion` (`CNV`) | Mengubah barang input menjadi output | KS 2.10 |
| Resep Konversi | `conversion_recipe` | Pola konversi berulang [F2] | BR-CNV-06 |
| Serah Terima Aset | `asset_handover` (`AST`) | Pencatatan aset keluar/kembali | KS 2.11 |
| Penyesuaian Stok | `stock_adjustment` (`ADJ`) | Koreksi stok dengan approval | KS 2.12 |
| Stock Opname | `stock_count` (`OPN`) | Sesi penghitungan fisik | KS 2.13 |
| Berita Acara Waste | `waste_disposal` (`WST`) | Dokumen disposisi waste | KS 2.14 |
| Purchase Request | `purchase_request` (`PRQ`) | Permintaan pembelian ke Purchasing; asal `purchase_request_origin` | KS 2.15 |
| Catatan Pemesanan *(baru)* | `purchase_request_order` | Pemesanan per vendor/toko online di bawah PRQ (nomor PO/pesanan, resi, ETA, baris × jumlah dipesan) | A-51 |
| Nomor Pesanan Marketplace *(baru)* | `marketplace_order_no` | Nomor pesanan di toko online | A-51 |
| Purchase Order *(baru)* | `purchase_order` (`PO`) | Pesanan pembelian ke satu vendor untuk satu gudang tujuan, dari baris PRQ, dengan harga beli; disetujui menjadi catatan pemesanan (Fase 1b) | KS 2.17, A-210 |
| Baris PO *(baru)* | `purchase_order_line` | Baris PRQ × jumlah × harga satuan; mencatat jumlah diterima dan ditutup | A-210, A-214 |
| Harga Beli Vendor *(baru)* | `vendor_price` | Harga per satuan dasar item dari satu vendor, berlaku mulai tanggal tertentu; harga lama tetap sebagai riwayat | A-211 |
| Harga Satuan *(baru)* | `unit_price` | Harga per satuan dasar pada harga beli vendor dan baris PO (Rupiah) | A-211 |
| Nilai PO *(baru)* | `total_amount` | Σ jumlah × harga baris PO; dasar kondisi approval nilai | A-212 |
| Timeline Dokumen *(baru)* | `document_timeline` | Riwayat status/pelaku/waktu/kanal per dokumen (untuk user) | BR-GEN-05 |
| Jejak Audit *(baru)* | `audit_log` | Catatan teknis nilai lama → baru (untuk Admin) | BR-GEN-05 |
| Format Nomor *(baru)* | `numbering_format` | Pola nomor dokumen per company | BR-GEN-06 |
| Urutan Nomor *(baru)* | `document_sequence` | Penghitung nomor per format/gudang/bulan, dikunci di DB | BR-GEN-06 |
| Lampiran *(baru)* | `attachment` | Foto/berkas pada dokumen (maks 5 MB) | NFR-14 |
| Tanda Tangan *(baru)* | `signature` | Gambar tanda tangan dari profil atau perangkat | BP 12 |
| Layout Induk *(baru)* | `document_layout` | Kop cetak per company: logo, teks kop, warna aksen, footer, blok tanda tangan | BP 12, [18](18-template-dokumen-label.md) |
| Template Dokumen *(baru)* | `document_template` | Bentuk cetak per jenis dokumen/label beserta kertasnya; F1 bawaan, editor [F2] | BP 12, A-122 |
| Label *(baru)* | `label` (`label_bin`, `label_item`, `label_lot`, `label_piece`) | Stiker cetak berisi teks, barcode Code128, dan QR untuk ditempel pada bin atau barang | BP 6.10, A-120 |
| Blok Tanda Tangan *(baru)* | `signature_blocks` | Kotak tanda tangan di kaki dokumen cetak, mis. "Pengemudi", "Penerima" | A-125 |

## 6. Konversi, offcut, waste

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Offcut | `offcut` | Sisa potong yang masih layak pakai dan kembali ke stok | BR-CNV-03 |
| Waste | `waste` | Sisa/barang yang tidak layak pakai | BP 6.7 |
| Kerf / Rugi Potong | `kerf` | Material yang hilang karena proses potong | BP 6.7 |
| Silsilah | `genealogy` (`parent_piece_id`, `parent_lot_id`) | Relasi asal-hasil antar potongan/lot | BR-CNV-04 |
| Disposisi Waste *(baru)* | `waste_disposition` | Dibuang / dijual scrap / dipakai ulang | KS 3 |

## 7. Aset

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Aset | `asset` | Unit barang ber-serial dengan model kepemilikan aset | BP 6.8 |
| State Aset *(baru)* | `asset_state` | Siklus hidup aset (lihat KS 3) | BR-AST-01 |
| Kondisi / Grade Aset *(baru)* | `condition_grade` | A–D saat keluar/kembali | BR-AST-03 |
| Skor Kondisi *(baru)* | `condition_score` | 0–100 % saat pemeriksaan; riwayat per aset untuk maintenance | BR-AST-08 |
| Meter Pemakaian *(baru)* | `usage_meter` (`meter_unit`, `meter_out`, `meter_in`, `meter_total`) | Jam mesin / km dibaca saat keluar & kembali | BR-AST-08 |
| Umur Pakai *(baru)* | `expected_life_days`, `expected_life_hours` | Umur harapan aset (hari dan/atau jam) | BR-AST-08 |
| Sisa Umur *(baru)* | `remaining_life_pct` | Turunan: 100 − pemakaian/umur harapan; peringatan di bawah ambang | BR-AST-08 |
| Jatuh Tempo Pengembalian *(baru)* | `due_return_date` | Tanggal kembali yang direncanakan | BR-AST-06 |
| Hilang / Dihapuskan *(baru)* | `lost`, `written_off` | State aset hilang; dihapuskan lewat ADJ | BR-AST-04 |
| Maintenance | `maintenance`, `maintenance_schedule` | Servis aset & jadwalnya [F2] | BR-AST-07 |
| Hari Pakai | `usage_days` | Jumlah hari aset berada di proyek; dasar tagihan sewa di Akuntansi | BR-AST-05 |

## 8. Stock opname

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Hitung Buta | `blind_count` | Penghitung tidak melihat angka sistem | BP 9 |
| Pemeriksaan Mendadak *(baru)* | `stock_count` (count_type = spot_check) | Sesi kecil tanpa pembekuan oleh Auditor/Kepala Gudang | BR-OPN-10 |
| Penugasan Penghitung *(baru)* | `count_assignment` | Siapa menghitung bin mana | BR-OPN-05 |
| Hitung Ulang *(baru)* | `recount` | Hitungan kedua oleh orang berbeda | BR-OPN-05 |
| Toleransi Selisih | `variance_tolerance` | Ambang relatif & absolut untuk kelas selisih | BR-OPN-04 |
| Kelas Selisih *(baru)* | `variance_class` | Kecil / sedang / besar | KS 3 |
| Kategori Akar Masalah *(baru)* | `root_cause_category` | Salah ambil, salah taruh, salah satuan, rusak/hilang, tidak tercatat | KS 3 |
| Rekonsiliasi | `reconciliation` | Proses menyelesaikan selisih hasil opname | BR-OPN-06 |
| Pembekuan Bin *(baru)* | `bin_freeze` | Bin berstatus dibeku selama sesi | BR-OPN-02 |
| Auditor Internal / Eksternal | `auditor` (`internal` / `external`) | Pelaksana audit stok | BR-OPN-08 |

## 9. Approval & notifikasi

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Aturan Approval | `approval_rule` | Konfigurasi approval per jenis dokumen | BP 8.1 |
| Lapis Approval | `approval_step` | Satu tingkat dalam aturan | BP 8.1 |
| Snapshot Aturan *(baru)* | `approval_snapshot` | Salinan aturan yang melekat pada dokumen saat diajukan | BR-APR-01 |
| Keputusan Approval *(baru)* | `approval_decision` | Setuju / tolak / didelegasikan / dieskalasi | KS 3 |
| Tugas Approval *(baru)* | `approval_task` | Satu tugas memutus satu lapis untuk satu approver; tampil di layar *Tugas approval saya* | ERD 08c, [20-approval](20-approval.md) |
| Cara Putus *(baru)* | `decision_mode` | Berurutan / cukup salah satu / semua harus setuju, per lapis | BP 8.1, KS 3 |
| Jenis Approver *(baru)* | `approver_type` | User tertentu, jabatan, role, atasan langsung, kepala gudang terkait, PIC proyek | BP 8.1, KS 3 |
| Approver Cadangan *(baru)* | `backup_approver` | Tujuan pertama eskalasi sebuah lapis | BR-APR-06 |
| Simulasi Aturan *(baru)* | `approval_simulation` | "Siapa yang akan menyetujui dokumen ini?" tanpa menyimpan apa pun | BR-APR-11 |
| Delegasi | `approval_delegation` | Pelimpahan hak approve sementara (tidak berantai) | BR-APR-05 |
| Eskalasi | `escalation` | Pengalihan approval yang melewati batas waktu | BR-APR-06 |
| Token Approval WA *(baru)* | `approval_token` | Token sekali pakai di pesan WhatsApp [F2] | BR-APR-10 |
| Log Pesan WA *(baru)* | `wa_message_log` | Pesan keluar/masuk WhatsApp per company & jenis [F2] | BP 8.2 |
| Notifikasi | `notification` | Pesan in-app/email/WA ke user | BP 10 |

## 10. Perangkat & PWA

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Perangkat Terdaftar | `device` | HP/tablet yang boleh menjalankan PWA | BP 11 |
| Antrean Sinkron | `sync_queue` | Transaksi offline yang menunggu dikirim [F2] | BP 11 |
| Antrean Tinjauan | `sync_conflict` | Transaksi offline yang bentrok dan perlu ditinjau [F2] | BP 11 |
| Nomor Sementara *(baru)* | `temp_number` (`TMP-…`) | Nomor dokumen sebelum sinkron | BR-GEN-06 |
| Tag RFID | `rfid_tag` | Tag yang dipetakan ke item/aset/bin [F2] | BP 6.10 |

## 11. Role & akses

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Role | `role` | Kumpulan hak akses; bawaan atau buatan company | BP 4.2 |
| Permission | `permission` | Satu hak akses (`<modul>.<aksi>`) | KS |
| Penugasan Role *(baru)* | `role_assignment` | User × role × cakupan | BR-GEN-09 |
| Cakupan Akses | `access_scope` | Batas gudang/proyek pada satu penugasan role | BR-GEN-09 |
| Manajemen | `management` | Role pimpinan | BP 4.2 |
| Kepala Gudang | `warehouse_head` | Penanggung jawab gudang & approver | BP 4.2 |
| Staf Gudang | `warehouse_staff` | Pelaksana penerimaan, picking, konversi, hitung | BP 4.2 |
| Driver | `driver` | Pengantar barang, mengisi bukti terima | BP 4.2 |
| Pemohon Internal | `internal_requester` | Engineer/PIC proyek dari company | BP 4.2 |
| Penindak Lanjut PR *(baru, menggantikan "Purchasing")* | `pr_follow_up` | Mencatat tindak lanjut PRQ secara manual di Fase 1 | BP 4.2 |
| Klien (role) | `client_user` | User dari pemilik proyek di portal | BP 4.2 |
| Portal Klien | `client_portal` | Area login user klien (`/portal`) | BR-PRJ-07 |

## 12. Proyek

| Istilah (UI) | Nama di kode | Arti | Rujukan |
|---|---|---|---|
| Proyek | `project` | Pekerjaan pembangunan milik klien; pusat pelacakan material | BP 6.9 |
| Proyek Internal | `internal_project` | Proyek khusus non-klien (persiapan stok, pinjam ke orang) | BR-AST-02 |
| Status Proyek *(baru)* | `project_status` | Aktif / Ditutup / Dibatalkan / Diarsipkan | BR-PRJ-01 |
| Penutupan Proyek *(baru)* | `project_closure` | Checklist & guard saat menutup proyek | BR-PRJ-02 |
| Stok On-site | `on_site_stock` (tampilan) | Tiga sub-tampilan: Di Gudang Site, Aset di Proyek, Terkirim ke Klien | BR-PRJ-05 |
| PIC Proyek | `project_pic` | Penanggung jawab proyek di company | BP 6.9 |
| Rencana Kebutuhan Material *(baru)* | `project_material_plan` | BoQ kuantitas per proyek (item × jumlah rencana) [F2] | BR-PRJ-09 |

*Singkatan rujukan:* BP = Blueprint, BR = Aturan Bisnis, KS = Katalog Status & Enum, NFR = kebutuhan non-fungsional.
