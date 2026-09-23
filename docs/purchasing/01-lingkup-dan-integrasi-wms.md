# Modul Purchasing — Lingkup & Integrasi dengan WMS

**Versi:** 0.3
**Tanggal:** 23 September 2026
**Status:** alur Fase 1 dilengkapi 23 Sep 2026 (multi-vendor, jenis vendor, vendor tetap, titik pesan ulang, D-28); Purchasing inti dijadwalkan Fase 1b ([D-29](../wms/04-keputusan-dan-asumsi.md#d-29)); spesifikasi modulnya dibuat setelah Part 4 WMS
**Dokumen terkait:** [Blueprint §15](../wms/01-blueprint.md#15-integrasi) · [Katalog Status PRQ](../wms/06-katalog-status-dan-enum.md#215-prq-purchase-request--purchase_request-f1-manual-f3-terhubung) · [Aturan Bisnis](../wms/05-aturan-bisnis.md) · [Keputusan D-08](../wms/04-keputusan-dan-asumsi.md#d-08) · [Asumsi A-47](../wms/04-keputusan-dan-asumsi.md#a-47), [A-51](../wms/04-keputusan-dan-asumsi.md#a-51)–[A-53](../wms/04-keputusan-dan-asumsi.md#a-53) · [Keputusan D-28](../wms/04-keputusan-dan-asumsi.md#d-28)

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

Sebelum modul Purchasing ada, WMS menyediakan alur minimum ([A-47](../wms/04-keputusan-dan-asumsi.md#a-47), [A-51](../wms/04-keputusan-dan-asumsi.md#a-51), [A-52](../wms/04-keputusan-dan-asumsi.md#a-52), [A-53](../wms/04-keputusan-dan-asumsi.md#a-53)):

1. **Sumber PRQ:** otomatis dari backorder REQ saat approval ([BR-REQ-05](../wms/05-aturan-bisnis.md#br-req)); manual oleh Kepala Gudang/staf (`pr.create`) bila barang tidak ada di gudang; **draf harian dari titik pesan ulang** yang ditinjau Kepala Gudang ([BR-REQ-11](../wms/05-aturan-bisnis.md#br-req)). Item wajib sudah terdefinisi (non-katalog dipetakan dulu).
2. **Approval PRQ** bertingkat lewat mesin approval company (lapis, delegasi, eskalasi, simulasi — [BR-APR](../wms/05-aturan-bisnis.md#br-apr)); opsional per aturan. Kondisi: jumlah, kategori, gudang, proyek, **jenis vendor**, **asal PRQ**; tidak ada nilai uang ([D-07](../wms/04-keputusan-dan-asumsi.md#d-07)). Approval berdasarkan harga dilakukan di modul Purchasing pada PO dengan mesin approval yang sama ([D-28](../wms/04-keputusan-dan-asumsi.md#d-28)).
3. **Catatan pemesanan per vendor** oleh role *Penindak Lanjut PR*: satu PRQ boleh dipesan ke beberapa vendor/toko online; tiap catatan berisi vendor (sistem menyarankan **vendor tetap** item), jenis vendor, nomor PO eksternal atau **nomor pesanan marketplace**, **nomor resi**, perkiraan tanggal datang, dan baris PRQ × jumlah dipesan (satu baris boleh dipecah). Vendor yang belum ada boleh dibuat **sementara** saat memesan dan dilengkapi Admin kemudian. Tidak ada harga. PRQ menjadi *Diteruskan* saat minimal satu baris dipesan.
4. Saat barang datang, staf membuat GRN dan **memilih catatan pemesanan & baris** yang dipenuhi; PRQ menjadi *Sebagian Terpenuhi* / *Dipenuhi* otomatis.
5. Barang yang ditunggu REQ direservasi otomatis ke REQ penunggu (urutan `required_date`) dan disarankan cross-dock ([BR-REQ-08](../wms/05-aturan-bisnis.md#br-req)).
6. PRQ bisa *Ditolak* atau *Dibatalkan* (alasan `*`, keterangan opsional) selama belum ada GRN. Barang gagal QC kembali ke vendor/toko lewat RTV.

Laporan Fase 1: PRQ terbuka per gudang/proyek, lewat perkiraan datang, dan pemenuhan PRQ vs GRN.

## 4. Tanggung jawab modul Purchasing

**Fase 1b — Purchasing inti** ([D-29](../wms/04-keputusan-dan-asumsi.md#d-29)), dibangun langsung setelah WMS inti:

1. Menerima `purchase_requested`, mengelompokkan PR menjadi PO per vendor, mencatat harga beli.
2. Menjadi pemilik master vendor (jenis, termin) dan **vendor tetap per item** dengan daftar harga; WMS hanya membaca prioritas vendor.
3. **Approval PO berdasarkan nilai uang** memakai mesin approval WMS sebagai paket bersama (`document_type = purchase_order`, [D-28](../wms/04-keputusan-dan-asumsi.md#d-28)); aturan diatur per company seperti dokumen WMS lainnya.
4. Mengirim PO ke WMS (`po_created/updated/cancelled`) agar GRN bisa merujuk PO dan memvalidasi jumlah diterima ≤ jumlah PO (kelebihan → keputusan Purchasing).
5. Menutup PO saat semua baris diterima atau dibatalkan.

**Fase 3 — Purchasing lengkap:**

6. Evaluasi vendor (ketepatan ETA, kualitas dari `goods_rejected`) dan perbandingan penawaran.
7. Meneruskan data PO + GRN ke Akuntansi untuk pencocokan tagihan vendor (three-way match) dan nota debit dari RTV.

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
| `po_created` | `po_id`, nomor PO, vendor, baris (item, qty, `prq_line_ref`, ETA) | Mengisi **catatan pemesanan** (satu per PO, `po_line_ref` per baris); PRQ → *Diteruskan*; GRN dapat dibuat "dari PO" |
| `po_updated` | Perubahan qty/ETA per baris | Perbarui ETA & jumlah yang ditunggu |
| `po_cancelled` | `po_id`, alasan | PRQ kembali ke *Diteruskan* tanpa PO, atau dibatalkan bila diminta |

Payload memakai amplop yang sama dengan kejadian stok ([Akuntansi §4.1](../akuntansi/01-lingkup-dan-integrasi-wms.md#41-payload-minimum)): `event_id`, `schema_version`, `company_id`, `occurred_at`, `source_type`, `source_id`. **Tidak ada harga** di kejadian yang masuk ke WMS.

## 6. Aturan integrasi

- Idempoten berdasarkan `event_id`; koreksi lewat kejadian baru, bukan edit.
- Satu baris PRQ boleh dipesan ke lebih dari satu vendor (beberapa catatan pemesanan / baris PO) dan dipenuhi oleh lebih dari satu GRN ([A-51](../wms/04-keputusan-dan-asumsi.md#a-51)).
- WMS tidak pernah menampilkan harga PO; bila layar GRN perlu menampilkan "sesuai PO", yang ditampilkan hanya jumlah & ETA.
- Master vendor: selama Fase 1–2 WMS pemilik; saat Purchasing aktif, dilakukan migrasi kepemilikan satu arah (WMS baca saja).

## 7. Pertanyaan untuk spesifikasi Purchasing nanti

1. ~~Apakah PR perlu approval anggaran (nilai uang) di Purchasing sebelum PO~~ — **dijawab 23 Sep 2026:** ya, pada PO di Purchasing dengan mesin approval WMS ([D-28](../wms/04-keputusan-dan-asumsi.md#d-28)).
2. Kebijakan kelebihan terima (over-receipt) vs PO: tolak, terima dengan approval, atau terima dan koreksi PO.
3. Evaluasi vendor: metrik apa yang dibaca dari WMS (ketepatan ETA, `goods_rejected`).
4. Apakah Purchasing dijual sebagai modul SaaS terpisah per company.
5. Kebijakan pembelian di toko online (akun toko milik siapa, bukti bayar, retur lewat marketplace) — dibagi antara Purchasing dan Akuntansi.
