# Spesifikasi Modul — `picking` & `shipment` (Picking, Surat Jalan, Bukti Terima, Selisih)

**Versi:** 0.13
**Tanggal:** 25 September 2026
**Status:** terimplementasi (Fase 1) — modul keenam setelah [Request](14-request.md); v0.5: PCK/SJ melayani TRF dan RET ([22-retur-transfer](22-retur-transfer.md)); v0.6: SJ aset diterima proyek melahirkan AST dan memperkaya `asset_checked_out` ([25-aset](25-aset.md), [A-163](04-keputusan-dan-asumsi.md#a-163)); v0.7: alokasi PCK mengikuti strategi pengambilan & mengurangi alokasi keras PCK lain per baris saldo; DSC `client_dispute` dari keberatan pemohon diselesaikan tanpa pergerakan stok ([27-pendukung-f1](27-pendukung-f1.md), [A-185](04-keputusan-dan-asumsi.md#a-185), [A-188](04-keputusan-dan-asumsi.md#a-188)); v0.9: halaman penerima bertoken `/terima/{token}` dan unggah foto/tanda tangan bukti terima ([A-231](04-keputusan-dan-asumsi.md#a-231), §6, §13.3)
**Modul:** `picking`, `shipment`
**Fase:** F1
**Dokumen terkait:** [Blueprint §7](01-blueprint.md#7-dokumen--alur-utama) · [Aturan Bisnis §BR-SJ](05-aturan-bisnis.md#br-sj) · [Katalog Status §2.2–§2.4](06-katalog-status-dan-enum.md) · [Glosarium](03-glosarium.md) · [Model data dokumen](08b-model-data-stok-dokumen.md) · [Proses bisnis alur 1](07-proses-bisnis.md)
**Ketergantungan modul:** `stock` (satu-satunya pintu tulis saldo), `warehouse` (bin Loading Area dan Dalam Perjalanan), `request` (sumber alokasi dan backorder), `master` (kendaraan, ekspedisi, alasan). Modul `receipt` ([19](19-receipt-putaway.md)), `transfer`, dan `return` ([22](22-retur-transfer.md)) sudah ada dan memakai PCK/SJ modul ini.

---

## 1. Tujuan & lingkup

Di sinilah barang benar-benar bergerak. REQ hanya menjanjikan; modul ini yang mengambil barang dari bin, memuatnya, mengantarnya, dan mencatat apa yang sebenarnya sampai.

Empat dokumen, satu rantai:

- **PCK** — tugas mengambil barang dari bin tertentu ke Loading Area.
- **SJ** — surat jalan: apa yang dimuat, dibawa siapa, ke mana.
- **Bukti terima** — apa yang benar-benar diterima, per baris dan per unit.
- **DSC** — selisih antara yang dikirim dan yang diterima, beserta nasib barangnya.

Yang paling sering salah dipahami: **kurang dan rusak tidak hilang dari pembukuan**. Keduanya tetap tercatat sebagai stok gudang asal di bin *Dalam Perjalanan* sampai DSC diselesaikan ([BR-SJ-10](05-aturan-bisnis.md#br-sj)). Barang tidak menguap hanya karena penerima menolaknya.

## 2. Aktor & permission

Permission disimpan dengan `module = picking` dan `module = shipment`.

| Role bawaan | Permission |
|---|---|
| Admin Company | semua permission kedua modul |
| Manajemen | `pick.view`, `shipment.view`, `discrepancy.view` |
| Kepala Gudang | semua kecuali `shipment.confirm_delivery` |
| Staf Gudang | `pick.view`, `pick.start`, `pick.complete`, `shipment.view`, `shipment.create`, `shipment.ship` |
| Driver | `pick.view`, `shipment.view`, `shipment.ship`, `shipment.confirm_delivery` |
| Klien | `shipment.view` (hanya SJ proyeknya) |
| Auditor Internal & Eksternal | `pick.view`, `shipment.view`, `discrepancy.view` |

Daftar: `pick.view`, `pick.create`, `pick.start`, `pick.complete`, `pick.cancel`, `shipment.view`, `shipment.create`, `shipment.ship`, `shipment.confirm_delivery`, `shipment.cancel`, `discrepancy.view`, `discrepancy.resolve`.

## 3. Entitas & data

### 3.1 `pick_tasks` — PCK header

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `number` | varchar(40) UK | `PCK/<gudang>/<yymm>/<urut>` |
| `warehouse_id` | FK warehouses | gudang yang mengambil |
| `source_type`, `source_id` | varchar(30), bigint | `material_request` \| `transfer` |
| `status` | enum | Katalog §2.2 |
| `assigned_to` | FK users | |
| `started_at`, `completed_at` | datetime | |
| `cancel_reason_id` | FK reason_codes | |

### 3.2 `pick_task_lines` — alokasi keras per bin

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `pick_task_id` | FK | |
| `source_line_id` | bigint | baris REQ/TRF yang dipenuhi |
| `item_id`, `bin_id`, `suggested_bin_id` | FK | bin asal dan saran sistem |
| `lot_id`, `serial_id`, `piece_id` | FK | sesuai mode pelacakan item |
| `qty_allocated`, `qty_picked` | decimal(18,4) | |
| `short_reason_id` | FK reason_codes | wajib bila `qty_picked < qty_allocated` ([BR-SJ-02](05-aturan-bisnis.md#br-sj)) |
| `override_reason` | varchar(255) | bila staf mengganti bin saran |
| `scanned_at` | datetime | |

### 3.3 `shipments` — SJ header

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `number` | varchar(40) UK | `SJ/<gudang>/<yymm>/<urut>` |
| `warehouse_id` | FK warehouses | gudang asal |
| `destination_type` | enum | `project_client` \| `site_warehouse` \| `warehouse` \| `vendor` |
| `destination_project_id`, `destination_warehouse_id` | FK | sesuai jenis tujuan |
| `shipment_method` | enum | `own_fleet` \| `carrier` \| `self_delivered` ([BR-SJ-07](05-aturan-bisnis.md#br-sj)) |
| `vehicle_id`, `driver_id` | FK | wajib bila `own_fleet` |
| `carrier_id`, `tracking_no` | FK, varchar(60) | wajib bila `carrier` |
| `carried_by_name` | varchar(100) | wajib bila `self_delivered` |
| `status` | enum | Katalog §2.3 |
| `loaded_at`, `shipped_at`, `delivered_at` | datetime | |
| `cancel_reason_id` | FK reason_codes | |

### 3.4 `shipment_lines`, bukti terima, dan DSC

| Tabel | Isi |
|---|---|
| `shipment_lines` | `pick_task_line_id`, `qty_shipped`, `qty_delivered`, `ownership_effect` (`sold`/`transfer`/`loan`, [BR-SJ-04](05-aturan-bisnis.md#br-sj)) |
| `proofs_of_delivery` | satu per SJ: penerima, tanda tangan, foto, GPS, `channel` (`driver_pwa`/`token_link`), `confirmation`, `confirm_deadline_at` |
| `proof_of_delivery_lines` | per baris SJ: `qty_good` + `qty_damaged` + `qty_missing` = `qty_shipped`; foto wajib bila rusak |
| `proof_of_delivery_units` | per unit untuk item berserial dan per potong: `condition` = `good`/`damaged`/`missing` |
| `delivery_tokens` | tautan bertoken sekali pakai + OTP, berlaku 24 jam ([A-41](04-keputusan-dan-asumsi.md#a-41)) |
| `delivery_discrepancies` | DSC header: `origin` = `partial_delivery`/`client_dispute` |
| `delivery_discrepancy_lines` | `discrepancy_type` (`missing`/`damaged`), jumlah, `disposition`, `client_decision`, `claim_ref` |

### 3.5 Enum

Semua dari [Katalog Status](06-katalog-status-dan-enum.md); **tidak ada nilai baru**.

## 4. Mesin status

Diambil apa adanya dari [Katalog Status §2.2–§2.4](06-katalog-status-dan-enum.md):

```
PCK:  pending → in_progress → completed
      pending|in_progress → cancelled
SJ:   prepared → shipped → delivered | partially_delivered
      prepared → cancelled          (setelah shipped tidak bisa dibatalkan)
DSC:  open → resolved
```

Transisi hanya lewat POST. SJ `shipped` tidak bisa dibatalkan; koreksinya lewat DSC atau RET.

## 5. Aturan bisnis yang berlaku

| Aturan | Ditegakkan di mana |
|---|---|
| [BR-SJ-01](05-aturan-bisnis.md#br-sj) | Picking hanya dari bin yang menyimpan alokasi; penggantian bin menuntut alasan |
| [BR-SJ-02](05-aturan-bisnis.md#br-sj) | Short pick: alasan wajib, bin ditandai hitung, sisa jadi backorder REQ; PCK berikutnya memetik sisa terhitung (`Shipment\Support\RequestLineOutstanding`, [A-204](04-keputusan-dan-asumsi.md#a-204)) |
| [BR-SJ-04](05-aturan-bisnis.md#br-sj) | Efek `delivered` berbeda per tujuan dan kepemilikan |
| [BR-SJ-05](05-aturan-bisnis.md#br-sj) | Satu bukti terima per SJ; foto wajib bila ada yang rusak |
| [BR-SJ-06](05-aturan-bisnis.md#br-sj) | Baik < dikirim → SJ `partially_delivered` dan DSC `open` |
| [BR-SJ-07](05-aturan-bisnis.md#br-sj) | Kelengkapan cara kirim sesuai `shipment_method` |
| [BR-SJ-09](05-aturan-bisnis.md#br-sj) | Satu SJ boleh memuat beberapa PCK dengan tujuan dan gudang asal sama |
| [BR-SJ-10](05-aturan-bisnis.md#br-sj) | Kurang dan rusak tetap di *Dalam Perjalanan* sampai DSC diselesaikan |
| [BR-OPN-02](05-aturan-bisnis.md#br-opn) | Bin beku menolak picking, kecuali PCK `pending` diberi **override** Kepala Gudang (`bin.manage`, alasan wajib, `OverrideFrozenBinPick`): PCK boleh mengambil dari bin beku, angka sesi opname bin itu digeser dan bin ditandai ⚑ ([A-240](04-keputusan-dan-asumsi.md#a-240)) |
| [BR-STK-01](05-aturan-bisnis.md#br-stk) | Seluruh pergerakan lewat `StockLedger`, tidak ada perkecualian |
| [BR-GEN-11](05-aturan-bisnis.md#br-gen) | Batal, short pick, dan penyelesaian selisih menuntut Alasan |

## 6. Layar

| Route | Komponen | Isi |
|---|---|---|
| `/picks` | `shipment.pick-list` | Daftar PCK dengan penyaring gudang, status, petugas |
| `/picks/{pck}` | `shipment.pick-detail` | Baris alokasi, pencatatan jumlah diambil, short pick dengan Alasan `*` |
| `/shipments` | `shipment.shipment-list` | Daftar SJ dengan penyaring status, tujuan, cara kirim |
| `/shipments/create` | `shipment.shipment-form` | Memilih PCK `completed` di Loading Area dengan tujuan sama; kelengkapan cara kirim |
| `/shipments/{sj}` | `shipment.shipment-detail` | Muat, kirim, bukti terima (foto serah terima, tanda tangan kanvas, foto rusak per baris), tautan penerima + OTP tampil sekali, dan DSC yang lahir darinya |
| `/terima/{token}` | `DeliveryTokenController` (tanpa login) | Penerima tanpa akun: OTP → formulir bukti terima per baris (foto wajib bila rusak, tanda tangan) → ringkasan; tautan kedaluwarsa/terpakai → 410 ([A-231](04-keputusan-dan-asumsi.md#a-231)) |
| `/discrepancies` | `shipment.discrepancy-list` | DSC terbuka dengan umur dan disposisi per baris |

## 7. Kejadian stok & integrasi

| Transisi | Pergerakan stok | Kejadian outbox |
|---|---|---|
| PCK `completed` | bin asal → Loading Area | — |
| PCK `cancelled` | Loading Area → bin asal (pembalik) | — |
| SJ `shipped` | Loading Area → bin *Dalam Perjalanan* | `goods_shipped` |
| SJ `delivered`, tujuan klien jual putus | keluar dari ledger | `goods_delivered` |
| SJ `delivered`, tujuan gudang/site | tetap *Dalam Perjalanan* sampai GRN tujuan | `stock_transferred` |
| SJ `delivered`, baris `loan` | → bin `on_site` proyek | `asset_checked_out` |
| DSC `resolved` | sesuai disposisi per baris | `delivery_discrepancy` |

## 8. Notifikasi

| Kejadian | Penerima | Kanal |
|---|---|---|
| PCK ditugaskan | Staf Gudang yang ditunjuk | in-app |
| Short pick tercatat | Kepala Gudang | in-app |
| SJ `shipped` | Pemohon, klien | in-app + portal |
| Bukti terima terisi | Pemohon, Kepala Gudang | in-app |
| DSC `open` | Kepala Gudang, pemohon | in-app |
| DSC terbuka lebih dari ambang | Kepala Gudang | in-app, diulang harian |

## 9. Laporan & dashboard

| Laporan | Filter | Kolom |
|---|---|---|
| Daftar pengiriman | status, tujuan, cara kirim, rentang tanggal | nomor, tujuan, cara kirim, status, tanggal kirim |
| Short pick | gudang, rentang tanggal | PCK, item, dialokasikan, diambil, selisih, alasan |
| Posisi barang rusak & selisih | gudang, umur | SJ, item, jenis, jumlah, disposisi, umur DSC |
| Kinerja pengiriman | gudang, rentang tanggal | dikirim, diterima utuh, bersisa, rata-rata hari |

## 10. Kasus uji (Given / When / Then)

| ID | Given | When | Then | BR |
|---|---|---|---|---|
| TC-PCK-01 | REQ `approved` bersumber stok | buat PCK | PCK `pending` dengan alokasi per bin | BR-SJ-01 |
| TC-PCK-02 | PCK `pending` | mulai | status `in_progress` | — |
| TC-PCK-03 | Bin dibeku opname | mulai picking dari bin itu | ditolak; dengan override Kepala Gudang boleh (TC-OPN-21) | BR-OPN-02, A-240 |
| TC-PCK-04 | PCK `in_progress`, fisik sesuai | selesaikan | stok pindah ke Loading Area, PCK `completed` | BR-STK-01 |
| TC-PCK-05 | Fisik kurang dari alokasi | selesaikan tanpa alasan | ditolak | BR-SJ-02 |
| TC-PCK-06 | Fisik kurang, alasan diisi | selesaikan | sisa jadi backorder REQ, bin ditandai hitung | BR-SJ-02 |
| TC-PCK-07 | PCK `completed` | batalkan | ditolak | — |
| TC-PCK-08 | PCK `in_progress` | batalkan dengan alasan | alokasi dilepas, tidak ada stok pindah | — |
| TC-PCK-17 | REQ 20, PCK pertama diambil 15 (kurang, beralasan) | buat PCK lagi (2×) | PCK baru 5; yang ketiga ditolak selama PCK kedua berjalan | [A-204](04-keputusan-dan-asumsi.md#a-204), BR-SJ-02 |
| TC-PCK-18 | item ber-lot, PCK berjalan | pindai kode item; pindai nomor lot (huruf kecil) | ditolak; baris tercatat | [A-203](04-keputusan-dan-asumsi.md#a-203) |
| TC-PCK-16 | PCK `in_progress`, item ber-barcode | pindai kode asing; pindai kode bin; pindai barcode item; selesaikan | galat tanpa pencatatan; bin aktif; baris tercatat sejumlah alokasi & disorot; PCK `completed` | [A-203](04-keputusan-dan-asumsi.md#a-203) |
| TC-SJ-01 | Dua PCK `completed` tujuan sama | buat SJ | satu SJ memuat keduanya | BR-SJ-09 |
| TC-SJ-02 | PCK tujuan berbeda | buat satu SJ | ditolak | BR-SJ-09 |
| TC-SJ-03 | `own_fleet` tanpa kendaraan | buat SJ | ditolak | BR-SJ-07 |
| TC-SJ-04 | `carrier` tanpa nomor resi | buat SJ | ditolak | BR-SJ-07 |
| TC-SJ-05 | SJ `prepared` | kirim | stok ke bin Dalam Perjalanan, kejadian `goods_shipped` | BR-SJ-04 |
| TC-SJ-06 | SJ `shipped` | batalkan | ditolak | — |
| TC-SJ-07 | Semua baris baik, tujuan klien | isi bukti terima | SJ `delivered`, stok keluar ledger, `goods_delivered` | BR-SJ-04 |
| TC-SJ-08 | Tujuan Gudang Site | isi bukti terima | stok tetap Dalam Perjalanan, `stock_transferred` | BR-SJ-04 |
| TC-SJ-09 | Baik + rusak ≠ dikirim | isi bukti terima | ditolak | BR-SJ-05 |
| TC-SJ-10 | Ada yang rusak tanpa foto | isi bukti terima | ditolak | BR-SJ-05 |
| TC-SJ-11 | Baik < dikirim | isi bukti terima | SJ `partially_delivered`, DSC `open` | BR-SJ-06 |
| TC-SJ-12 | Baris rusak | setelah bukti terima | kondisi stok menjadi `damaged` di Dalam Perjalanan | BR-SJ-10 |
| TC-DSC-01 | DSC `open`, disposisi `adjusted` | selesaikan | stok keluar ledger dengan alasan | BR-SJ-10 |
| TC-DSC-02 | DSC `open`, disposisi `returned_to_warehouse` | selesaikan | stok kembali ke bin Retur gudang asal | BR-SJ-10 |
| TC-DSC-03 | DSC `open`, disposisi `reship` | selesaikan | jumlah kembali ke backorder baris REQ | BR-SJ-10 |
| TC-DSC-04 | DSC `open`, `client_decision = not_needed` | selesaikan | sisa baris REQ ditutup | BR-SJ-10 |
| TC-DSC-05 | Disposisi tanpa alasan bila menuntut | selesaikan | ditolak | BR-GEN-11 |
| TC-SJ-13 | User tanpa `shipment.view` | buka daftar SJ | 403 | BR-GEN-09 |
| TC-SJ-14 | Klien proyek lain | buka SJ | 404 | BR-ACC-05 |
| TC-SJ-18 | SJ aset berserial (1 unit) terkirim | bukti terima dibagi 0,5 baik + 0,5 rusak; lalu seluruhnya baik | pembagian ditolak BR-SJ-05; layar driver menawarkan satu pilihan kondisi berserial; baris `proof_of_delivery_units` (serial, `good`) | BR-SJ-05, A-64, [A-244](04-keputusan-dan-asumsi.md#a-244) |

## 11. Di luar lingkup modul ini

Penerimaan di gudang tujuan (modul `receipt`); retur (modul `return`); transfer antar gudang sebagai dokumen (modul `transfer`); klaim ke ekspedisi di luar pencatatan `claim_ref`; pengiriman OTP lewat WhatsApp atau SMS ([O-06](04-keputusan-dan-asumsi.md#o-06)); mode offline penuh `[F2]`; optimasi rute.

## 12. Definisi selesai

- [x] Migrasi tenant, model, dan enum untuk seluruh tabel §3
- [x] Aksi domain per permission dengan validasi §5
- [x] Seluruh pergerakan stok lewat `StockLedger`, tanpa perkecualian
- [x] Efek `delivered` per tujuan dan kepemilikan (BR-SJ-04)
- [x] DSC lahir otomatis dan diselesaikan per disposisi (BR-SJ-10)
- [x] Enam layar §6 dan menu "Pengiriman"
- [x] Semua TC-PCK, TC-SJ, dan TC-DSC lulus; `php83 artisan test` hijau
- [x] Dokumen ini diperbarui bila implementasi menyimpang (versi naik + changelog README)

## 13. Catatan implementasi (24 September 2026)

Picking tinggal di domain `app/Domain/Shipment`, bukan folder `Picking/` seperti [Arsitektur §4](08-arsitektur.md#4-struktur-kode). Tujuh aksi domain: `CreatePickTask` (`pick.create`), `ProcessPickTask` (`pick.start`, `pick.complete`, `pick.cancel`), `CreateShipment` (`shipment.create`), `ShipShipment` (`shipment.ship`, `shipment.cancel`), `ConfirmDelivery` (`shipment.confirm_delivery`), `IssueDeliveryToken`, `ResolveDiscrepancy` (`discrepancy.resolve`). Dua kelas memegang lebih dari satu permission; tiap metode memeriksa permission-nya sendiri ([A-73](04-keputusan-dan-asumsi.md#a-73)). Short pick menandai bin lewat `ChangeBinStatus::flagForCount()` ([A-67](04-keputusan-dan-asumsi.md#a-67)). Pengaturan company yang dipakai: `receipt_confirm_days` dan `discrepancy_alert_days` ([11-master §13.4](11-master.md#135-sisa-pekerjaan-modul-ini)).

### 13.1 Penyimpangan dari spesifikasi

1. **Buku besar stok diperluas untuk perubahan kondisi di bin yang sama.** BR-SJ-10 menuntut barang rusak
   berkondisi `damaged` sejak bukti terima **tanpa berpindah bin**, sedangkan BR-LED-01 melarang bin asal
   sama dengan bin tujuan. `MovementRequest` diberi `fromStockStatus`; bin yang sama kini sah selama
   kondisinya berubah. Bentuk yang sama akan dipakai QC di modul `receipt`.
2. **`StockLedger::emitEvent()` ditambahkan** untuk kejadian tanpa pergerakan. BR-SJ-04 menuntut
   `stock_transferred` terbit saat barang diterima gudang tujuan, padahal barangnya belum berpindah —
   ia masih milik gudang asal sampai GRN tujuan dibuat.
3. **Tanda tangan, foto, dan foto kerusakan disimpan sebagai path berkas**, bukan menunjuk tabel
   `attachments` seperti ERD, karena tabel itu belum ada. Penyimpanannya mengikuti [A-68](04-keputusan-dan-asumsi.md#a-68).
4. **`shipments` diberi `destination_vendor_id`** yang tidak ada di ERD: `destination_type` sudah
   memuat `vendor`, tetapi tidak ada kolom yang menampung vendornya.
5. **DSC diberi `number`.** ERD tidak menyebutnya, tetapi DSC adalah dokumen yang dirujuk saat
   berdebat dengan ekspedisi dan klien; dokumen tanpa nomor tidak bisa disebut.
6. **Pengiriman OTP lewat WhatsApp atau SMS belum ada** (penyedia OTP: [O-15](04-keputusan-dan-asumsi.md#o-15)).
   Tautan dan OTP-nya berfungsi; keduanya ditampilkan sekali di layar penerbit dan disampaikan
   driver kepada penerima ([A-231](04-keputusan-dan-asumsi.md#a-231)).
7. **Alokasi picking hanya dari bin `storage`** (v0.4, bersama modul [Receipt/Putaway](19-receipt-putaway.md)).
   Sebelumnya `CreatePickTask` mengambil saldo Tersedia dari bin apa pun di gudang, termasuk bin Dalam
   Perjalanan milik gudang asal, Penerimaan, dan Loading Area — barang transfer yang belum diterima gudang
   tujuan bisa ikut dipetik lagi. Kini hanya bin penyimpanan ([A-84](04-keputusan-dan-asumsi.md#a-84)).

8. **PCK dan SJ untuk TRF dan RET** (v0.5, [22-retur-transfer](22-retur-transfer.md)). `CreatePickTask` kini punya
   `forTransfer()` (PCK di gudang asal, dibuat otomatis saat TRF disetujui, [A-107](04-keputusan-dan-asumsi.md#a-107)) dan
   `forGoodsReturn()` (SJ balik dari Gudang Site, alokasi persis bin/turunan baris RET, [A-111](04-keputusan-dan-asumsi.md#a-111));
   `handle()` juga memetik baris REQ bersumber transfer sebesar reservasi lunaknya ([A-108](04-keputusan-dan-asumsi.md#a-108)).
   `CreateShipment` menolak SJ yang memuat PCK TRF/RET bila tujuannya bukan gudang tujuan dokumen itu, dan PCK retur tidak
   digabung dengan PCK lain; SJ balik menandai RET `in_progress`. `ShipShipment` mencatat `qty_shipped` baris TRF.
   `ConfirmDelivery` tidak menerbitkan `stock_transferred` untuk SJ balik ([A-112](04-keputusan-dan-asumsi.md#a-112)).
9. **Perbaikan kecil** (v0.5): `PickTaskLine::unshippedQty()` tidak lagi menghitung SJ yang dibatalkan (Katalog §2.3: barang
   tetap di Loading Area dan boleh dimuat SJ lain); relasi `PickTaskLine::pickTask` dan `ShipmentLine::shipment` dibaca lintas
   cakupan sehingga penerima di gudang tujuan tetap menemukan asal barisnya; daftar REQ yang menunggu picking kini menyaring
   `pick_tasks.source_type` (`source_line_id` dipakai REQ, TRF, dan RET); form SJ menerima `?pick_task=` dan gudang tujuan
   tidak lagi dibatasi cakupan pengirim.
10. **DSC `returned_to_warehouse` tidak lewat GRN retur** ([A-114](04-keputusan-dan-asumsi.md#a-114)): barang rusak yang
   dibawa balik tetap dipindah langsung ke bin Retur gudang asal berkondisi Rusak (setara pemilahan `damaged`);
   `return_receipt_id` tetap titik sambung. Barang rusak yang **ditinggal** ekspedisi (DSC `claimed`) kembali lewat RET.

### 13.2 Keputusan implementasi

1. **Alokasi keras menggantikan reservasi lunak, tidak menumpuk di atasnya.** Saat PCK dibuat, reservasi
   lunak REQ dilepas lebih dulu. Tanpa itu barang yang sama terhitung dua kali dan stok tersedia menjadi
   kurang dari kenyataan.
2. **Satu PCK untuk satu gudang sumber.** Baris REQ yang gudangnya berbeda melahirkan PCK sendiri-sendiri,
   karena yang mengambil adalah orang yang berdiri di gudang itu.
3. **Bin saran dan bin yang dipakai keduanya disimpan.** Penggantian bin menuntut alasan (BR-SJ-01) dan
   tetap terbaca di layar, bukan hilang begitu staf menimpanya.
4. **Bukti terima menuntut seluruh baris terisi dan jumlahnya genap.** Selisih yang tidak dijelaskan
   berarti ada barang yang hilang dari pembukuan tanpa seorang pun bertanggung jawab.
5. **Disposisi dan keputusan klien dipisah** (BR-SJ-10). Keduanya tidak selalu searah: barang rusak bisa
   dibawa balik ke gudang sementara klien tetap menunggu penggantinya.
6. **`reship` tidak menyentuh stok.** Yang dikirim ulang adalah barang baru dari gudang; yang di
   perjalanan menunggu keputusan berikutnya.
7. **Akun klien tidak pernah mencapai route internal**: middleware area memulangkannya ke portal sebelum
   policy dipanggil. Policy tetap menjaga komponen dan aksinya, sebagai lapis kedua.
8. **Token bukti terima sekali pakai.** Menerbitkan tautan baru mematikan yang lama, supaya tidak ada dua
   orang yang merasa berhak menandatangani.

### 13.3 Layar yang sudah ada

| Layar | Route | Komponen Livewire | Isi |
|---|---|---|---|
| Tugas picking | `/picks` | `shipment.pick-list` | Daftar PCK, plus REQ disetujui yang belum punya tugas |
| Detail picking | `/picks/{id}` | `shipment.pick-detail` | Pencatatan per baris, penggantian bin dengan alasan, short pick, mulai/selesai/batal |
| Surat jalan | `/shipments` | `shipment.shipment-list` | Penyaring status, gudang, cara kirim; penanda SJ yang masih berselisih |
| Susun surat jalan | `/shipments/create` | `shipment.shipment-form` | Pilih PCK selesai di gudang itu, tujuan, dan kelengkapan cara kirim |
| Detail surat jalan | `/shipments/{id}` | `shipment.shipment-detail` | Berangkatkan, batalkan, bukti terima per baris, tautan bertoken, riwayat |
| Selisih pengiriman | `/discrepancies` | `shipment.discrepancy-list` | DSC terbuka, umur, dan penyelesaian per baris |
| Halaman penerima bertoken | `/terima/{token}` | `Shipment\DeliveryTokenController` + `Support\ProofFiles` | Tanpa `auth`, `throttle`; OTP diverifikasi `IssueDeliveryToken::verify` (sesi 30 menit per token), bukti terima lewat `ConfirmDelivery` kanal `token_link`, token dihabiskan; berkas `pod/sj-<id>/…` dilayani `shipments.proof.file` (berotorisasi). Kemampuan policy baru `issueToken` (SJ `shipped`) — sebelumnya tombol *Terbitkan tautan* memakai `ship` yang hanya berlaku saat `prepared` |

Uji yang menopangnya ada di `tests/Feature/Shipment`: `PickTaskTest` (TC-PCK-01–08),
`ShipmentTest` (TC-SJ-01–12), `DiscrepancyTest` (TC-DSC-01–05), `ShipmentScreenTest`
(TC-SJ-13–17, TC-PCK-09, TC-DSC-06), dan `DeliveryTokenPageTest` (TC-SJ-05d, 05d2, 05e).

### 13.4 Sisa pekerjaan modul ini

1. ~~Halaman penerima bertoken~~ — **selesai 25 Sep 2026** ([A-231](04-keputusan-dan-asumsi.md#a-231)); pengiriman OTP otomatis menunggu [O-15](04-keputusan-dan-asumsi.md#o-15).
2. ~~Bukti terima per unit~~ untuk item berserial dan per potong — selesai 25 Sep 2026: satu baris SJ = satu unit, dinilai utuh baik/rusak/kurang (pilihan kondisi di layar driver & halaman penerima), dicatat di `proof_of_delivery_units` ([A-244](04-keputusan-dan-asumsi.md#a-244), TC-SJ-18); pemindaian PWA tetap `[F2]`.
3. ~~Konfirmasi dan keberatan pemohon~~ — **selesai** ([BR-REQ-10](05-aturan-bisnis.md#br-req), [A-188](04-keputusan-dan-asumsi.md#a-188)): kartu bukti terima di REQ back-office & portal klien, keberatan berfoto membuka DSC, konfirmasi otomatis lewat batas ([27-pendukung-f1](27-pendukung-f1.md)).
4. **Cross-dock dari GRN** ([BR-SJ-03](05-aturan-bisnis.md#br-sj), [A-83](04-keputusan-dan-asumsi.md#a-83)) — menunggu keputusan A-83. GRN retur
   untuk barang rusak yang dibawa balik tidak dibuat ([A-114](04-keputusan-dan-asumsi.md#a-114)); modul Retur sudah ada ([22](22-retur-transfer.md)).
5. *(catatan implementasi, bukan sisa)* **Aset dipinjamkan** (v0.6): `ConfirmDelivery` memanggil `Asset\Support\AssetCustody::checkOut` untuk baris `loan` berserial sebelum memindahkannya ke bin On-site; state aset (`reserved` → `in_transit` → `on_loan`) diperbarui observer kartu stok modul Aset ([25-aset §13](25-aset.md)).
6. ~~Unggah tanda tangan dan foto lewat layar~~ — **selesai 25 Sep 2026**: layar driver dan halaman penerima mengunggah foto serah terima, tanda tangan kanvas (data URL → PNG), foto kerusakan per baris (`StoreUpload::handleDataUrl`, NFR-14).
7. **Mode offline driver** ([BR-SJ-08](05-aturan-bisnis.md#br-sj)) `[F2]`.
8. **Notifikasi §8** belum lengkap; ~~laporan §9~~ — **selesai 25 Sep 2026**: *Daftar pengiriman*, *Short pick*, *Posisi barang rusak & selisih*, *Kinerja pengiriman* di [16-shared-laporan-berkas §3.2](16-shared-laporan-berkas.md#32-definisi-laporan-terdaftar-reportsdefinitions) ([A-232](04-keputusan-dan-asumsi.md#a-232)).
