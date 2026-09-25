# Catatan Perubahan — Arsip (v0.2–v0.4)

**Versi:** 1.0
**Tanggal:** 25 September 2026
**Status:** arsip — dipindah dari [README](README.md) v0.37 dan v0.39 agar README tetap ≤ 450 baris; isi tidak diubah
**Dokumen terkait:** [README](README.md)

### v0.6 — 23 September 2026 (bukti terima, barang kurang/rusak, umur aset)
- **Asumsi baru A-63–A-66** (*Setuju 23 Sep 2026*): A-63 konfirmasi atau keberatan klien (3 hari, otomatis bila klien mengisi sendiri); A-64 bukti terima per baris baik/rusak/kurang & per unit, DSC dengan jenis, `reship`, keputusan klien, laporan posisi barang rusak; A-65 posisi barang rusak (Dalam Perjalanan berkondisi Rusak → dibawa balik / RET + klaim); A-66 meter pemakaian, umur pakai, skor kondisi aset (penyusutan tetap di Akuntansi).
- **Aturan v0.6:** baru BR-SJ-10, BR-AST-08; diubah BR-SJ-05, BR-SJ-06, BR-REQ-10, BR-RET-05, BR-AST-07; §14 baris `goods_delivered` (jumlah baik), `delivery_discrepancy`, `asset_checked_out`/`asset_returned` (meter, skor), baris kondisi rusak tanpa kejadian.
- **Katalog v0.6:** KS 2.3 guard bukti terima, KS 2.4 (keberatan, disposisi `reship`, keputusan klien), KS 2.1 aksi `request.confirm_receipt`/`request.dispute_receipt`; enum `discrepancy_type`, `client_decision`, `receipt_confirmation`, `pod_unit_condition`, `meter_unit`; `discrepancy_disposition` + `reship`.
- **Blueprint v0.6** (447 baris): §4.2 Klien, §6.6 posisi rusak, §6.8 skor & meter aset, §6.9a laporan aset & posisi barang rusak, §7 SJ/DSC. **Glosarium v0.6:** 15 istilah baru. **akuntansi/01 v0.5:** penanda kejadian aset & DSC, pertanyaan penyusutan.
- **Part 2 v0.4 / Part 3 v0.4 (dibuat ulang):** alur 1 gateway *Keberatan?*, bukti terima per baris; alur 7 meter & skor; ERD 112 tabel: `proof_of_delivery_lines`, `proof_of_delivery_units`; kolom baru di `proofs_of_delivery`, `delivery_discrepancy_lines`, `serials`, `asset_handovers`, `asset_inspections`.
- Laporan validasi v1.2: §8 tambahan A-63–A-66.

### v0.5 — 23 September 2026 (purchasing, permintaan klien, pengiriman, audit)
- **Keputusan baru D-28** (modul Purchasing memakai mesin approval WMS; approval nilai uang hanya di Purchasing). **Asumsi baru A-51–A-62**, semua *Setuju 23 Sep 2026*: A-51 catatan pemesanan per vendor, A-52 jenis vendor & vendor tetap, A-53 vendor sementara, A-54 klien menambah baris / REQ Tambahan, A-55 penggantian item, A-56 pecah baris antar gudang, A-57 cara kirim, A-58 tutup periode stok, A-59 umur reservasi, A-60 SLA tinjau & tanggal janji, A-61 permintaan pembatalan klien, A-62 rencana kebutuhan material [F2].
- **Aturan v0.5:** baru BR-STK-15–16, BR-REQ-11–15, BR-SJ-09, BR-OPN-09–10, BR-PRJ-09 [F2]; diubah BR-REQ-02, BR-REQ-04, BR-SJ-07, BR-GRN-01, BR-APR-07; §1 relasi PRQ → Catatan Pemesanan → GRN.
- **Katalog v0.5:** KS 2.15 PRQ (`draft`, `approved`, `pr.order`), KS 2.1 aksi baris & `pending_approval → under_review`, KS 2.3 `shipment_method`, KS 2.13 guard SoD; enum baru `vendor_type`, `vendor_status`, `purchase_request_origin`, `request_origin`, `substitution_response`, `shipment_method`; `count_type` + `spot_check`.
- **Blueprint v0.5** (447 baris): §3 luar lingkup (transfer antar company, ongkir), §4.2, §6.3a, §6.4, §6.6, §6.9a, §7, §8.1, §9, §10, §15, §18. **Glosarium v0.5:** 20 istilah baru. **purchasing/01 v0.2**, **akuntansi/01** (`goods_shipped`, tutup periode), **08-arsitektur v0.3** (paket Approval bersama).
- **Part 2 v0.3 / Part 3 v0.3 (dibuat ulang):** alur 2 dimulai dari siklus PRQ; alur 1 & 8 diperluas; ERD 110 tabel: `item_vendors`, `purchase_request_orders(+_lines)`, `project_material_plans` [F2]; kolom baru di `vendors`, `material_requests`, `material_request_lines`, `shipments.shipment_method`; `purchase_requests` tanpa vendor di header.
- Laporan validasi v1.1: §8 tambahan diskusi lanjutan.

Catatan perubahan v0.4 dan sebelumnya: [00-catatan-perubahan-arsip.md](00-catatan-perubahan-arsip.md).

### v0.4 — 23 September 2026 (validasi asumsi)
- **Validasi:** A-25–A-49 **Setuju** (23 Sep 2026); **A-40 Ubah** (satu proyek boleh banyak Gudang Site; guard & checklist penutupan mencakup semuanya); **A-16 diganti A-42**; asumsi baru **A-50** (pemindahan dalam proyek = TRF ringan antar Gudang Site) *Perlu validasi*. Laporan: [00-laporan-validasi-2026-09-23.md](00-laporan-validasi-2026-09-23.md).
- **Aturan UI dari pemilik produk:** **BR-GEN-11** baru (field wajib ditandai `*`; tolak/batal = Alasan `*` + Keterangan opsional); BR-GEN-02 rujukan silang; KS §1 catatan; Blueprint §6.3a; glosarium *Keterangan*; template modul kolom *Wajib*.
- **Aturan v0.4:** BR-PRJ-02, BR-PRJ-04 (semua Gudang Site proyek); BR-RET-02, BR-SJ-07 (transfer dalam proyek, `self_delivered`); §1 relasi TRF.
- **Katalog v0.4:** guard `shipment.create` (KS 2.3) dan `transfer.create` (KS 2.7) memuat A-50; label `rejected`/`cancelled`.
- **Blueprint v0.4:** §6.2, §6.9, §7 (TRF), §18, §19, §20; **Glosarium v0.4:** Gudang Site, Transfer, *Diantar Sendiri*, *Keterangan*, Alasan.
- **Part 2 v0.2 / Part 3 v0.2 (dibuat ulang):** alur 5 gateway *Dalam proyek & diantar sendiri?* (A-50); ERD `shipments.self_delivered`, `carried_by_name`; `warehouses.project_id` catatan A-40; kepala 07*/08* dan `08-arsitektur.md` (v0.2, §12) menyebut validasi.
- **Baru:** `diagram/_verify.py` (link & anchor, ID, ganda, ukuran). Laporan audit v0.3: anchor ke 04 §2.3 diperbarui mengikuti judul baru.

### v0.3.2 — 23 September 2026 (draf Part 3)
- **Baru:** `wms/08-arsitektur.md` (AD-01–AD-15, tenancy & siklus request, struktur kode, ledger/reservasi, outbox, antrean & scheduler, peta NFR, lingkungan) dan `wms/08a`–`08c` + `diagram/erd-*.drawio` (106 tabel, 11 area) dari `diagram/_generate_erd.py`.
- **O-01 selesai:** paket diverifikasi di Packagist terhadap Laravel 13 / PHP 8.3; `endroid/qr-code` 6 ditolak (PHP 8.4), dipilih `bacon/bacon-qr-code` v3.
- Blueprint §20 dan §17 menunjuk ke 08.

### v0.3.1 — 23 September 2026 (draf Part 2)
- **Baru:** `wms/07-proses-bisnis.md`, `07a`, `07b` dan `diagram/bpmn-01…10-*.drawio` untuk 10 alur (permintaan–terima, penerimaan–QC–RTV, pemakaian ISU, retur, transfer, konversi & WST, aset, opname, approval, langganan). Semua dibuat dari `diagram/_generate.py`; setiap alur mencantumkan asumsi default yang dipakainya (A-07…A-47) dan BR yang berlaku.
- Tautan Odoo di riset diverifikasi hidup.

### v0.3 — 23 September 2026 (audit dokumentasi)
- **Struktur:** file dipindah ke `wms/`, `akuntansi/`; dibuat [purchasing/01](purchasing/01-lingkup-dan-integrasi-wms.md), [00-audit/README.md](00-audit/README.md), [00-laporan-audit-dokumentasi.md](00-laporan-audit-dokumentasi.md), `diagram/`, `../CLAUDE.md`. Semua rujukan menjadi link beranchor.
- **Baru:** `wms/05-aturan-bisnis.md` (BR-GEN/STK/REQ/SJ/GRN/RET/CNV/AST/OPN/APR/PRJ/SUB, relasi dokumen, matriks kejadian stok, matriks kombinasi pelacakan); `wms/06-katalog-status-dan-enum.md` (16 mesin status, 23 enum, blok YAML); `wms/_template-spesifikasi-modul.md`.
- **Blueprint v0.3:** P-02 ditulis ulang; §6.6 model stok tunggal; §7 tambah `ISU`, `RTV`, `DSC`; §7.1 relasi dokumen; §6.9 status proyek & tiga sub-tampilan Stok On-site; §4.2 role *Penindak Lanjut PR*, cakupan role × scope; NFR-07 diperjelas; NFR-13–16 baru; tag fase; §1 pembeda #4/#5 → `[F2]`.
- **Glosarium v0.3:** `return` → `goods_return`; `stock_movement` sebagai nama tabel; ±50 istilah baru; kolom Rujukan; dikelompokkan per tema.
- **Keputusan v0.3:** kolom Tanggal · Alasan · Konsekuensi · Diganti oleh; asumsi baru **A-29–A-49** (per tema); A-16 usulan ubah (→ A-42); A-04, A-17, A-20, A-21 diperjelas; isu baru **O-12–O-15**; matriks ketertelusuran.
- **Akuntansi v0.3:** kejadian baru `material_consumed`, `goods_rejected`, `delivery_discrepancy`; kejadian ganda dihapus (satu kejadian per pergerakan); payload + `schema_version`, `occurred_at`, `recorded_at`, `timezone`, `reverses_event_id`; mekanisme Fase 1 = tabel outbox.
- **Riset v0.3:** §2.8 daftar riset teknis Part 3; tautan Odoo ke dokumentasi resmi; baris "Tidak dipakai" di tabel adopsi.

### v0.2 — 23 September 2026 (audit ulang Part 1)
- A-01–A-24 disetujui. Kontradiksi opname vs approval diperbaiki (A-09). Status *Ditinjau Staf* (A-07). Efek pengiriman terhadap kepemilikan (A-25). Master Klien/Vendor/Kategori/Kendaraan/Alasan + impor Excel. Laporan inti Fase 1. Callback SSO pusat (A-28). Akses dukungan (A-27). NFR-11, NFR-12. O-11. Riset v0.2: harga WhatsApp diverifikasi ulang.
