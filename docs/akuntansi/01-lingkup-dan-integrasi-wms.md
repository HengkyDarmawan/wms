# Modul Akuntansi — Lingkup & Integrasi dengan WMS

**Versi:** 0.3
**Tanggal:** 23 September 2026
**Status:** kerangka awal; spesifikasi lengkap modul Akuntansi dibuat terpisah nanti. Daftar kejadian mengikuti [matriks kejadian stok](../wms/05-aturan-bisnis.md#14-matriks-kejadian-stok) v0.3
**Dokumen terkait:** [Blueprint §15](../wms/01-blueprint.md#15-integrasi) · [Aturan Bisnis](../wms/05-aturan-bisnis.md) · [Purchasing](../purchasing/01-lingkup-dan-integrasi-wms.md) · [Keputusan D-07](../wms/04-keputusan-dan-asumsi.md#d-07)

---

## 1. Prinsip pembagian

| | WMS | Akuntansi |
|---|---|---|
| Sumber kebenaran | **Kuantitas** & lokasi barang | **Nilai uang** |
| Menyimpan harga? | Tidak | Ya |
| Pemicu | Setiap pergerakan ledger menerbitkan **satu kejadian stok** | Menerima kejadian, memberi nilai, membuat jurnal/tagihan |

WMS tidak pernah menampilkan atau meminta input harga ([D-07](../wms/04-keputusan-dan-asumsi.md#d-07)). Bila layar WMS perlu menampilkan nilai (mis. nilai waste), angka dibaca dari modul Akuntansi dan hanya terlihat oleh role yang diizinkan.

## 2. Yang dipindahkan dari prototipe lama ke Akuntansi

| Fitur di prototipe | Ke Akuntansi sebagai |
|---|---|
| Field `Capital` (harga modal) & `Price` di master barang | Daftar harga beli, harga jual, tarif sewa |
| `Payment` Full / Down Payment pada permintaan barang | Termin pembayaran pada penjualan/sewa |
| Modal **Pay** + unggah bukti transfer + `Remaining Payment` | Penerimaan pembayaran klien & piutang |
| `Sub Price`, `Total` pada item outbound | Faktur penjualan |
| "Download Invoice Delivery" | Faktur (surat jalan tetap di WMS, tanpa harga) |
| `unit_time`, `minimum_order` (sisa fitur sewa) | Aturan tarif & minimum sewa |

## 3. Tanggung jawab modul Akuntansi (terkait persediaan)

1. **Penilaian persediaan** dengan metode yang dipilih company (rata-rata tertimbang atau FIFO), berdasarkan kejadian stok dari WMS.
2. **Jurnal persediaan** untuk penerimaan, pemakaian di proyek, pengiriman jual-putus, retur, transfer antar entitas bila ada, penyesuaian opname, selisih pengiriman, dan waste.
3. **Biaya proyek:** material yang **dipakai** (`material_consumed`) dan yang **dijual** (`goods_delivered`, jual putus) dibebankan/ditagihkan ke proyek.
4. **Konversi material:** membagi nilai input ke output dan offcut, membebankan waste serta kerf.
5. **Penjualan barang jual-putus:** faktur ke klien berdasarkan `goods_delivered`; nota kredit berdasarkan `goods_returned` dengan `ownership = sold`.
6. **Tagihan sewa aset:** dari `usage_days` per aset per proyek (`asset_checked_out` → `asset_returned`), mengikuti siklus tagih yang diatur.
7. **Kerusakan & kehilangan aset:** tagihan ganti rugi atau penghapusan.
8. **Pembayaran klien** (DP, pelunasan, bukti transfer) dan piutang.
9. **Retur ke vendor:** nota debit / pengurangan tagihan vendor berdasarkan `goods_rejected`.

> **Tagihan langganan SaaS** (company membayar ke pemilik platform) **bukan** bagian modul ini; dikelola modul Platform di database pusat.

## 4. Kejadian stok yang diterbitkan WMS

Satu kejadian per satu pergerakan ledger ([BR §14](../wms/05-aturan-bisnis.md#14-matriks-kejadian-stok)). Tidak ada kejadian ganda untuk satu pergerakan: pengiriman ke Gudang Site menghasilkan `goods_shipped` lalu `stock_transferred` (dua pergerakan), **bukan** `goods_delivered`; aset menghasilkan `goods_shipped` lalu `asset_checked_out`.

### 4.1 Payload minimum

```json
{
  "event_id": "uuid",
  "schema_version": "1.0",
  "company_id": 12,
  "event_type": "goods_delivered",
  "occurred_at": "2026-09-23T07:15:00Z",
  "recorded_at": "2026-09-23T07:15:03Z",
  "timezone": "Asia/Jakarta",
  "source_type": "shipment",
  "source_id": 981,
  "source_number": "SJ/CKG/2026/09/0042",
  "project_id": 55,
  "from_bin_id": 301,
  "to_bin_id": null,
  "reverses_event_id": null,
  "meta": { "ownership": "sold", "client_id": 7 },
  "lines": [
    { "item_id": 1001, "lot_id": null, "serial_id": null, "piece_id": null,
      "qty_base": "24.0000", "base_uom": "pcs", "source_line_id": 4411 }
  ]
}
```

`occurred_at` = waktu pergerakan ledger (UTC). Tidak ada harga di payload.

### 4.2 Daftar kejadian

| Kejadian | Dipicu oleh | Penanda di `meta` | Kegunaan di Akuntansi |
|---|---|---|---|
| `goods_received` | GRN (vendor) `received` | `vendor_id`, `po_ref`, `prq_ref` | Menambah persediaan; mencocokkan dengan PO/tagihan vendor |
| `goods_rejected` | RTV `shipped` | `vendor_id`, `grn_ref`, `reason` | Nota debit / pengurangan tagihan vendor |
| `goods_shipped` | SJ `shipped` | `destination_type` (project_client / site_warehouse / warehouse) | Persediaan berpindah ke *dalam perjalanan* (masih milik gudang asal) |
| `goods_delivered` | SJ `delivered` / bagian diterima dari `partially_delivered`, baris jual-putus ke klien | `ownership = sold`, `client_id` | Dasar faktur jual; keluar dari persediaan |
| `asset_checked_out` | SJ `delivered`, baris aset | `serial_id`, `due_return_date` | Mulai hitung hari sewa |
| `stock_transferred` | GRN (dari SJ) `received` | `from_warehouse_id`, `to_warehouse_id`, `from_project_id`, `to_project_id` | Pemindahan antar gudang/proyek; tidak ada perubahan kepemilikan |
| `delivery_discrepancy` | DSC `resolved` | `disposition` per baris, `carrier_id` | Kerugian dalam perjalanan / klaim ekspedisi / kembali ke persediaan |
| `material_consumed` | ISU `confirmed` | `project_id` | **Beban material proyek** |
| `goods_returned` | RET `sorted` | `ownership = sold \| company`, `sorting` per baris | `sold` → nota kredit/retur penjualan; `company` → pembalik beban proyek (bila sebelumnya `material_consumed`) atau sekadar pindah lokasi |
| `asset_returned` | AST `inspected` | `serial_id`, `condition_grade`, `usage_days` | Akhir hari sewa; kondisi aset |
| `asset_lost_or_damaged` | aksi tandai hilang / inspeksi grade C–D | `serial_id`, `state` | Ganti rugi; penghapusan menunggu `stock_adjusted` |
| `material_converted` | CNV `completed` | `inputs`, `outputs`, `offcuts`, `waste`, `kerf` | Alokasi nilai input → output, offcut, waste, kerf |
| `waste_disposed` | WST `closed` | `disposition` | Beban waste / pendapatan scrap |
| `stock_adjusted` | ADJ `posted` | `reason_code`, `count_session_ref` | Selisih persediaan; penghapusan aset |

Kejadian pembalik memakai `event_type` yang sama dengan asal dan mengisi `reverses_event_id`.

## 5. Aturan integrasi

- **Idempoten:** Akuntansi menyimpan `event_id` yang sudah diproses dan mengabaikan duplikat.
- **Urutan:** kejadian diproses berurutan per `source_type` + `source_id`; `occurred_at` dipakai untuk periode akuntansi.
- **Koreksi** tidak mengedit kejadian lama; WMS menerbitkan kejadian pembalik.
- **Versi skema:** perubahan payload menaikkan `schema_version`; Akuntansi wajib menerima versi lama selama 2 rilis.
- **Rekonsiliasi berkala:** laporan pembanding kuantitas WMS vs kuantitas yang sudah dinilai di Akuntansi per akhir bulan, per gudang & proyek.
- **Mekanisme Fase 1:** WMS menulis kejadian ke tabel *outbox* di database tenant. Konsumsi oleh Akuntansi (antrean internal vs webhook/API) ditentukan di Part 3.

## 6. Pertanyaan untuk spesifikasi Akuntansi nanti

1. Metode penilaian persediaan yang diizinkan per company.
2. Aturan alokasi nilai pada konversi (per ukuran/berat/nilai relatif).
3. Siklus dan rumus tagihan sewa (harian, mingguan, 28/30 hari, minimum sewa); apakah `usage_days` hari kalender atau hari kerja.
4. Perlakuan `material_consumed` vs `goods_delivered` untuk proyek yang sama (beban vs penjualan) dan dampaknya pada laporan laba proyek.
5. Apakah modul Akuntansi dijual sebagai modul SaaS terpisah per company.
