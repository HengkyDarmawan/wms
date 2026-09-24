# Katalog Status & Enum

**Versi:** 0.10
**Tanggal:** 24 September 2026
**Status:** nilai divalidasi 23 Sep 2026 bersama [A-29](04-keputusan-dan-asumsi.md#a-29)–[A-49](04-keputusan-dan-asumsi.md#a-49); guard SJ/TRF memuat bagian dari [A-50](04-keputusan-dan-asumsi.md#a-50) (menunggu validasi); v0.5: PRQ `draft`, aksi baris REQ (A-54, A-55, A-56, A-61), `shipment_method`, `spot_check`, enum vendor; v0.6: bukti terima baik/rusak/kurang, keberatan klien, DSC `reship`, `meter_unit`; v0.7: enum yang sudah dipakai kode didaftarkan (`request_line_status`, `fulfillment_source`, `requester_type`, `destination_type`, `ownership_effect`, `proof_channel`, `discrepancy_origin`, `reservation_level`, `reservation_status`, `stock_event_type`, `capacity_mode`, `reason_context`, `uom_category_code`, `scope_type`, `user_status`, `login_result`, `company_status`) — tanpa status baru; v0.8: enum `receipt_type` (sumber GRN, sudah ada di ERD) didaftarkan dan langkah QC GRN diberi permission `receipt.qc` ([A-80](04-keputusan-dan-asumsi.md#a-80)) — tanpa status baru; v0.9: enum mesin approval yang sudah ada di ERD 08c didaftarkan (`approval_document_type`, `approver_type`, `decision_mode`, `approval_snapshot_status`, `approval_task_status`) ditambah `condition_match` ([A-87](04-keputusan-dan-asumsi.md#a-87)); permission approve per dokumen dipakai mesin approval ([A-86](04-keputusan-dan-asumsi.md#a-86)) — tanpa status dokumen baru; v0.10: enum `count_assignment_status` dan `adjustment_origin` (sudah ada di ERD 08c) didaftarkan, ADJ dan OPN tersambung ke mesin approval ([21-opname-penyesuaian](21-opname-penyesuaian.md), [A-95](04-keputusan-dan-asumsi.md#a-95)–[A-97](04-keputusan-dan-asumsi.md#a-97)) — tanpa status baru
**Dokumen terkait:** [Blueprint §7](01-blueprint.md#7-dokumen--alur-utama) · [Aturan Bisnis](05-aturan-bisnis.md) · [Glosarium](03-glosarium.md)

Dokumen ini adalah **satu-satunya sumber** nilai status dan enum. UI memakai kolom *Label*, kode memakai kolom *Enum* (`snake_case`, Inggris). Developer dan agen AI **tidak boleh** menambah status di luar katalog ini tanpa menaikkan versi dokumen ini.

Konvensi:
- Aksi ditulis sebagai permission `<modul>.<aksi>` (mis. `request.approve`).
- *Guard* = syarat yang harus terpenuhi sebelum transisi.
- *Efek* = perubahan pada ledger / reservasi / kejadian stok (rujuk [matriks kejadian](05-aturan-bisnis.md#14-matriks-kejadian-stok)).
- Tag fase `[F1]` `[F2]` `[F3]` mengikuti [Blueprint §18](01-blueprint.md#18-peta-modul--fase-rilis).

---

## 1. Status umum dokumen

Nilai yang dipakai bersama oleh banyak dokumen. Warna badge adalah usulan untuk NexaDash.

| Enum | Label UI | Arti | Badge |
|---|---|---|---|
| `draft` | Draf | Belum diajukan, boleh diedit/dihapus oleh pembuat | secondary |
| `submitted` | Diajukan | Sudah diajukan, menunggu tahap berikutnya | info |
| `under_review` | Ditinjau Staf | Ditinjau staf company (khusus permintaan klien) | info |
| `pending_approval` | Menunggu Approval | Sedang di lapis approval | warning |
| `approved` | Disetujui | Semua lapis approval selesai | primary |
| `in_progress` | Diproses | Dokumen anak (tugas/pengiriman) sedang berjalan | primary |
| `partially_fulfilled` | Sebagian Terpenuhi | Sebagian baris selesai, sisanya backorder | warning |
| `completed` | Selesai | Semua baris terpenuhi | success |
| `closed_short` | Ditutup dengan Sisa | Ditutup manual; sisa backorder dilepas | dark |
| `rejected` | Ditolak | Ditolak approver; alasan wajib `*`, keterangan opsional | danger |
| `cancelled` | Dibatalkan | Dibatalkan pengaju/admin; alasan wajib `*`, keterangan opsional; bila stok sudah bergerak, dibuat dokumen pembalik | danger |

Catatan: di semua tabel §2, guard "Alasan" berarti *Alasan* dari master Alasan **wajib** (`*`) dan *Keterangan* teks bebas **opsional** ([BR-GEN-11](05-aturan-bisnis.md#br-gen)). Field wajib di setiap form ditandai `*`.

## 2. Mesin status per dokumen

### 2.1 `REQ` Permintaan Material — `material_request` [F1]

| Dari | Ke | Aksi (permission) | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `draft` | `request.create` | Pemohon Internal, Klien | Proyek aktif | — |
| `draft` | `submitted` | `request.submit` | Pembuat | ≥1 baris; tanggal dibutuhkan terisi | timeline |
| `submitted` | `under_review` | otomatis | sistem | Pemohon = Klien ([A-07](04-keputusan-dan-asumsi.md#a-07)) | notifikasi staf |
| `submitted` | `pending_approval` | otomatis | sistem | Pemohon internal; gudang sumber terisi ([A-31](04-keputusan-dan-asumsi.md#a-31)) | snapshot aturan approval |
| `under_review` | `pending_approval` | `request.review` | Staf Gudang / Kepala Gudang | Semua baris non-katalog terpetakan ([A-39](04-keputusan-dan-asumsi.md#a-39)); gudang sumber terisi | perubahan baris tercatat di timeline; klien diberi tahu |
| `under_review` | `rejected` | `request.review` | Staf / Kepala Gudang | Alasan | notifikasi klien |
| `pending_approval` | `under_review` | `request.add_lines` | Klien | Klien menambah baris saat menunggu approval ([A-54](04-keputusan-dan-asumsi.md#a-54)) | snapshot approval dibuang; baris baru ditinjau |
| `pending_approval` | `approved` | `request.approve` | Approver sesuai aturan | Semua lapis setuju; setiap baris punya sumber: stok tersedia, transfer, atau PR ([A-30](04-keputusan-dan-asumsi.md#a-30)) | **reservasi lunak** dibuat; backorder → TRF/PRQ dibuat |
| `pending_approval` | `approved` | otomatis | sistem | Tidak ada aturan approval ([A-08](04-keputusan-dan-asumsi.md#a-08)) | sama |
| `pending_approval` | `rejected` | `request.approve` | Approver | Alasan | — |
| `approved` | `in_progress` | otomatis | sistem | PCK pertama dibuat, atau cross-dock dari GRN | — |
| `in_progress` | `partially_fulfilled` | otomatis | sistem | ≥1 SJ `delivered`, masih ada baris terbuka | — |
| `in_progress` / `partially_fulfilled` | `completed` | otomatis | sistem | Semua baris terkirim & dikonfirmasi terima | reservasi tersisa = 0 |
| `partially_fulfilled` | `closed_short` | `request.close_short` | Kepala Gudang / Pemohon | Alasan | reservasi & backorder sisa dilepas; PRQ terkait dibatalkan bila belum diteruskan |
| `draft`/`submitted`/`under_review`/`pending_approval` | `cancelled` | `request.cancel` | Pembuat / Admin | Alasan | — |
| `approved`/`in_progress` | `cancelled` | `request.cancel` | Kepala Gudang | Tidak ada SJ `shipped`; alasan | reservasi dilepas; PCK dibatalkan; PRQ dibatalkan bila belum diteruskan |

Aksi **tingkat baris** (tidak mengubah status dokumen): `request.add_lines` — Klien menambah baris saat `draft`/`submitted`/`under_review`; setelah `approved` membuat **REQ Tambahan** `origin = supplement` ([A-54](04-keputusan-dan-asumsi.md#a-54)) · `request.split_line` — staf memecah baris ke beberapa gudang sumber ([A-56](04-keputusan-dan-asumsi.md#a-56)) · `request.respond_substitution` — Klien menolak pengganti sebelum `substitution_deadline_at` ([A-55](04-keputusan-dan-asumsi.md#a-55)) · `request.request_cancel` / `request.confirm_cancel` — permintaan pembatalan baris oleh klien setelah `approved`, dikonfirmasi staf ([A-61](04-keputusan-dan-asumsi.md#a-61)) · `request.confirm_receipt` / `request.dispute_receipt` — pemohon menerima atau mengajukan keberatan (kurang/rusak) dalam `receipt_confirm_days`; keberatan membuka DSC ([A-63](04-keputusan-dan-asumsi.md#a-63), [BR-REQ-10](05-aturan-bisnis.md#br-req)).

### 2.2 `PCK` Tugas Picking — `pick_task` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `pending` (Menunggu) | otomatis / `pick.create` | sistem / Kepala Gudang | REQ/TRF `approved` | alokasi keras: bin/lot/potongan disarankan sesuai strategi |
| `pending` | `in_progress` (Dikerjakan) | `pick.start` | Staf Gudang | Bin tidak dibeku opname | — |
| `in_progress` | `completed` (Selesai) | `pick.complete` | Staf Gudang | Semua baris dipindai/dikonfirmasi; **short pick** wajib alasan ([A-35](04-keputusan-dan-asumsi.md#a-35)) | ledger: bin → Loading Area; short pick → bin ditandai hitung, sisa jadi backorder |
| `pending`/`in_progress` | `cancelled` | `pick.cancel` | Kepala Gudang | Alasan | barang di Loading Area dikembalikan ke bin (ledger balik); alokasi dilepas |

### 2.3 `SJ` Pengiriman / Surat Jalan — `shipment` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `prepared` (Disiapkan) | `shipment.create` | Staf Gudang | ≥1 PCK `completed` di Loading Area dengan tujuan sama (boleh dari beberapa REQ, [BR-SJ-09](05-aturan-bisnis.md#br-sj)); `shipment_method` terisi: `own_fleet` → kendaraan & driver, `carrier` → ekspedisi & resi, `self_delivered` → nama pembawa (transfer dalam proyek, [A-50](04-keputusan-dan-asumsi.md#a-50), [A-57](04-keputusan-dan-asumsi.md#a-57)) | — |
| `prepared` | `shipped` (Dikirim) | `shipment.ship` | Staf Gudang / Driver | Konfirmasi muat (foto opsional) | ledger: Loading Area → bin virtual *Dalam Perjalanan* (milik gudang asal); kejadian `goods_shipped` |
| `shipped` | `delivered` (Diterima) | `shipment.confirm_delivery` | Driver / penerima bertoken ([A-41](04-keputusan-dan-asumsi.md#a-41)) | Bukti terima per baris (per unit untuk serial/potongan): semua baris **baik** = dikirim; tanda tangan + foto | lihat [BR-SJ-04](05-aturan-bisnis.md#br-sj) untuk efek per tujuan |
| `shipped` | `partially_delivered` (Diterima Sebagian) | `shipment.confirm_delivery` | sama | Jumlah **baik** < dikirim (kurang dan/atau rusak; foto wajib bila rusak, [A-64](04-keputusan-dan-asumsi.md#a-64)) | efek untuk jumlah baik; rusak → `in_transit` kondisi `damaged`; kurang tetap `in_transit`; dokumen `DSC` dibuat otomatis |
| `prepared` | `cancelled` | `shipment.cancel` | Kepala Gudang | Alasan | barang tetap di Loading Area; PCK tetap `completed` |

Catatan: SJ setelah `shipped` **tidak bisa dibatalkan**; koreksi lewat `DSC` atau `RET`. *Konfirmasi muat* adalah catatan timeline pada transisi ke `shipped`, bukan status tersendiri.

### 2.4 `DSC` Selisih Pengiriman — `delivery_discrepancy` [F1] (baru, [A-35](04-keputusan-dan-asumsi.md#a-35))

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `open` (Terbuka) | otomatis | sistem | SJ `partially_delivered`, **atau** keberatan pemohon dalam `receipt_confirm_days` ([A-63](04-keputusan-dan-asumsi.md#a-63)) | kurang & rusak tetap di *Dalam Perjalanan* (rusak berkondisi `damaged`); rusak dibawa balik driver bila kendaraan sendiri ([A-65](04-keputusan-dan-asumsi.md#a-65)) |
| `open` | `resolved` (Diselesaikan) | `discrepancy.resolve` | Kepala Gudang | Disposisi per baris (jenis kurang/rusak): `returned_to_warehouse` / `adjusted` (hilang, alasan) / `claimed` (klaim ekspedisi) / `reship` (kirim pengganti); keputusan klien `still_needed` / `not_needed` ([BR-SJ-10](05-aturan-bisnis.md#br-sj)) | ledger sesuai disposisi; `reship` → backorder baris REQ; `not_needed` → sisa baris ditutup; rusak yang dibawa balik → GRN retur; kejadian `delivery_discrepancy` |

### 2.5 `GRN` Penerimaan Barang — `goods_receipt` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `draft` | `receipt.create` | Staf Gudang | Sumber: vendor (manual / PO [F3]) · SJ masuk · RET | — |
| `draft` | `received` (Diterima) | `receipt.receive` | Staf Gudang | Jumlah per baris; lot/serial/potongan terisi bila mode pelacakan menuntut | ledger: masuk ke bin Penerimaan (atau Karantina bila item wajib QC); kejadian `goods_received` / `stock_transferred` / `goods_returned` sesuai sumber |
| `received` | `completed` (Selesai) | `receipt.complete` | Staf Gudang | Hasil QC per baris terisi (`passed`/`quarantined`/`rejected`) bila QC aktif; PUT dibuat untuk baris `passed` | baris `rejected` tetap di Karantina menunggu `RTV`; cross-dock → langsung Loading Area |
| `draft` | `cancelled` | `receipt.cancel` | Kepala Gudang | Alasan | — |

Catatan: GRN `received` tidak bisa dibatalkan; koreksi lewat `ADJ` atau `RTV`. QC adalah **langkah** di dalam status `received`, bukan status; langkah itu memakai permission `receipt.qc` dan efek stoknya dijelaskan [A-78](04-keputusan-dan-asumsi.md#a-78). Baris yang `quarantined` boleh diputus ulang setelah GRN `completed`; bila lolos, PUT dibuat saat itu ([19-receipt-putaway](19-receipt-putaway.md)).

### 2.6 `PUT` Tugas Put-away — `putaway_task` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `pending` | otomatis | sistem | GRN `completed` | bin disarankan (kategori penyimpanan, kapasitas) |
| `pending` | `completed` | `putaway.complete` | Staf Gudang | Bin tujuan dipindai | ledger: Penerimaan → bin tujuan |
| `pending` | `cancelled` | `putaway.cancel` | Kepala Gudang | Alasan | barang tetap di bin Penerimaan |

### 2.7 `TRF` Transfer — `transfer` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `submitted` | `transfer.create` | Staf / Kepala Gudang / sistem (backorder) | Gudang asal ≠ tujuan atau proyek asal ≠ tujuan; termasuk dua Gudang Site proyek yang sama — transfer dalam proyek ([A-50](04-keputusan-dan-asumsi.md#a-50)) | — |
| `submitted` | `pending_approval` / `approved` | otomatis | sistem | Sesuai aturan approval | reservasi lunak di gudang asal |
| `pending_approval` | `approved` / `rejected` | `transfer.approve` | Approver | — | — |
| `approved` | `in_progress` | otomatis | sistem | PCK dibuat | — |
| `in_progress` | `completed` | otomatis | sistem | GRN tujuan `completed` | — |
| `submitted`/`pending_approval`/`approved` | `cancelled` | `transfer.cancel` | Pengaju / Kepala Gudang | Belum ada SJ `shipped`; alasan | reservasi dilepas |

### 2.8 `RET` Retur dari Proyek — `goods_return` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `submitted` | `return.create` | Pemohon Internal / Klien / Staf | Merujuk SJ asal atau proyek; baris jual-putus mengikuti [A-26](04-keputusan-dan-asumsi.md#a-26) | — |
| `submitted` | `pending_approval` / `approved` | otomatis | sistem | Sesuai aturan | — |
| `pending_approval` | `approved` / `rejected` | `return.approve` | Approver | — | — |
| `approved` | `in_progress` | otomatis | sistem | SJ balik dibuat (atau tanpa SJ bila diantar sendiri) | — |
| `in_progress` | `received` (Diterima) | otomatis | sistem | GRN jenis retur `received` | ledger masuk ke bin Retur |
| `received` | `sorted` (Dipilah) | `return.sort` | Staf Gudang | Setiap baris: `good` / `damaged` / `offcut` / `waste` | ledger: bin Retur → bin tujuan; offcut mendapat ID potongan + silsilah; kejadian `goods_returned` (dengan penanda kepemilikan) atau `asset_returned` |
| `submitted`/`pending_approval`/`approved` | `cancelled` | `return.cancel` | Pengaju | Belum ada SJ `shipped`; alasan | — |

### 2.9 `ISU` Pemakaian Material — `material_issue` [F1] (baru, [A-32](04-keputusan-dan-asumsi.md#a-32))

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `draft` | `issue.create` | Staf Gudang Site / Pemohon Internal | Gudang Site milik proyek; hanya item habis pakai | — |
| `draft` | `confirmed` (Dikonfirmasi) | `issue.confirm` | Staf Gudang Site | Stok tersedia cukup | ledger: bin Gudang Site → keluar (dipakai proyek); kejadian `material_consumed` |
| `draft` | `cancelled` | `issue.cancel` | Pembuat | — | — |

Pembalikan setelah `confirmed` hanya lewat dokumen ISU pembalik (jumlah negatif, alasan, approval).

### 2.10 `CNV` Konversi Material — `conversion` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `draft` | `conversion.create` | Staf Gudang | Proyek terisi ([D-10](04-keputusan-dan-asumsi.md#d-10)); input tersedia | — |
| `draft` | `pending_approval` | `conversion.submit` | Staf Gudang | Ada aturan approval | — |
| `draft` / `pending_approval` | `completed` | `conversion.complete` / `conversion.approve` | Staf / Approver | Neraca ukuran: input = output + offcut + waste + kerf | ledger: input keluar, output & offcut masuk (ID potongan baru + silsilah), waste ke bin Waste; kejadian `material_converted` |
| `draft` / `pending_approval` | `cancelled` | `conversion.cancel` | Pembuat | Alasan | — |

Pembalikan `completed` hanya bila **tidak ada output/offcut yang sudah dipakai** ([BR-CNV-05](05-aturan-bisnis.md#br-cnv)).

### 2.11 `AST` Serah Terima Aset — `asset_handover` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `checked_out` (Dipinjam) | otomatis | sistem | SJ berisi aset `delivered` | aset → bin virtual *On-site Proyek*; state aset `on_loan`; kejadian `asset_checked_out` |
| `checked_out` | `returned` (Dikembalikan) | otomatis | sistem | GRN retur berisi aset `received` | aset → bin Retur; state `returned` |
| `returned` | `inspected` (Diperiksa) | `asset.inspect` | Staf Gudang | Grade kondisi + foto | state → `available` / `maintenance` / `damaged`; kejadian `asset_returned` |

Aset `lost` / `written_off` diproses lewat `ADJ` ([BR-AST-04](05-aturan-bisnis.md#br-ast)).

### 2.12 `ADJ` Penyesuaian Stok — `stock_adjustment` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `submitted` | `adjustment.create` | Staf / Kepala Gudang / sistem (dari OPN, DSC) | Alasan dari master Alasan | — |
| `submitted` | `pending_approval` | otomatis | sistem | Manual: **selalu** ([A-09](04-keputusan-dan-asumsi.md#a-09)); dari OPN: dilewati, approval di sesi | — |
| `pending_approval` | `approved` / `rejected` | `adjustment.approve` | Approver | — | — |
| `approved` | `posted` (Diposting) | otomatis | sistem | — | ledger ±; kejadian `stock_adjusted` |
| `submitted` / `pending_approval` | `cancelled` | `adjustment.cancel` | Pengaju | — | — |

Koreksi ADJ `posted` hanya lewat **ADJ pembalik** (`reversal_of_id`, [BR-GEN-03](05-aturan-bisnis.md#br-gen)) yang melewati mesin status yang sama; izin melihat `adjustment.view` ditambah [A-95](04-keputusan-dan-asumsi.md#a-95).

### 2.13 `OPN` Sesi Stock Opname — `stock_count` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `planned` (Direncanakan) | `count.create` | Kepala Gudang / Auditor | Cakupan, tim, jenis (`spot_check` = cakupan kecil tanpa pembekuan, [BR-OPN-10](05-aturan-bisnis.md#br-opn)), pembekuan ya/tidak | — |
| `planned` | `in_progress` (Berjalan) | `count.start` | Kepala Gudang / Auditor | Tidak ada PCK `in_progress` di bin cakupan bila pembekuan aktif ([BR-OPN-02](05-aturan-bisnis.md#br-opn)) | bin dibeku; snapshot angka sistem (fisik) |
| `in_progress` | `recount` (Hitung Ulang) | otomatis | sistem | Ada baris selisih kelas *sedang* ([A-42](04-keputusan-dan-asumsi.md#a-42)) | penugasan penghitung berbeda |
| `in_progress` / `recount` | `reconciling` (Rekonsiliasi) | `count.reconcile` | Kepala Gudang / Auditor | Semua baris terhitung | draf ADJ per gudang dibuat |
| `reconciling` | `approved` | `count.approve` | Approver | Selisih kelas *besar* punya akar masalah; approver bukan penghitung sesi; sesi `annual`/audit disetujui Auditor Internal atau Manajemen ([BR-OPN-09](05-aturan-bisnis.md#br-opn)) | ADJ → `posted` (tidak untuk `spot_check`); bin dibuka |
| `approved` | `closed` (Ditutup) | otomatis | sistem | Laporan PDF terbit | — |
| `planned` | `cancelled` | `count.cancel` | Pembuat | — | — |

Penolakan approval tidak mengubah status: sesi tetap `reconciling` dan diajukan ulang lewat `count.reconcile` ([A-97](04-keputusan-dan-asumsi.md#a-97)). Izin `count.view`, `count.assign` (penugasan penghitung), `count.record` (hitung buta) ditambah [A-95](04-keputusan-dan-asumsi.md#a-95).

### 2.14 `WST` Berita Acara Waste — `waste_disposal` [F1]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `submitted` | `waste.create` | Staf Gudang | Baris dari bin Waste; disposisi `disposed` / `sold_scrap` / `reused` | — |
| `submitted` | `pending_approval` / `approved` | otomatis | sistem | Sesuai aturan | — |
| `pending_approval` | `approved` / `rejected` | `waste.approve` | Approver | — | — |
| `approved` | `closed` | `waste.close` | Staf Gudang | Bukti (foto/berita acara) | ledger: bin Waste → keluar (atau kembali ke stok bila `reused`); kejadian `waste_disposed` |
| `submitted` / `pending_approval` | `cancelled` | `waste.cancel` | Pengaju | — | — |

### 2.15 `PRQ` Purchase Request — `purchase_request` [F1 manual, F3 terhubung]

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `draft` | otomatis | sistem (titik pesan ulang) | Stok tersedia < titik pesan ulang; belum ada draf/PRQ terbuka untuk item & gudang itu ([BR-REQ-11](05-aturan-bisnis.md#br-req)) | `origin = reorder_point` |
| `draft` | `submitted` | `pr.submit` | Kepala Gudang | Baris ditinjau | kejadian `purchase_requested` |
| `draft` | `cancelled` | `pr.cancel` | Kepala Gudang | Alasan | — |
| — | `submitted` | otomatis / `pr.create` | sistem (backorder REQ) / Staf / Kepala Gudang | Item terdefinisi (non-katalog sudah dipetakan) | kejadian `purchase_requested` |
| `submitted` | `pending_approval` / `approved` | otomatis | sistem | Sesuai aturan (opsional; kondisi boleh jenis vendor & asal, [BR-APR-07](05-aturan-bisnis.md#br-apr)) | — |
| `pending_approval` | `approved` / `rejected` | `pr.approve` | Approver | — | — |
| `approved` | `forwarded` (Diteruskan) | `pr.order` | Penindak Lanjut PR | Minimal satu baris punya **catatan pemesanan** (vendor/toko online, nomor PO/pesanan, resi, ETA — [A-51](04-keputusan-dan-asumsi.md#a-51)); vendor baru boleh dibuat sementara ([A-53](04-keputusan-dan-asumsi.md#a-53)) | — |
| `forwarded` | `partially_fulfilled` / `fulfilled` (Dipenuhi) | otomatis | sistem | Baris GRN merujuk baris catatan pemesanan ([A-47](04-keputusan-dan-asumsi.md#a-47)); `fulfilled` bila semua baris `qty_received ≥ qty_base` | reservasi otomatis ke REQ penunggu |
| `submitted` / `pending_approval` / `approved` / `forwarded` | `cancelled` | `pr.cancel` | Penindak Lanjut PR | Alasan; belum ada GRN | kejadian `purchase_request_cancelled` |

### 2.16 `RTV` Retur ke Vendor — `vendor_return` [F1] (baru, [A-34](04-keputusan-dan-asumsi.md#a-34))

| Dari | Ke | Aksi | Aktor | Guard | Efek |
|---|---|---|---|---|---|
| — | `submitted` | `vendor_return.create` | Staf Gudang | Baris dari Karantina hasil QC `rejected` (atau alasan lain) | — |
| `submitted` | `pending_approval` / `approved` | otomatis | sistem | Sesuai aturan | — |
| `pending_approval` | `approved` / `rejected` | `vendor_return.approve` | Approver | — | — |
| `approved` | `shipped` (Dikirim) | `vendor_return.ship` | Staf Gudang | Surat jalan retur | ledger: Karantina → keluar; kejadian `goods_rejected` |
| `shipped` | `completed` | `vendor_return.complete` | Penindak Lanjut PR | Konfirmasi vendor (ganti barang → GRN baru) | — |
| `submitted` / `pending_approval` / `approved` | `cancelled` | `vendor_return.cancel` | Pengaju | Alasan | — |

## 3. Enum lain

| Enum | Nilai (`kode` = Label) |
|---|---|
| `stock_status` (kondisi saldo) | `available` = Tersedia · `quarantine` = Karantina · `damaged` = Rusak. *Dicadangkan* bukan status, melainkan tabel `stock_reservation` ([A-29](04-keputusan-dan-asumsi.md#a-29)). |
| `bin_type` | `storage` = Penyimpanan · `receiving` = Penerimaan · `staging` = Loading Area · `quarantine` = Karantina QC · `return` = Retur · `waste` = Waste · `in_transit` = Dalam Perjalanan (virtual) · `on_site` = On-site Proyek (virtual) |
| `bin_status` | `active` = Aktif · `frozen` = Dibeku (opname) · `inactive` = Nonaktif |
| `ownership_model` | `consumable` = Habis pakai / jual putus · `asset` = Aset dipinjamkan · `both` = Keduanya |
| `line_ownership` (per baris REQ, [A-38](04-keputusan-dan-asumsi.md#a-38)) | `buy` = Beli · `loan` = Pinjam |
| `tracking_mode` | `none` · `lot` = Batch/Lot · `serial` = Serial number · `piece` = Per potong |
| `removal_strategy` | `fifo` · `fefo` · `manual` · `offcut_first` = Sisa potongan dulu |
| `asset_state` | `available` · `reserved` · `in_transit` · `on_loan` = Dipinjam · `returned` = Dikembalikan · `inspection` = Pemeriksaan · `maintenance` · `damaged` = Rusak · `lost` = Hilang · `written_off` = Dihapuskan |
| `qc_result` | `passed` = Lolos · `quarantined` = Karantina · `rejected` = Ditolak |
| `receipt_type` (sumber GRN, [A-33](04-keputusan-dan-asumsi.md#a-33)) | `vendor` = Dari vendor · `transfer` = Transfer masuk (SJ ke gudang) · `return` = Retur dari proyek (titik sambung modul Retur, [BR-GEN-10](05-aturan-bisnis.md#br-gen)) |
| `condition_grade` | `A` = Baik · `B` = Layak · `C` = Rusak ringan · `D` = Rusak berat |
| `return_sorting` | `good` = Layak · `damaged` = Rusak · `offcut` = Offcut · `waste` = Waste |
| `waste_disposition` | `disposed` = Dibuang · `sold_scrap` = Dijual scrap · `reused` = Dipakai ulang |
| `discrepancy_disposition` | `returned_to_warehouse` = Kembali ke gudang · `adjusted` = Disesuaikan (hilang) · `claimed` = Klaim ekspedisi · `reship` = Kirim pengganti ([A-64](04-keputusan-dan-asumsi.md#a-64)) |
| `discrepancy_type` ([A-64](04-keputusan-dan-asumsi.md#a-64)) | `missing` = Kurang · `damaged` = Rusak |
| `client_decision` | `still_needed` = Masih perlu (default) · `not_needed` = Tidak perlu (sisa ditutup) |
| `receipt_confirmation` ([A-63](04-keputusan-dan-asumsi.md#a-63)) | `confirmed` = Diterima · `disputed` = Keberatan · `auto_confirmed` = Otomatis (lewat batas) |
| `pod_unit_condition` | `good` = Baik · `damaged` = Rusak · `missing` = Kurang (per unit serial/potongan pada bukti terima) |
| `project_status` ([A-40](04-keputusan-dan-asumsi.md#a-40)) | `active` = Aktif · `closed` = Ditutup · `cancelled` = Dibatalkan · `archived` = Diarsipkan |
| `subscription_status` | `trial` · `active` = Aktif · `past_due` = Jatuh Tempo (tenggang) · `suspended` = Ditangguhkan · `terminated` = Diakhiri |
| `approval_decision` | `approved` = Setuju · `rejected` = Tolak · `delegated` = Didelegasikan · `escalated` = Dieskalasi |
| `approval_channel` | `web` · `whatsapp` (keputusan); kanal lapis aturan menambah `both` = Web & WhatsApp (ERD 08c; F1 selalu `web`) |
| `approval_document_type` ([20-approval](20-approval.md)) | Jenis dokumen beraturan approval (alur 9), nilai = nama di kode Glosarium: `material_request` (REQ) · `transfer` (TRF) · `goods_return` (RET) · `conversion` (CNV) · `stock_adjustment` (ADJ) · `waste_disposal` (WST) · `purchase_request` (PRQ) · `vendor_return` (RTV) · `stock_count` (OPN) · `material_issue` (ISU pembalik). F1 tersambung: REQ, RTV, ADJ, OPN |
| `approver_type` (lapis aturan, Blueprint §8.1) | `user` = User tertentu · `position` = Jabatan · `role` = Role (dalam cakupan dokumen) · `direct_manager` = Atasan langsung pemohon · `warehouse_head` = Kepala gudang terkait · `project_pic` = PIC proyek |
| `decision_mode` (cara putus lapis) | `sequential` = Berurutan · `any` = Cukup salah satu · `all` = Semua harus setuju |
| `condition_match` ([A-87](04-keputusan-dan-asumsi.md#a-87)) | `all` = Semua kondisi terpenuhi · `any` = Salah satu kondisi terpenuhi |
| `approval_snapshot_status` (bukan status dokumen) | `pending` = Menunggu · `approved` = Disetujui · `rejected` = Ditolak · `cancelled` = Dibatalkan (dokumen ditarik/diajukan ulang) |
| `approval_task_status` | `open` = Terbuka · `decided` = Diputus · `superseded` = Digantikan · `expired` = Dialihkan (eskalasi) |
| `notification_channel` | `in_app` · `email` · `whatsapp` |
| `count_type` | `monthly` · `annual` · `adhoc` · `spot_check` = Pemeriksaan mendadak (tanpa pembekuan, [BR-OPN-10](05-aturan-bisnis.md#br-opn)) · `cycle_abc` [F2] |
| `variance_class` | `minor` = Kecil (auto) · `moderate` = Sedang (hitung ulang) · `major` = Besar (approval + akar masalah) |
| `root_cause_category` | `mispick` = Salah ambil · `misplaced` = Salah taruh · `wrong_uom` = Salah satuan · `damaged_lost` = Rusak/hilang · `unrecorded_txn` = Transaksi tidak tercatat · `other` |
| `count_assignment_status` (penugasan penghitung, ERD 08c; bukan status dokumen) | `pending` = Belum dihitung · `done` = Selesai |
| `document_template_type` (jenis template cetak, [18-template-dokumen-label](18-template-dokumen-label.md); bukan status) | Dokumen: `shipment` = Surat Jalan · `proof_of_delivery` = Bukti Terima · `pick_task` = Picklist · `delivery_discrepancy` = BA Selisih Pengiriman · `vendor_return` = Surat Retur ke Vendor · `stock_adjustment` = BA Penyesuaian · `stock_count` = Laporan Stock Opname · `asset_handover` = BA Serah Terima Aset (stub) · `waste_disposal` = BA Waste (stub). Label: `label_bin` · `label_item` · `label_lot` · `label_piece` |
| `paper_size` (kertas cetak, [A-120](04-keputusan-dan-asumsi.md#a-120)) | `a4` = A4 tegak · `a4_landscape` = A4 lanskap · `label_50x30` = Label thermal 50×30 mm · `label_a4_3x8` = Lembar label A4 3×8 |
| `adjustment_origin` (asal ADJ, ERD 08c) | `manual` = Manual · `count` = Hasil opname · `discrepancy` = Selisih pengiriman (titik sambung, [A-98](04-keputusan-dan-asumsi.md#a-98)) · `asset_lost` = Aset hilang (titik sambung modul Aset) |
| `sync_status` (PWA, [F2]) | `queued` · `synced` · `conflict` = Perlu tinjauan · `held` = Ditahan (langganan ditangguhkan) |
| `item_status` | `active` · `provisional` = Sementara (dibuat dari baris non-katalog) · `inactive` |
| `vendor_type` ([A-52](04-keputusan-dan-asumsi.md#a-52)) | `company` = Perusahaan · `shop` = Toko · `online_marketplace` = Toko online · `individual` = Perorangan |
| `vendor_status` ([A-53](04-keputusan-dan-asumsi.md#a-53)) | `active` · `provisional` = Sementara (dibuat saat memesan) · `inactive` |
| `purchase_request_origin` | `backorder` = Dari backorder REQ · `manual` · `reorder_point` = Titik pesan ulang |
| `request_origin` ([A-54](04-keputusan-dan-asumsi.md#a-54)) | `regular` = Biasa · `supplement` = REQ Tambahan |
| `substitution_response` ([A-55](04-keputusan-dan-asumsi.md#a-55)) | `accepted` = Diterima · `rejected` = Ditolak klien · `expired` = Lewat batas (dianggap setuju) |
| `shipment_method` ([A-57](04-keputusan-dan-asumsi.md#a-57)) | `own_fleet` = Kendaraan sendiri · `carrier` = Ekspedisi · `self_delivered` = Diantar sendiri |
| `meter_unit` ([A-66](04-keputusan-dan-asumsi.md#a-66)) | `hour` = Jam mesin · `km` = Kilometer · `none` = Tanpa meter |
| `request_line_status` (baris REQ) | `open` = Terbuka · `closed` = Selesai · `cancelled` = Dibatalkan. Baris tidak pernah dihapus (P-03); baris yang dibatalkan tetap tampil |
| `requester_type` ([A-07](04-keputusan-dan-asumsi.md#a-07)) | `internal` = Internal · `client` = Klien |
| `fulfillment_source` (baris REQ, [BR-REQ-05](05-aturan-bisnis.md#br-req)) | `stock` = Stok tersedia · `transfer` = Transfer antar gudang · `purchase` = Pembelian |
| `destination_type` (tujuan SJ, [BR-SJ-04](05-aturan-bisnis.md#br-sj)) | `project_client` = Proyek klien · `site_warehouse` = Gudang Site · `warehouse` = Gudang lain · `vendor` = Vendor |
| `ownership_effect` (baris SJ, [BR-SJ-04](05-aturan-bisnis.md#br-sj)) | `sold` = Jual putus · `transfer` = Transfer · `loan` = Pinjam |
| `proof_channel` (bukti terima, [A-41](04-keputusan-dan-asumsi.md#a-41)) | `driver_pwa` = Aplikasi driver · `token_link` = Tautan bertoken |
| `discrepancy_origin` (DSC, [A-63](04-keputusan-dan-asumsi.md#a-63)) | `partial_delivery` = Bukti terima sebagian · `client_dispute` = Keberatan klien |
| `reservation_level` ([BR-STK-04](05-aturan-bisnis.md#br-stk)) | `soft` = Lunak (item & gudang) · `hard` = Keras (alokasi bin) |
| `reservation_status` | `active` = Aktif · `consumed` = Terpenuhi · `released` = Dilepas. Hanya `active` yang mengurangi stok tersedia ([BR-STK-03](05-aturan-bisnis.md#br-stk)) |
| `stock_event_type` | Nilai = kolom kejadian di [matriks kejadian stok](05-aturan-bisnis.md#14-matriks-kejadian-stok): `goods_received` · `goods_rejected` · `goods_shipped` · `goods_delivered` · `goods_returned` · `stock_transferred` · `delivery_discrepancy` · `material_consumed` · `material_converted` · `waste_disposed` · `stock_adjusted` · `asset_checked_out` · `asset_returned` · `asset_lost_or_damaged` · `purchase_requested` · `purchase_request_cancelled` |
| `capacity_mode` (kategori penyimpanan, [A-37](04-keputusan-dan-asumsi.md#a-37)) | `warn` = Peringatan · `block` = Blokir |
| `reason_context` (master Alasan, [BR-GEN-02](05-aturan-bisnis.md#br-gen)) | `reject` = Penolakan · `cancel` = Pembatalan · `adjustment` = Penyesuaian stok · `waste` = Waste · `damage` = Kerusakan · `short_pick` = Kekurangan pick · `discrepancy` = Selisih pengiriman · `lost` = Kehilangan |
| `uom_category_code` (kategori satuan bawaan) | `count` = Jumlah · `length` = Panjang · `weight` = Berat · `volume` = Volume · `area` = Luas |
| `scope_type` (penugasan role, [BR-GEN-09](05-aturan-bisnis.md#br-gen)) | `all` = Semua · `warehouse` = Gudang · `project` = Proyek |
| `user_status` (nilai turunan, bukan kolom) | `invited` = Diundang · `active` = Aktif · `inactive` = Nonaktif · `locked` = Terkunci — dihitung dari `is_active`, `locked_until`, dan undangan yang belum diterima |
| `login_result` (catatan login) | `success` = Berhasil · `invalid` = Email atau password salah · `locked` = Akun terkunci · `inactive` = Akun nonaktif · `no_role` = Tanpa penugasan role · `wrong_portal` = Salah pintu masuk · `suspended` = Langganan diakhiri |
| `company_status` (database pusat; bukan status langganan) | `provisioning` = Disiapkan · `active` = Aktif · `suspended` = Ditangguhkan · `terminated` = Diakhiri |

## 4. Blok mesin-status untuk prompt & tes

Ringkasan yang bisa disalin langsung ke prompt Claude Code atau ke tes transisi. Sumber kebenaran tetap tabel di atas.

```yaml
material_request:
  initial: draft
  transitions:
    - {from: draft, to: submitted, action: request.submit}
    - {from: submitted, to: under_review, action: system, when: requester_is_client}
    - {from: submitted, to: pending_approval, action: system}
    - {from: under_review, to: pending_approval, action: request.review}
    - {from: under_review, to: rejected, action: request.review}
    - {from: pending_approval, to: approved, action: request.approve}
    - {from: pending_approval, to: rejected, action: request.approve}
    - {from: approved, to: in_progress, action: system}
    - {from: in_progress, to: partially_fulfilled, action: system}
    - {from: [in_progress, partially_fulfilled], to: completed, action: system}
    - {from: partially_fulfilled, to: closed_short, action: request.close_short}
    - {from: [draft, submitted, under_review, pending_approval], to: cancelled, action: request.cancel}
    - {from: [approved, in_progress], to: cancelled, action: request.cancel, guard: no_shipped_shipment}
  terminal: [completed, closed_short, rejected, cancelled]
shipment:
  initial: prepared
  transitions:
    - {from: prepared, to: shipped, action: shipment.ship}
    - {from: shipped, to: delivered, action: shipment.confirm_delivery}
    - {from: shipped, to: partially_delivered, action: shipment.confirm_delivery, effect: create_dsc}
    - {from: prepared, to: cancelled, action: shipment.cancel}
  terminal: [delivered, partially_delivered, cancelled]
goods_receipt:
  initial: draft
  transitions:
    - {from: draft, to: received, action: receipt.receive, effect: ledger_in}
    - {from: received, to: completed, action: receipt.complete, effect: create_putaway}
    - {from: draft, to: cancelled, action: receipt.cancel}
  terminal: [completed, cancelled]
stock_count:
  initial: planned
  transitions:
    - {from: planned, to: in_progress, action: count.start, effect: freeze_bins}
    - {from: in_progress, to: recount, action: system}
    - {from: [in_progress, recount], to: reconciling, action: count.reconcile}
    - {from: reconciling, to: approved, action: count.approve, effect: post_adjustments}
    - {from: approved, to: closed, action: system}
    - {from: planned, to: cancelled, action: count.cancel}
  terminal: [closed, cancelled]
```

Dokumen lain (PCK, PUT, TRF, RET, ISU, CNV, AST, ADJ, WST, PRQ, RTV, DSC) mengikuti tabel §2 dengan pola yang sama; blok YAML-nya dibuat di spesifikasi modul masing-masing (Part 4).
