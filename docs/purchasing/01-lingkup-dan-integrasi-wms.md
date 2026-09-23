# Modul Purchasing — Lingkup & Integrasi dengan WMS

**Versi:** 0.1
**Tanggal:** 23 September 2026
**Status:** kerangka awal (dibuat pada audit v0.3 karena dirujuk Blueprint tetapi belum ada); spesifikasi lengkap modul Purchasing dibuat terpisah nanti
**Dokumen terkait:** [Blueprint §15](../wms/01-blueprint.md#15-integrasi) · [Katalog Status PRQ](../wms/06-katalog-status-dan-enum.md#215-prq-purchase-request--purchase_request-f1-manual-f3-terhubung) · [Aturan Bisnis](../wms/05-aturan-bisnis.md) · [Keputusan D-08](../wms/04-keputusan-dan-asumsi.md#d-08) · [Asumsi A-47](../wms/04-keputusan-dan-asumsi.md#a-47)

---

## 1. Prinsip pembagian

| | WMS | Purchasing |
|---|---|---|
| Sumber kebenaran | **Kebutuhan** barang (Purchase Request) dan **penerimaan fisik** (GRN) | **Pembelian**: vendor terpilih, harga beli, PO, termin |
| Menyimpan harga beli? | Tidak | Ya |
| Master vendor | Dikelola WMS **sementara** (nama, NPWP, kontak, termin) sampai modul Purchasing ada; setelah itu Purchasing menjadi pemilik dan WMS hanya membaca | Pemilik master vendor (Fase 3) |

## 2. Yang dipindahkan dari prototipe lama ke Purchasing

| Fitur di prototipe | Ke Purchasing sebagai |
|---|---|
| Field harga beli / `Capital` di master barang | Daftar harga beli per vendor |
| Input vendor bebas pada penerimaan | Purchase Order dengan vendor terpilih |
| Tidak ada PR/PO (barang datang langsung dicatat) | PR dari WMS → PO di Purchasing → GRN di WMS merujuk PO |

## 3. Fase 1: tindak lanjut PR manual di WMS

Sebelum modul Purchasing ada, WMS menyediakan alur minimum ([A-47](../wms/04-keputusan-dan-asumsi.md#a-47)):

1. `PRQ` dibuat otomatis dari backorder REQ atau manual oleh staf (item wajib sudah terdefinisi; non-katalog dipetakan dulu).
2. Approval PRQ opsional (aturan approval per company).
3. Role **Penindak Lanjut PR** mengubah status ke *Diteruskan* dan mengisi catatan manual: nomor PO eksternal (bebas), vendor, perkiraan tanggal datang. Tidak ada harga.
4. Saat barang datang, staf membuat GRN dan **memilih baris PRQ** yang dipenuhi; PRQ menjadi *Sebagian Terpenuhi* / *Dipenuhi* otomatis.
5. Barang yang ditunggu REQ direservasi otomatis ke REQ penunggu (urutan `required_date`) dan disarankan cross-dock ([BR-REQ-08](../wms/05-aturan-bisnis.md#br-req)).
6. PRQ bisa *Ditolak* atau *Dibatalkan* (alasan) selama belum ada GRN.

Laporan Fase 1: PRQ terbuka per gudang/proyek, lewat perkiraan datang, dan pemenuhan PRQ vs GRN.

## 4. Tanggung jawab modul Purchasing (Fase 3)

1. Menerima `purchase_requested`, mengelompokkan PR menjadi PO per vendor, negosiasi & harga.
2. Mengelola vendor (menjadi pemilik master vendor), termin, dan evaluasi vendor (ketepatan, kualitas dari `goods_rejected`).
3. Mengirim PO ke WMS agar GRN bisa merujuk PO dan memvalidasi jumlah diterima ≤ jumlah PO (kelebihan → keputusan Purchasing).
4. Menutup PO saat semua baris diterima atau dibatalkan.
5. Meneruskan data PO + GRN ke Akuntansi untuk pencocokan tagihan vendor (three-way match).

## 5. Kejadian antar modul

### 5.1 Dari WMS ke Purchasing

| Kejadian | Dipicu oleh | Isi pokok |
|---|---|---|
| `purchase_requested` | PRQ `submitted` | `prq_id`, nomor, gudang tujuan, proyek, baris (item, qty_base, uom, `required_date`, `req_ref`) |
| `purchase_request_cancelled` | PRQ `cancelled` | `prq_id`, alasan |
| `goods_received` | GRN (vendor) `received` | `po_ref`, `prq_ref`, baris diterima (untuk menutup PO) |
| `goods_rejected` | RTV `shipped` | `po_ref`, `grn_ref`, alasan (untuk klaim & evaluasi vendor) |

### 5.2 Dari Purchasing ke WMS

| Kejadian | Isi pokok | Efek di WMS |
|---|---|---|
| `po_created` | `po_id`, nomor PO, vendor, baris (item, qty, `prq_line_ref`, ETA) | PRQ → *Diteruskan*; GRN dapat dibuat "dari PO" |
| `po_updated` | Perubahan qty/ETA per baris | Perbarui ETA & jumlah yang ditunggu |
| `po_cancelled` | `po_id`, alasan | PRQ kembali ke *Diteruskan* tanpa PO, atau dibatalkan bila diminta |

Payload memakai amplop yang sama dengan kejadian stok ([Akuntansi §4.1](../akuntansi/01-lingkup-dan-integrasi-wms.md#41-payload-minimum)): `event_id`, `schema_version`, `company_id`, `occurred_at`, `source_type`, `source_id`. **Tidak ada harga** di kejadian yang masuk ke WMS.

## 6. Aturan integrasi

- Idempoten berdasarkan `event_id`; koreksi lewat kejadian baru, bukan edit.
- Satu baris PRQ boleh dipenuhi oleh lebih dari satu baris PO dan lebih dari satu GRN.
- WMS tidak pernah menampilkan harga PO; bila layar GRN perlu menampilkan "sesuai PO", yang ditampilkan hanya jumlah & ETA.
- Master vendor: selama Fase 1–2 WMS pemilik; saat Purchasing aktif, dilakukan migrasi kepemilikan satu arah (WMS baca saja).

## 7. Pertanyaan untuk spesifikasi Purchasing nanti

1. Apakah PR perlu approval anggaran (nilai uang) di Purchasing sebelum PO — di luar WMS.
2. Kebijakan kelebihan terima (over-receipt) vs PO: tolak, terima dengan approval, atau terima dan koreksi PO.
3. Evaluasi vendor: metrik apa yang dibaca dari WMS (ketepatan ETA, `goods_rejected`).
4. Apakah Purchasing dijual sebagai modul SaaS terpisah per company.
