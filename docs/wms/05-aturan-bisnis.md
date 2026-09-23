# Aturan Bisnis, Relasi Dokumen & Matriks Kejadian

**Versi:** 0.3
**Tanggal:** 23 September 2026
**Status:** baru (hasil audit dokumentasi v0.3); aturan yang bersumber dari A-29–A-49 menunggu validasi
**Dokumen terkait:** [Blueprint](01-blueprint.md) · [Katalog Status & Enum](06-katalog-status-dan-enum.md) · [Keputusan & Asumsi](04-keputusan-dan-asumsi.md) · [Akuntansi](../akuntansi/01-lingkup-dan-integrasi-wms.md) · [Purchasing](../purchasing/01-lingkup-dan-integrasi-wms.md)

Dokumen ini mengumpulkan aturan bisnis yang sebelumnya tersebar di prosa Blueprint, memberinya **ID stabil** agar bisa dirujuk spesifikasi modul (Part 4), kasus uji, dan prompt Claude Code (Part 6).

Konvensi:
- ID `BR-<AREA>-nn`. Area: `GEN` umum · `STK` stok · `REQ` permintaan · `SJ` picking & pengiriman · `GRN` penerimaan · `RET` transfer & retur · `CNV` konversi · `AST` aset · `OPN` opname · `APR` approval · `PRJ` proyek & klien · `SUB` platform & langganan.
- Kolom **Fase** memakai `F1` `F2` `F3` ([Blueprint §18](01-blueprint.md#18-peta-modul--fase-rilis)).
- Kolom **Sumber** menunjuk prinsip (P-xx), keputusan (D-xx), asumsi (A-xx), atau bagian Blueprint.
- Aturan tidak dihapus; yang tidak berlaku lagi diberi tanda *(digantikan BR-…)*.

---

## 1. Relasi antar dokumen

Prinsip: **dokumen niat** (permintaan, transfer, retur) dipisahkan dari **dokumen pergerakan fisik** (picking, pengiriman, penerimaan, put-away). Pergerakan fisik antar lokasi selalu memakai `SJ` (keluar) dan `GRN` (masuk), apa pun dokumen niatnya ([A-33](04-keputusan-dan-asumsi.md#a-33)).

```
REQ ──► PCK ──► SJ ──► Bukti Terima ──► (DSC bila selisih)
 │                └──► GRN (bila tujuan Gudang Site / gudang lain) ──► PUT
 └──► backorder ──► TRF (dari gudang lain)  atau  PRQ ──► [PO di Purchasing] ──► GRN ──► PUT / cross-dock ke Loading Area

TRF ──► PCK ──► SJ ──► GRN (gudang tujuan) ──► PUT
RET ──► SJ (dari site; opsional bila diantar sendiri) ──► GRN (jenis retur) ──► Pemilahan ──► PUT | bin Waste | offcut (ID baru)
GRN (vendor) ──► QC ──► PUT | Karantina ──► RTV
ISU : Gudang Site ──► dipakai proyek (tanpa SJ)
CNV : input ──► output + offcut + waste + kerf (di gudang yang sama)
AST : menempel pada SJ (keluar) dan GRN retur (kembali); pemeriksaan setelah kembali
OPN ──► ADJ (satu ADJ per gudang per sesi)
WST : menutup isi bin Waste
```

| Induk | Anak | Kardinalitas | Catatan |
|---|---|---|---|
| REQ | PCK | 1 : n | Satu PCK per gudang sumber per gelombang picking ([A-31](04-keputusan-dan-asumsi.md#a-31)) |
| PCK | SJ | n : 1 | Beberapa PCK boleh digabung ke satu SJ bila tujuan & gudang asal sama |
| SJ | DSC | 1 : 0..1 | Dibuat otomatis saat `partially_delivered` |
| SJ | GRN | 1 : 1 | Hanya bila tujuan adalah gudang company (Gudang Site/gudang lain) |
| GRN | PUT | 1 : n | Per baris `passed`; cross-dock tidak membuat PUT |
| GRN | RTV | 1 : n | Per baris `rejected` |
| TRF | PCK / SJ / GRN | 1 : n | Mengikuti jalur REQ |
| RET | SJ / GRN | 1 : 1 | SJ opsional |
| REQ | TRF, PRQ | 1 : n | Dibuat dari backorder saat approval |
| PRQ | GRN | 1 : n | Baris GRN merujuk baris PRQ ([A-47](04-keputusan-dan-asumsi.md#a-47)) |
| OPN | ADJ | 1 : n | Satu ADJ per gudang dalam cakupan |
| Proyek | REQ, SJ, ISU, CNV, RET, AST, WST | 1 : n | Semua wajib proyek kecuali GRN vendor, PUT, OPN, ADJ, RTV, PRQ |

Setiap dokumen menyimpan `source_type` + `source_id` (polimorfik) ke induknya, dan setiap baris dokumen anak menyimpan rujukan ke baris induk agar pemenuhan bisa dihitung per baris.

---

<a id="br-gen"></a>
## 2. Aturan umum — `BR-GEN`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-GEN-01 | Semua perubahan status hanya lewat aksi bernama di [Katalog Status](06-katalog-status-dan-enum.md); status di luar katalog dilarang. | F1 | P-04 |
| BR-GEN-02 | Menolak dan membatalkan wajib alasan dari master **Alasan** (boleh ditambah teks bebas). | F1 | P-04, Blueprint 6.3a |
| BR-GEN-03 | Dokumen yang sudah menggerakkan stok tidak bisa dibatalkan; koreksi lewat **dokumen pembalik** yang merujuk dokumen asal (`reversal_of_id`) dan menerbitkan kejadian dengan `reverses_event_id`. | F1 | P-03 |
| BR-GEN-04 | Batasan pembatalan/pembalikan: GRN `received` → tidak bisa dibatalkan (pakai ADJ/RTV) · SJ `shipped` → tidak bisa (pakai DSC/RET) · CNV `completed` → hanya bila tidak ada output/offcut yang sudah dipakai · ISU `confirmed` → ISU pembalik dengan approval · master (item, bin, gudang) → tidak bisa dinonaktifkan bila saldo atau reservasi ≠ 0. | F1 | P-03, F-16 audit |
| BR-GEN-05 | Dua catatan resmi: **`document_timeline`** (per dokumen: status, pelaku, waktu, kanal, catatan; dibaca user) dan **`audit_log`** (teknis: tabel, kolom, nilai lama → baru, IP; hanya Admin). Keduanya *append-only*. | F1 | P-07, NFR-03 |
| BR-GEN-06 | Nomor dokumen: format per company ([A-17](04-keputusan-dan-asumsi.md#a-17)); segmen `{GUDANG}` = gudang asal untuk SJ/TRF/PCK/GRN/PUT/ADJ/RTV, gudang pemenuh untuk REQ/CNV/WST/ISU, `ALL` untuk OPN multi-gudang dan PRQ. Urutan dikunci di database (`SELECT … FOR UPDATE` pada `document_sequence`). Dokumen yang dibuat offline memakai nomor sementara `TMP-<uuid>` dan diberi nomor final saat sinkron. | F1 | [A-43](04-keputusan-dan-asumsi.md#a-43) |
| BR-GEN-07 | Waktu disimpan **UTC**; tampilan, reset urutan bulanan, batas hari, dan `usage_days` memakai zona waktu company. Kejadian stok membawa `occurred_at` (UTC) dan `timezone`. | F1 | NFR-07 |
| BR-GEN-08 | Semua angka kuantitas `DECIMAL(18,4)` dalam satuan dasar item; pembulatan mengikuti aturan per satuan. | F1 | [A-37](04-keputusan-dan-asumsi.md#a-37) |
| BR-GEN-09 | Cakupan akses melekat pada **penugasan role × scope** (user bisa Kepala Gudang di A dan Staf di B). Role Klien tidak bisa digabung dengan role internal. | F1 | [A-46](04-keputusan-dan-asumsi.md#a-46) |
| BR-GEN-10 | Kebutuhan bertag `[F2]`/`[F3]` di Fase 1 dibangun sebagai *stub*: tabel & titik sambung ada, UI tidak ditampilkan. | F1 | audit |

<a id="br-stk"></a>
## 3. Stok, ledger, reservasi — `BR-STK`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-STK-01 | Stok hanya berubah lewat baris `stock_movement` (append-only). Saldo `stock_balance` adalah hasil agregasi dan bisa dibangun ulang dari ledger. | F1 | P-01 |
| BR-STK-02 | **Lokasi** stok = bin (termasuk bin virtual `in_transit`, `on_site`). **Kondisi** stok = `stock_status` ∈ {available, quarantine, damaged}. Tidak ada status "Dalam Perjalanan"/"On-site"/"Dicadangkan" pada saldo. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-STK-03 | **Reservasi** dicatat di tabel `stock_reservation` (item, gudang, jumlah, dokumen), bukan di ledger. Stok tersedia = saldo `available` − reservasi aktif. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-STK-04 | Reservasi **lunak** (per item per gudang) dibuat saat REQ/TRF `approved`. Alokasi **keras** (bin, lot, serial, potongan) dibuat saat PCK dibuat, mengikuti strategi pengambilan; user boleh mengganti alokasi dengan alasan. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-STK-05 | Reservasi dilepas otomatis saat: dokumen `rejected`/`cancelled`/`closed_short`, baris terpenuhi (dikonversi jadi pergerakan), proyek `closed`/`cancelled`, atau PCK dibatalkan (alokasi keras saja). | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-STK-06 | Saldo per bin tidak boleh negatif; transaksi yang menyebabkan negatif ditolak dengan pesan jelas. Penguncian baris saldo dilakukan dalam satu transaksi database (NFR-13). | F1 | [A-37](04-keputusan-dan-asumsi.md#a-37) |
| BR-STK-07 | Kapasitas bin: default **peringatan**; company boleh mengubah menjadi **blokir** per kategori penyimpanan. | F1 | [A-37](04-keputusan-dan-asumsi.md#a-37) |
| BR-STK-08 | Aset (`ownership_model` ∈ {asset, both}) **wajib** `tracking_mode = serial`. Reservasi aset menunjuk serial tertentu sejak alokasi keras. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-STK-09 | Item `piece`: satuan dasar = panjang (mis. m). Saldo ditampilkan sebagai *jumlah potongan* **dan** *total panjang*. Konversi kemasan ("1 batang = 6 m") hanya untuk **input** transaksi dan hanya berlaku pada potongan berukuran nominal. | F1 | [A-36](04-keputusan-dan-asumsi.md#a-36) |
| BR-STK-10 | Permintaan item `piece` diinput sebagai *n potongan ukuran nominal* atau *total panjang*; alokasi potongan konkret dilakukan staf saat picking dengan strategi `offcut_first` lalu FIFO sebagai pemutus seri. | F1 | [A-36](04-keputusan-dan-asumsi.md#a-36) |
| BR-STK-11 | Kombinasi yang sah: lihat [§13](#15-matriks-kombinasi-pelacakan). Kombinasi di luar matriks ditolak saat menyimpan item. | F1 | [A-36](04-keputusan-dan-asumsi.md#a-36) |
| BR-STK-12 | Kedaluwarsa hanya untuk `tracking_mode` ∈ {lot, serial}; FEFO mensyaratkan kedaluwarsa aktif. | F1 | D-13 |
| BR-STK-13 | Bin `in_transit` adalah milik **gudang asal**; laporan saldo per gudang memasukkan stok dalam perjalanan ke gudang asal sampai GRN tujuan `received`. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-STK-14 | Bin `on_site` selalu terikat satu proyek dan hanya menampung aset (`loan`). Barang habis pakai tidak pernah berada di `on_site`. | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |

<a id="br-req"></a>
## 4. Permintaan material — `BR-REQ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-REQ-01 | Satu REQ untuk satu proyek; setiap baris punya `required_date` (default = tanggal dibutuhkan header). | F1 | [A-22](04-keputusan-dan-asumsi.md#a-22), [A-39](04-keputusan-dan-asumsi.md#a-39) |
| BR-REQ-02 | Permintaan dari Klien masuk `under_review`. Staf boleh mengubah jumlah/baris; setiap perubahan tercatat di timeline dengan nilai lama → baru dan klien diberi tahu. Perubahan tidak butuh persetujuan balik klien (klien bisa membatalkan). | F1 | [A-07](04-keputusan-dan-asumsi.md#a-07), [A-39](04-keputusan-dan-asumsi.md#a-39) |
| BR-REQ-03 | Baris non-katalog wajib **dipetakan** oleh staf ke item yang ada atau item baru berstatus `provisional` sebelum meninggalkan `under_review`. Item `provisional` harus dilengkapi Admin sebelum GRN pertama. | F1 | [A-39](04-keputusan-dan-asumsi.md#a-39) |
| BR-REQ-04 | **Gudang sumber** per baris ditetapkan sebelum `pending_approval` (oleh staf untuk REQ klien, oleh pemohon/sistem untuk REQ internal); sistem menyarankan gudang terdekat/induk dengan stok tersedia. | F1 | [A-31](04-keputusan-dan-asumsi.md#a-31) |
| BR-REQ-05 | Saat `approved`, tiap baris harus punya sumber: (a) stok tersedia → reservasi lunak; (b) transfer dari gudang lain → TRF dibuat; (c) pembelian → PRQ dibuat. Baris tanpa sumber menahan approval. Ini menggantikan P-02 v0.2. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-REQ-06 | Untuk item `both`, baris menyimpan `line_ownership` = `buy` atau `loan` (default dari item). Baris `loan` hanya untuk item berserial. | F1 | [A-38](04-keputusan-dan-asumsi.md#a-38) |
| BR-REQ-07 | Pemohon tidak boleh menjadi approver dokumennya sendiri (SoD). | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-REQ-08 | Barang backorder yang tiba (GRN merujuk PRQ/TRF) otomatis direservasi ke REQ penunggu dengan urutan `required_date`, lalu disarankan **cross-dock**. | F1 | [A-47](04-keputusan-dan-asumsi.md#a-47) |
| BR-REQ-09 | REQ `partially_fulfilled` boleh ditutup dengan sisa (`closed_short`) oleh Kepala Gudang atau pemohon; sisa reservasi & PRQ yang belum diteruskan dibatalkan. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-REQ-10 | Pemohon (internal atau klien) mengonfirmasi terima di sisi penerima bila bukti terima diisi driver; konfirmasi ini menutup baris. Tanpa konfirmasi dalam 3 hari, baris dianggap dikonfirmasi (dapat diubah per company). | F1 | Blueprint 4.2 |

<a id="br-sj"></a>
## 5. Picking, pengiriman, bukti terima — `BR-SJ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-SJ-01 | Picking hanya dari bin yang memang menyimpan alokasi; scan bin + item/lot/serial/potongan wajib di PWA, boleh manual di web dengan alasan. | F1 | P-06 |
| BR-SJ-02 | **Short pick**: bila fisik kurang dari alokasi, staf mencatat jumlah nyata + alasan; sistem menandai bin untuk hitung (`count_flag`), mengurangi SJ, dan mengembalikan sisa ke backorder REQ. | F1 | [A-35](04-keputusan-dan-asumsi.md#a-35) |
| BR-SJ-03 | Cross-dock: baris GRN yang ditunggu REQ langsung ke Loading Area tanpa PCK; REQ tetap `in_progress`. | F1 | Blueprint 7 |
| BR-SJ-04 | Efek `delivered` per tujuan & kepemilikan: ke **Gudang Site/gudang lain** → tetap `in_transit` sampai GRN tujuan `received` (kejadian `stock_transferred`); **jual putus ke klien** → keluar ledger, riwayat *Terkirim ke Klien* (kejadian `goods_delivered`); **aset** → pindah ke bin `on_site` proyek (kejadian `asset_checked_out`). | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-SJ-05 | Bukti terima = foto + tanda tangan + jumlah per baris; diisi Driver (user company) atau penerima tanpa akun lewat **tautan bertoken sekali pakai + OTP WA/SMS**, berlaku 24 jam. | F1 | [A-20](04-keputusan-dan-asumsi.md#a-20), [A-41](04-keputusan-dan-asumsi.md#a-41) |
| BR-SJ-06 | Jumlah diterima < dikirim → SJ `partially_delivered` dan DSC `open`; selisih tetap di `in_transit` sampai DSC `resolved`. | F1 | [A-35](04-keputusan-dan-asumsi.md#a-35) |
| BR-SJ-07 | SJ wajib mencatat kendaraan & driver (internal) atau nama ekspedisi & nomor resi (pihak ketiga). | F1 | Blueprint 6.3a |
| BR-SJ-08 | Driver Fase 1: draf bukti terima disimpan di perangkat (localStorage) bila offline dan dikirim saat online; ini bukan sinkron penuh (Fase 2). | F1 | [A-49](04-keputusan-dan-asumsi.md#a-49) |

<a id="br-grn"></a>
## 6. Penerimaan, QC, put-away, retur ke vendor — `BR-GRN`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-GRN-01 | Ledger diposting saat GRN `received` ke bin `receiving`, atau ke `quarantine` bila item/company mewajibkan QC. Kejadian stok terbit pada saat yang sama. | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-02 | QC adalah langkah per baris dengan hasil `passed`/`quarantined`/`rejected`; `rejected` menunggu RTV, `quarantined` menunggu keputusan ulang. | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-03 | Put-away menyarankan bin berdasarkan kategori penyimpanan item, kapasitas, dan kedekatan zona; staf boleh mengganti. | F1 | Blueprint 6.3 |
| BR-GRN-04 | Retur ke vendor (RTV) hanya dari bin `quarantine`; barang pengganti masuk lewat GRN baru yang merujuk RTV. | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-05 | GRN dari SJ/RET tidak boleh menerima lebih dari yang dikirim; kelebihan dicatat sebagai baris tanpa rujukan dan memicu ADJ. | F1 | audit |

<a id="br-ret"></a>
## 7. Transfer & retur — `BR-RET`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-RET-01 | TRF dan RET adalah dokumen niat + approval; pergerakan fisik lewat SJ + GRN (BR-GEN, [§1](#1-relasi-antar-dokumen)). | F1 | [A-33](04-keputusan-dan-asumsi.md#a-33) |
| BR-RET-02 | Transfer antar proyek = transfer antar Gudang Site (atau `on_site` untuk aset) dengan proyek asal/tujuan; menggantikan "Next Project" prototipe. | F1 | Blueprint 7 |
| BR-RET-03 | Retur merujuk SJ asal bila ada; baris jual-putus yang diretur menghasilkan `goods_returned` dengan `ownership = sold` (retur penjualan), baris dari Gudang Site dengan `ownership = company`. | F1 | [A-26](04-keputusan-dan-asumsi.md#a-26) |
| BR-RET-04 | Pemilahan retur: `good` → bin penyimpanan (PUT), `damaged` → kondisi `damaged`, `offcut` → potongan baru dengan ID & silsilah ke potongan asal, `waste` → bin Waste. | F1 | Blueprint 7 |
| BR-RET-05 | Klien hanya bisa mengajukan RET untuk barang yang tercatat *Terkirim ke Klien* atau aset `on_loan` di proyeknya. | F1 | [A-21](04-keputusan-dan-asumsi.md#a-21) |

<a id="br-cnv"></a>
## 8. Konversi material — `BR-CNV`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-CNV-01 | Konversi wajib proyek (Proyek Internal untuk persiapan stok) dan terjadi di satu gudang. | F1 | D-10, [A-06](04-keputusan-dan-asumsi.md#a-06) |
| BR-CNV-02 | Neraca ukuran wajib seimbang: Σ input = Σ output + Σ offcut + waste + kerf (toleransi pembulatan satuan). | F1 | Blueprint 6.7 |
| BR-CNV-03 | Sisa ≥ `min_offcut_length` → offcut (potongan baru, item sama); sisa < minimum → waste. `min_offcut_length` wajib untuk item yang bisa dipotong. | F1 | [A-19](04-keputusan-dan-asumsi.md#a-19) |
| BR-CNV-04 | Setiap output/offcut menyimpan `parent_piece_id`/`parent_lot_id` (silsilah dua arah). | F1 | Blueprint 6.7 |
| BR-CNV-05 | Pembalikan CNV hanya bila semua output & offcut masih ada di bin dan belum dipakai dokumen lain. | F1 | P-03 |
| BR-CNV-06 | Resep konversi: template input→output; tidak mengunci hasil nyata. | F2 | Blueprint 6.7 |

<a id="br-ast"></a>
## 9. Aset dipinjamkan — `BR-AST`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-AST-01 | Pemetaan state aset → lokasi → kondisi: `available` → bin storage/available · `reserved` → storage/available + reservasi · `in_transit` → bin in_transit · `on_loan` → bin on_site proyek · `returned`/`inspection` → bin return · `maintenance` → bin storage/quarantine · `damaged` → storage/damaged · `lost`/`written_off` → keluar ledger via ADJ. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-AST-02 | Peminjaman ke orang (bukan proyek klien) wajib memakai Proyek Internal agar bin `on_site` selalu punya proyek. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-AST-03 | Pengembalian wajib pemeriksaan: grade kondisi + foto; grade C/D → `maintenance`/`damaged` dan diteruskan ke Akuntansi. | F1 | Blueprint 6.8 |
| BR-AST-04 | Aset `lost`: ditandai dengan alasan → kejadian `asset_lost_or_damaged` → ADJ keluar (approval) → `written_off`. | F1 | Blueprint 6.8 |
| BR-AST-05 | `usage_days` = hari kalender (zona company) dari `asset_checked_out` sampai `asset_returned` inklusif hari pertama; disediakan ke Akuntansi, tidak dihitung tagihannya di WMS. | F1 | D-07 |
| BR-AST-06 | Aset lewat `due_return_date` masuk laporan harian & notifikasi ke PIC proyek dan Kepala Gudang. | F1 | Blueprint 6.8 |
| BR-AST-07 | Aset dalam `maintenance` tidak bisa dicadangkan; jadwal servis mengubah state otomatis. | F2 | Blueprint 6.8 |

<a id="br-opn"></a>
## 10. Stock opname — `BR-OPN`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-OPN-01 | Angka pembanding = saldo **fisik** per bin (termasuk yang dicadangkan dan yang ada di Loading Area), bukan stok tersedia. | F1 | [A-42](04-keputusan-dan-asumsi.md#a-42) |
| BR-OPN-02 | Pembekuan: bin `frozen` menolak PCK/PUT/SJ/ISU baru. Sesi tidak bisa dimulai bila ada PCK `in_progress` di cakupan; Kepala Gudang dapat **override** untuk SJ mendesak dengan alasan, dan bin itu dihitung ulang setelahnya. | F1 | [A-42](04-keputusan-dan-asumsi.md#a-42) |
| BR-OPN-03 | Transaksi PWA offline yang tiba saat bin beku masuk antrean tinjauan, tidak diposting otomatis. | F2 | [A-42](04-keputusan-dan-asumsi.md#a-42) |
| BR-OPN-04 | Toleransi: selisih diklasifikasi `minor` bila ≤ ambang **relatif** dan ≤ ambang **absolut** (default 1 % dan 1 unit dasar); `moderate` bila ≤ 5 %; selebihnya `major`. Ambang bisa diatur per company & kategori item. | F1 | [A-42](04-keputusan-dan-asumsi.md#a-42) (mengubah A-16) |
| BR-OPN-05 | Hitung ulang wajib oleh penghitung berbeda dari hitungan pertama. | F1 | Blueprint 9 |
| BR-OPN-06 | Rekonsiliasi menghasilkan satu ADJ per gudang; persetujuan di tingkat sesi menggantikan approval per ADJ. | F1 | [A-09](04-keputusan-dan-asumsi.md#a-09) |
| BR-OPN-07 | Selisih `major` wajib kategori akar masalah sebelum sesi disetujui. | F1 | Blueprint 9 |
| BR-OPN-08 | Auditor: read-only terhadap mutasi stok, tetapi boleh membuat sesi, menginput hitungan, dan menyetujui rekonsiliasi sesuai role. Auditor eksternal dibatasi gudang & periode. | F1/F2 | [A-46](04-keputusan-dan-asumsi.md#a-46) |

<a id="br-apr"></a>
## 11. Approval — `BR-APR`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-APR-01 | Aturan approval di-*snapshot* ke dokumen saat masuk `pending_approval`; perubahan aturan tidak memengaruhi dokumen yang sedang menunggu. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-02 | Tanpa aturan → otomatis `approved`, kecuali ADJ manual (minimal satu lapis). | F1 | [A-08](04-keputusan-dan-asumsi.md#a-08), [A-09](04-keputusan-dan-asumsi.md#a-09) |
| BR-APR-03 | SoD: pengaju tidak boleh menyetujui dokumennya; bila aturan menunjuk pengaju, lapis itu dilewati ke atasan. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-04 | Orang yang sama di dua lapis berurutan cukup menyetujui sekali. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-05 | Delegasi tidak berantai (delegat tidak bisa mendelegasikan ulang) dan berperiode. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-06 | Approver nonaktif/keluar → eskalasi otomatis ke approver cadangan/atasan; bila tidak ada, ke Admin Company dengan peringatan. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-07 | Kondisi "jumlah di atas batas" = jumlah satuan dasar per baris **atau** jumlah baris; WMS tidak memakai nilai uang. | F1 | D-07 |
| BR-APR-08 | Batas eskalasi 24 jam kalender (bisa diubah per aturan). | F1 | [A-18](04-keputusan-dan-asumsi.md#a-18) |
| BR-APR-09 | Cara putus "cukup salah satu": keputusan pertama yang tercatat menang; keputusan berikutnya ditolak dengan pesan. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-10 | Keputusan via WhatsApp mencatat nomor pengirim, `wa_message_id`, id token, dan waktu; token sekali pakai dan kedaluwarsa. | F2 | Blueprint 8.2 |
| BR-APR-11 | Simulasi aturan wajib tersedia sebelum aturan disimpan ("siapa yang akan approve dokumen contoh ini?"). | F1 | Blueprint 8.1 |

<a id="br-prj"></a>
## 12. Proyek, klien, portal — `BR-PRJ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-PRJ-01 | Status proyek: `active` → `closed` / `cancelled` → `archived`. Hanya proyek `active` yang menerima dokumen baru. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) |
| BR-PRJ-02 | Guard penutupan: tidak ada REQ `approved`/`in_progress`, SJ `shipped`, DSC `open`, aset `on_loan`, saldo di Gudang Site ≠ 0. Checklist menawarkan: retur ke gudang, transfer ke proyek lain, ISU (pemakaian akhir), atau jadikan waste. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) |
| BR-PRJ-03 | Barang jual-putus yang sudah *Terkirim ke Klien* **tidak** masuk checklist penutupan (milik klien); hanya ditampilkan sebagai ringkasan serah terima akhir. | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-PRJ-04 | Gudang Site dinonaktifkan otomatis saat proyek `closed` dan saldo nol. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) |
| BR-PRJ-05 | Tab **Stok On-site** terdiri dari tiga sub-tampilan: *Di Gudang Site* (stok company), *Aset di Proyek* (bin `on_site`), *Terkirim ke Klien* (riwayat jual-putus). | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-PRJ-06 | Cakupan Klien = proyeknya; klien melihat ketiga sub-tampilan untuk proyeknya, tetapi tidak melihat gudang company lain. | F1 | [A-21](04-keputusan-dan-asumsi.md#a-21) diperjelas |
| BR-PRJ-07 | Portal klien di `<subdomain-company>/portal`; user klien memakai login lokal. | F1 | [A-48](04-keputusan-dan-asumsi.md#a-48) |
| BR-PRJ-08 | Pemakaian material (ISU) hanya dari Gudang Site proyek itu dan hanya item habis pakai; hasilnya kolom *Terpakai* di laporan Material per Proyek. | F1 | [A-32](04-keputusan-dan-asumsi.md#a-32) |

<a id="br-sub"></a>
## 13. Platform & langganan — `BR-SUB`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-SUB-01 | Status langganan: `trial` → `active` → `past_due` (tenggang 7 hari) → `suspended` (hanya-baca 30 hari) → `terminated` (data disimpan 90 hari, bisa diekspor). | F1 | [A-12](04-keputusan-dan-asumsi.md#a-12) |
| BR-SUB-02 | Saat `suspended`: semua aksi tulis ditolak; antrean PWA **ditahan** (`held`), bukan ditolak; job eskalasi/pengingat berhenti; token approval WA dijawab dengan pesan "langganan ditangguhkan". | F1/F2 | [A-44](04-keputusan-dan-asumsi.md#a-44) |
| BR-SUB-03 | Saat `terminated`: hanya Admin Company yang bisa login, hanya untuk ekspor data. | F1 | [A-44](04-keputusan-dan-asumsi.md#a-44) |
| BR-SUB-04 | Super Admin tidak bisa membuka data operasional company tanpa **akses dukungan** yang diberikan Admin Company, berperiode, dan tercatat. | F1 | [A-27](04-keputusan-dan-asumsi.md#a-27) |
| BR-SUB-05 | Login SSO: setelah callback pusat, bila `sub` terpeta ke lebih dari satu user (beda company), tampilkan pemilih company sebelum redirect ke subdomain. | F3 | [A-48](04-keputusan-dan-asumsi.md#a-48) |
| BR-SUB-06 | Satu akun user milik satu company; email sama boleh dipakai di company lain sebagai akun terpisah. | F1 | [A-05](04-keputusan-dan-asumsi.md#a-05) |

---

## 14. Matriks kejadian stok

Satu kejadian per satu pergerakan ledger. Ini menyelesaikan tumpang tindih di Akuntansi v0.2 (`goods_delivered` vs `stock_transferred` vs `asset_checked_out`).

| Pergerakan ledger | Dokumen & status pemicu | Kejadian | Penanda tambahan |
|---|---|---|---|
| Masuk dari vendor → bin receiving/quarantine | GRN (vendor) `received` | `goods_received` | `po_ref` bila ada |
| Karantina → keluar ke vendor | RTV `shipped` | `goods_rejected` | `grn_ref` |
| Bin → Loading Area | PCK `completed` | *(tidak ada kejadian; internal gudang)* | — |
| Loading Area → in_transit | SJ `shipped` | `goods_shipped` | tujuan (proyek/klien/gudang) |
| in_transit → keluar (jual putus ke klien) | SJ `delivered`/`partially_delivered` (bagian diterima) | `goods_delivered` | `ownership = sold` |
| in_transit → on_site (aset) | SJ `delivered` | `asset_checked_out` | serial, proyek, `due_return_date` |
| in_transit → bin gudang tujuan | GRN (dari SJ) `received` | `stock_transferred` | gudang asal/tujuan, proyek asal/tujuan |
| in_transit → disposisi selisih | DSC `resolved` | `delivery_discrepancy` | disposisi per baris |
| Gudang Site → dipakai proyek | ISU `confirmed` | `material_consumed` | proyek |
| Retur masuk → bin return, lalu dipilah | RET `sorted` | `goods_returned` | `ownership = sold \| company`, hasil pilah |
| on_site → bin return (aset) | GRN retur `received` + AST `inspected` | `asset_returned` | grade kondisi, `usage_days` |
| Input → output/offcut/waste | CNV `completed` | `material_converted` | silsilah, kerf |
| Bin Waste → keluar | WST `closed` | `waste_disposed` | disposisi |
| ± penyesuaian | ADJ `posted` | `stock_adjusted` | alasan, `count_session_ref` bila dari OPN |
| Aset ditandai hilang/rusak | aksi `asset.mark_lost` / hasil inspeksi C-D | `asset_lost_or_damaged` | tidak menggerakkan ledger; write-off lewat ADJ |
| Kejadian pembalik | dokumen pembalik `posted`/`completed` | jenis sama dengan asal | `reverses_event_id` |
| Ke Purchasing | PRQ `submitted` / `cancelled` | `purchase_requested` / `purchase_request_cancelled` | — |

Payload minimum setiap kejadian: `event_id`, `schema_version`, `company_id`, `event_type`, `occurred_at` (UTC), `recorded_at`, `timezone`, `source_type`, `source_id`, `source_number`, `project_id`, `from_bin_id`, `to_bin_id`, baris (`item_id`, `lot_id|serial_id|piece_id`, `qty_base`, `base_uom`), `reverses_event_id` (opsional). **Tidak ada harga.**

## 15. Matriks kombinasi pelacakan

| `tracking_mode` | `removal_strategy` yang sah | Kedaluwarsa | `ownership_model` yang sah |
|---|---|---|---|
| `none` | fifo, manual | tidak | consumable |
| `lot` | fifo, fefo, manual | opsional (wajib untuk fefo) | consumable |
| `serial` | manual (fifo sebagai saran urutan) | opsional | consumable, asset, both |
| `piece` | offcut_first (tie-break fifo), manual | tidak | consumable |
