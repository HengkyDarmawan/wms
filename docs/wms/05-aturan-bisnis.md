# Aturan Bisnis, Relasi Dokumen & Matriks Kejadian

**Versi:** 0.10
**Tanggal:** 24 September 2026
**Status:** aturan dari A-25–A-49 dan A-51–A-66 berlaku (23 Sep 2026); BR-PRJ-02, BR-RET-02, BR-SJ-07 memuat bagian dari A-50 (menunggu validasi); baru: BR-GEN-11, BR-STK-15–16, BR-REQ-11–15, BR-SJ-09–10, BR-OPN-09–10, BR-PRJ-09 [F2], BR-AST-08; v0.6: BR-SJ-05/06, BR-REQ-10, BR-RET-05 diubah; v0.7: BR-ACC-01–06 (modul Access); v0.8: BR-MST-01–05 (modul Master); v0.9: BR-WH-01–07 (modul Warehouse); v0.10: BR-LED-01–06 (modul Stock)
**Dokumen terkait:** [Blueprint](01-blueprint.md) · [Katalog Status & Enum](06-katalog-status-dan-enum.md) · [Keputusan & Asumsi](04-keputusan-dan-asumsi.md) · [Akuntansi](../akuntansi/01-lingkup-dan-integrasi-wms.md) · [Purchasing](../purchasing/01-lingkup-dan-integrasi-wms.md)

Dokumen ini mengumpulkan aturan bisnis yang sebelumnya tersebar di prosa Blueprint, memberinya **ID stabil** agar bisa dirujuk spesifikasi modul (Part 4), kasus uji, dan prompt Claude Code (Part 6).

Konvensi:
- ID `BR-<AREA>-nn`. Area: `GEN` umum · `ACC` akses & autentikasi · `MST` master data · `STK` stok · `REQ` permintaan · `SJ` picking & pengiriman · `GRN` penerimaan · `RET` transfer & retur · `CNV` konversi · `AST` aset · `OPN` opname · `APR` approval · `PRJ` proyek & klien · `SUB` platform & langganan.
- Kolom **Fase** memakai `F1` `F2` `F3` ([Blueprint §18](01-blueprint.md#18-peta-modul--fase-rilis)).
- Kolom **Sumber** menunjuk prinsip (P-xx), keputusan (D-xx), asumsi (A-xx), atau bagian Blueprint.
- Aturan tidak dihapus; yang tidak berlaku lagi diberi tanda *(digantikan BR-…)*.

---

## 1. Relasi antar dokumen

Prinsip: **dokumen niat** (permintaan, transfer, retur) dipisahkan dari **dokumen pergerakan fisik** (picking, pengiriman, penerimaan, put-away). Pergerakan fisik antar lokasi selalu memakai `SJ` (keluar) dan `GRN` (masuk), apa pun dokumen niatnya ([A-33](04-keputusan-dan-asumsi.md#a-33)).

```
REQ ──► PCK ──► SJ ──► Bukti Terima ──► (DSC bila selisih)
 │                └──► GRN (bila tujuan Gudang Site / gudang lain) ──► PUT
 └──► backorder ──► TRF (dari gudang lain)  atau  PRQ ──► catatan pemesanan per vendor (F1) / PO di Purchasing (F3) ──► GRN ──► PUT / cross-dock ke Loading Area

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
| REQ | PCK | 1 : n | Satu PCK per gudang sumber per gelombang picking ([A-31](04-keputusan-dan-asumsi.md#a-31)); baris boleh dipecah antar gudang ([A-56](04-keputusan-dan-asumsi.md#a-56)) |
| PCK | SJ | n : 1 | Beberapa PCK (dari beberapa REQ) boleh digabung ke satu SJ bila tujuan & gudang asal sama; sistem menyarankan penggabungan (BR-SJ-09) |
| SJ | DSC | 1 : 0..1 | Dibuat otomatis saat `partially_delivered` |
| SJ | GRN | 1 : 1 | Hanya bila tujuan adalah gudang company (Gudang Site/gudang lain) |
| GRN | PUT | 1 : n | Per baris `passed`; cross-dock tidak membuat PUT |
| GRN | RTV | 1 : n | Per baris `rejected` |
| TRF | PCK / SJ / GRN | 1 : n | Mengikuti jalur REQ; transfer dalam proyek antar Gudang Site memakai jalur ringan ([A-50](04-keputusan-dan-asumsi.md#a-50)) |
| RET | SJ / GRN | 1 : 1 | SJ opsional |
| REQ | TRF, PRQ | 1 : n | Dibuat dari backorder saat approval |
| PRQ | Catatan Pemesanan | 1 : n | Satu catatan per vendor/toko; satu baris PRQ boleh dipecah ke beberapa catatan ([A-51](04-keputusan-dan-asumsi.md#a-51)) |
| Catatan Pemesanan | GRN | 1 : n | Baris GRN merujuk baris catatan pemesanan, turunan baris PRQ ([A-47](04-keputusan-dan-asumsi.md#a-47), [A-51](04-keputusan-dan-asumsi.md#a-51)) |
| OPN | ADJ | 1 : n | Satu ADJ per gudang dalam cakupan |
| Proyek | REQ, SJ, ISU, CNV, RET, AST, WST | 1 : n | Semua wajib proyek kecuali GRN vendor, PUT, OPN, ADJ, RTV, PRQ |

Setiap dokumen menyimpan `source_type` + `source_id` (polimorfik) ke induknya, dan setiap baris dokumen anak menyimpan rujukan ke baris induk agar pemenuhan bisa dihitung per baris.

---

<a id="br-gen"></a>
## 2. Aturan umum — `BR-GEN`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-GEN-01 | Semua perubahan status hanya lewat aksi bernama di [Katalog Status](06-katalog-status-dan-enum.md); status di luar katalog dilarang. | F1 | P-04 |
| BR-GEN-02 | Menolak dan membatalkan wajib alasan dari master **Alasan**; teks bebas *Keterangan* opsional (lihat BR-GEN-11). | F1 | P-04, Blueprint 6.3a |
| BR-GEN-03 | Dokumen yang sudah menggerakkan stok tidak bisa dibatalkan; koreksi lewat **dokumen pembalik** yang merujuk dokumen asal (`reversal_of_id`) dan menerbitkan kejadian dengan `reverses_event_id`. | F1 | P-03 |
| BR-GEN-04 | Batasan pembatalan/pembalikan: GRN `received` → tidak bisa dibatalkan (pakai ADJ/RTV) · SJ `shipped` → tidak bisa (pakai DSC/RET) · CNV `completed` → hanya bila tidak ada output/offcut yang sudah dipakai · ISU `confirmed` → ISU pembalik dengan approval · master (item, bin, gudang) → tidak bisa dinonaktifkan bila saldo atau reservasi ≠ 0. | F1 | P-03, F-16 audit |
| BR-GEN-05 | Dua catatan resmi: **`document_timeline`** (per dokumen: status, pelaku, waktu, kanal, catatan; dibaca user) dan **`audit_log`** (teknis: tabel, kolom, nilai lama → baru, IP; hanya Admin). Keduanya *append-only*. | F1 | P-07, NFR-03 |
| BR-GEN-06 | Nomor dokumen: format per company ([A-17](04-keputusan-dan-asumsi.md#a-17)); segmen `{GUDANG}` = gudang asal untuk SJ/TRF/PCK/GRN/PUT/ADJ/RTV, gudang pemenuh untuk REQ/CNV/WST/ISU, `ALL` untuk OPN multi-gudang dan PRQ. Urutan dikunci di database (`SELECT … FOR UPDATE` pada `document_sequence`). Dokumen yang dibuat offline memakai nomor sementara `TMP-<uuid>` dan diberi nomor final saat sinkron. | F1 | [A-43](04-keputusan-dan-asumsi.md#a-43) |
| BR-GEN-07 | Waktu disimpan **UTC**; tampilan, reset urutan bulanan, batas hari, dan `usage_days` memakai zona waktu company. Kejadian stok membawa `occurred_at` (UTC) dan `timezone`. | F1 | NFR-07 |
| BR-GEN-08 | Semua angka kuantitas `DECIMAL(18,4)` dalam satuan dasar item; pembulatan mengikuti aturan per satuan. | F1 | [A-37](04-keputusan-dan-asumsi.md#a-37) |
| BR-GEN-09 | Cakupan akses melekat pada **penugasan role × scope** (user bisa Kepala Gudang di A dan Staf di B). Role Klien tidak bisa digabung dengan role internal. | F1 | [A-46](04-keputusan-dan-asumsi.md#a-46) |
| BR-GEN-10 | Kebutuhan bertag `[F2]`/`[F3]` di Fase 1 dibangun sebagai *stub*: tabel & titik sambung ada, UI tidak ditampilkan. | F1 | audit |
| BR-GEN-11 | **Form:** setiap field wajib ditandai `*` di label. Pada aksi **tolak/batal**: *Alasan* (master Alasan) wajib `*`, kolom *Keterangan* (teks bebas, `notes`) **opsional**. Berlaku untuk semua dokumen dan master. | F1 | Pemilik produk, 23 Sep 2026 |

<a id="br-mst"></a>
## 13b. Master data — `BR-MST`

Aturan yang lahir dari [spesifikasi modul Master](11-master.md); melengkapi P-03 dan BR-STK-08..12.

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-MST-01 | Kode master (klien, proyek, vendor, item, kategori, satuan, alasan) unik per company, disimpan huruf besar, dan **tidak bisa diubah** setelah dibuat. | F1 | 11-master §5 |
| BR-MST-02 | Satuan dasar item tidak bisa diubah setelah item punya lot, serial, potongan, atau pernah dipakai dokumen — mengubahnya akan membuat saldo lama tidak bisa ditafsirkan. | F1 | P-01, 11-master §5 |
| BR-MST-03 | Setiap kategori satuan wajib punya satu **satuan acuan** dengan `factor_to_reference = 1`; faktor satuan lain relatif terhadapnya. | F1 | Blueprint 6.5 |
| BR-MST-04 | **Proyek Internal** ([A-06](04-keputusan-dan-asumsi.md#a-06)) tidak boleh punya klien, dan company wajib punya minimal satu Proyek Internal aktif selama fitur konversi atau peminjaman ke orang dipakai. | F1 | [A-06](04-keputusan-dan-asumsi.md#a-06), [BR-AST-02](#br-ast) |
| BR-MST-05 | Master hanya bisa dinonaktifkan bila tidak sedang dipakai data aktif (mis. kategori dengan item aktif, klien dengan proyek aktif, satuan yang menjadi satuan dasar item aktif). Penonaktifan memakai Alasan `*` ([BR-GEN-11](#br-gen)). | F1 | P-03 |

<a id="br-wh"></a>
## 13c. Gudang & lokasi — `BR-WH`

Aturan yang lahir dari [spesifikasi modul Warehouse](12-warehouse.md); melengkapi BR-STK-02, BR-STK-07, BR-STK-13, BR-STK-14, dan BR-GEN-04 yang sebelumnya tersebar.

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-WH-01 | Kode bin diturunkan dari hierarki `{GUDANG}-{ZONA}-{RAK}-{LEVEL}-{BIN}` (mis. `CKG-A-R03-L2-B05`) dan **tidak bisa diubah** setelah bin dibuat, karena kode itu tercetak di label dan tersimpan di riwayat mutasi. | F1 | Blueprint 6.3, 12-warehouse §5 |
| BR-WH-02 | Setiap gudang baru otomatis mendapat bin bawaan `receiving`, `staging`, `quarantine`, `return`, `waste`, dan satu bin virtual `in_transit` miliknya sendiri ([BR-STK-13](#br-stk)). Bin virtual tidak bisa dinonaktifkan selama gudangnya aktif. | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29), 12-warehouse §5 |
| BR-WH-03 | Bin `on_site` dibuat **satu per proyek**, wajib punya `project_id`, dan hanya menampung aset ([BR-STK-14](#br-stk)). | F1 | [A-29](04-keputusan-dan-asumsi.md#a-29) |
| BR-WH-04 | Gudang bertipe `site` **wajib** punya `project_id`; tipe lain wajib tidak punya. Satu proyek boleh punya beberapa Gudang Site ([A-40](04-keputusan-dan-asumsi.md#a-40)). | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40), [D-15](04-keputusan-dan-asumsi.md#d-15) |
| BR-WH-05 | Hierarki gudang tidak boleh melingkar: sebuah gudang tidak boleh menjadi induk dirinya sendiri, langsung maupun berantai. | F1 | [D-15](04-keputusan-dan-asumsi.md#d-15) |
| BR-WH-06 | Kapasitas bin ditegakkan menurut `capacity_mode` kategori penyimpanannya: `warn` memberi peringatan, `block` menolak penempatan ([BR-STK-07](#br-stk)). | F1 | [A-37](04-keputusan-dan-asumsi.md#a-37) |
| BR-WH-07 | Gudang dan bin **dinonaktifkan, tidak dihapus** (P-03), dan penonaktifan ditolak bila masih ada bin aktif, saldo, atau reservasi ([BR-GEN-04](#br-gen)). Penonaktifan memakai Alasan `*` ([BR-GEN-11](#br-gen)). | F1 | P-03, 12-warehouse §5 |

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
| BR-STK-15 | **Tutup periode stok:** company punya `stock_lock_date`; mutasi apa pun (termasuk ADJ dan dokumen pembalik) dengan `occurred_at` ≤ tanggal kunci ditolak; koreksi diposting di periode berjalan dengan rujukan dokumen asal. Kunci dimajukan Admin Company (`stock.lock_period`, keterangan opsional) atau otomatis saat sesi OPN bulanan `closed`. | F1 | [A-58](04-keputusan-dan-asumsi.md#a-58) |
| BR-STK-16 | **Reservasi menggantung:** reservasi `active` yang belum punya PCK lebih dari `reservation_alert_days` (default 7) masuk laporan *Reservasi Menggantung* dan memicu notifikasi ke Kepala Gudang & pemohon. Pelepasan hanya lewat `request.close_short` / `request.cancel` / `transfer.cancel`, tidak otomatis. | F1 | [A-59](04-keputusan-dan-asumsi.md#a-59) |

<a id="br-led"></a>
## 3a. Kartu stok & outbox — `BR-LED`

Aturan yang lahir dari [spesifikasi modul Stock](13-stock.md); melengkapi P-01 dan BR-STK-01 dengan hal-hal yang sebelumnya hanya tersirat di ERD.

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-LED-01 | Setiap pergerakan wajib punya bin asal, bin tujuan, atau keduanya. Baris tanpa keduanya tidak menggerakkan apa pun dan ditolak. | F1 | P-01, 13-stock §5 |
| BR-LED-02 | `qty_base` selalu **lebih besar dari nol**; arah pergerakan ditentukan pasangan asal dan tujuan, bukan tanda bilangan. Ini membuat penjumlahan saldo tidak pernah bergantung pada tanda. | F1 | 13-stock §3.1 |
| BR-LED-03 | Pergerakan item berpelacakan wajib menyebut turunan yang sesuai: lot untuk `lot`, serial untuk `serial`, potongan untuk `piece` ([BR-STK-11](#br-stk)). | F1 | [D-13](04-keputusan-dan-asumsi.md#d-13) |
| BR-LED-04 | Satu serial hanya boleh berada di satu bin pada satu waktu; saldonya selalu satu atau nol. Memasukkan serial yang sama ke bin kedua ditolak selama belum keluar dari bin pertama. | F1 | [BR-STK-08](#br-stk) |
| BR-LED-05 | **Pembalikan** menyalin pergerakan asal dengan asal dan tujuan ditukar, menunjuknya lewat `reverses_movement_id`, dan hanya boleh **sekali** per baris. Tidak ada penghapusan (P-03). | F1 | P-01, P-03 |
| BR-LED-06 | Kejadian stok ditulis ke outbox **dalam transaksi yang sama** dengan pergerakannya; kegagalan menulis kejadian membatalkan pergerakan. | F1 | [AD-05](08-arsitektur.md) |

<a id="br-req"></a>
## 4. Permintaan material — `BR-REQ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-REQ-01 | Satu REQ untuk satu proyek; setiap baris punya `required_date` (default = tanggal dibutuhkan header). | F1 | [A-22](04-keputusan-dan-asumsi.md#a-22), [A-39](04-keputusan-dan-asumsi.md#a-39) |
| BR-REQ-02 | Permintaan dari Klien masuk `under_review`. Staf boleh mengubah jumlah/baris; setiap perubahan tercatat di timeline dengan nilai lama → baru dan klien diberi tahu. Perubahan tidak butuh persetujuan balik klien, kecuali penggantian item (BR-REQ-13). Klien membatalkan langsung hanya sebelum `approved`; sesudahnya lewat permintaan pembatalan (BR-REQ-15). Klien menambah baris: BR-REQ-12. | F1 | [A-07](04-keputusan-dan-asumsi.md#a-07), [A-39](04-keputusan-dan-asumsi.md#a-39), [A-54](04-keputusan-dan-asumsi.md#a-54), [A-61](04-keputusan-dan-asumsi.md#a-61) |
| BR-REQ-03 | Baris non-katalog wajib **dipetakan** oleh staf ke item yang ada atau item baru berstatus `provisional` sebelum meninggalkan `under_review`. Item `provisional` harus dilengkapi Admin sebelum GRN pertama. | F1 | [A-39](04-keputusan-dan-asumsi.md#a-39) |
| BR-REQ-04 | **Gudang sumber** per baris ditetapkan sebelum `pending_approval` (oleh staf untuk REQ klien, oleh pemohon/sistem untuk REQ internal); sistem menyarankan gudang terdekat/induk dengan stok tersedia. Satu baris boleh **dipecah** menjadi beberapa baris dengan gudang sumber berbeda (`split_from_line_id`, total tetap); sistem menyarankan pemecahan bila satu gudang tidak cukup, dan tiap gudang mengirim langsung ke tujuan tanpa transfer dulu. | F1 | [A-31](04-keputusan-dan-asumsi.md#a-31), [A-56](04-keputusan-dan-asumsi.md#a-56) |
| BR-REQ-05 | Saat `approved`, tiap baris harus punya sumber: (a) stok tersedia → reservasi lunak; (b) transfer dari gudang lain → TRF dibuat; (c) pembelian → PRQ dibuat. Baris tanpa sumber menahan approval. Ini menggantikan P-02 v0.2. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-REQ-06 | Untuk item `both`, baris menyimpan `line_ownership` = `buy` atau `loan` (default dari item). Baris `loan` hanya untuk item berserial. | F1 | [A-38](04-keputusan-dan-asumsi.md#a-38) |
| BR-REQ-07 | Pemohon tidak boleh menjadi approver dokumennya sendiri (SoD). | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-REQ-08 | Barang backorder yang tiba (GRN merujuk PRQ/TRF) otomatis direservasi ke REQ penunggu dengan urutan `required_date`, lalu disarankan **cross-dock**. | F1 | [A-47](04-keputusan-dan-asumsi.md#a-47) |
| BR-REQ-09 | REQ `partially_fulfilled` boleh ditutup dengan sisa (`closed_short`) oleh Kepala Gudang atau pemohon; sisa reservasi & PRQ yang belum diteruskan dibatalkan. | F1 | [A-30](04-keputusan-dan-asumsi.md#a-30) |
| BR-REQ-10 | Setelah bukti terima diisi driver/penerima, pemohon (internal atau klien) memilih `request.confirm_receipt` **atau** `request.dispute_receipt` (per baris: kurang/rusak + foto) dalam `receipt_confirm_days` (default 3, per company); diam = `auto_confirmed`. Bila bukti terima diisi oleh akun pemohon sendiri (tautan bertoken/portal), konfirmasi otomatis saat itu. Keberatan membuka DSC (atau menambah baris ke DSC `open` SJ itu) — lihat BR-SJ-10. Konfirmasi menutup baris. | F1 | Blueprint 4.2, [A-63](04-keputusan-dan-asumsi.md#a-63) |
| BR-REQ-11 | **Titik pesan ulang:** job harian membuat **draf** PRQ (`origin = reorder_point`) per gudang untuk item yang stok tersedianya < titik pesan ulang (jumlah = stok minimum − tersedia, minimal sebesar titik pesan ulang); Kepala Gudang meninjau lalu `pr.submit` atau `pr.cancel`. Tidak dibuat ulang selama masih ada draf/PRQ terbuka untuk item & gudang yang sama. | F1 | [A-52](04-keputusan-dan-asumsi.md#a-52), Blueprint 6.4 |
| BR-REQ-12 | **Klien menambah baris:** boleh menambah (bukan mengurangi/menghapus) selama `draft`/`submitted`/`under_review`; saat `pending_approval`, penambahan mengembalikan REQ ke `under_review` dan snapshot approval dibuang. Setelah `approved`, tambahan menjadi **REQ Tambahan** (`origin = supplement`, `parent_request_id`), nomor & approval sendiri, tampil sebagai anak di REQ induk. | F1 | [A-54](04-keputusan-dan-asumsi.md#a-54) |
| BR-REQ-13 | **Penggantian item:** saat staf memetakan baris klien ke item lain, sistem memberi tahu klien "X diganti menjadi Y"; klien boleh menolak baris itu (`substitution_response = rejected`, alasan `*`) sampai `substitution_deadline_at` (= saat penggantian + `substitution_objection_days`, default 1 hari); lewat batas = `expired` (dianggap setuju). REQ tidak tertahan; baris yang ditolak `cancelled` dan reservasinya dilepas (BR-STK-05). | F1 | [A-55](04-keputusan-dan-asumsi.md#a-55) |
| BR-REQ-14 | **SLA tinjau & tanggal janji:** REQ klien di `under_review` lebih dari `review_sla_days` (default 1 hari kerja) → pengingat ke staf & Kepala Gudang, diulang harian. Staf mengisi `promised_date` per baris saat tinjau/penetapan sumber (default = `required_date` bila stok tersedia); tampil di portal; perubahan tercatat di timeline dan diberitahukan ke klien. | F1 | [A-60](04-keputusan-dan-asumsi.md#a-60) |
| BR-REQ-15 | **Permintaan pembatalan oleh klien:** setelah `approved`, klien mengajukan `request.request_cancel` per baris yang belum ada SJ `shipped` (alasan `*`); staf/Kepala Gudang `request.confirm_cancel` → baris `cancelled`, reservasi/PCK/PRQ/TRF terkait dilepas; ditolak → baris tetap, klien diberi tahu. Sebelum `approved` klien membatalkan langsung (`request.cancel`). | F1 | [A-61](04-keputusan-dan-asumsi.md#a-61) |

<a id="br-sj"></a>
## 5. Picking, pengiriman, bukti terima — `BR-SJ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-SJ-01 | Picking hanya dari bin yang memang menyimpan alokasi; scan bin + item/lot/serial/potongan wajib di PWA, boleh manual di web dengan alasan. | F1 | P-06 |
| BR-SJ-02 | **Short pick**: bila fisik kurang dari alokasi, staf mencatat jumlah nyata + alasan; sistem menandai bin untuk hitung (`count_flag`), mengurangi SJ, dan mengembalikan sisa ke backorder REQ. | F1 | [A-35](04-keputusan-dan-asumsi.md#a-35) |
| BR-SJ-03 | Cross-dock: baris GRN yang ditunggu REQ langsung ke Loading Area tanpa PCK; REQ tetap `in_progress`. | F1 | Blueprint 7 |
| BR-SJ-04 | Efek `delivered` per tujuan & kepemilikan: ke **Gudang Site/gudang lain** → tetap `in_transit` sampai GRN tujuan `received` (kejadian `stock_transferred`); **jual putus ke klien** → keluar ledger, riwayat *Terkirim ke Klien* (kejadian `goods_delivered`); **aset** → pindah ke bin `on_site` proyek (kejadian `asset_checked_out`). | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-SJ-05 | Bukti terima = satu per SJ: foto, tanda tangan, GPS, dan **per baris** jumlah *baik / rusak / kurang* (serial & potongan **per unit**); foto wajib `*` bila rusak; diisi Driver (user company) atau penerima tanpa akun lewat **tautan bertoken sekali pakai + OTP WA/SMS**, berlaku 24 jam. | F1 | [A-20](04-keputusan-dan-asumsi.md#a-20), [A-41](04-keputusan-dan-asumsi.md#a-41), [A-64](04-keputusan-dan-asumsi.md#a-64) |
| BR-SJ-06 | Jumlah **baik** < dikirim (kurang dan/atau rusak) → SJ `partially_delivered` dan DSC `open`; kurang & rusak tetap di `in_transit` sampai DSC `resolved` (rusak langsung berkondisi `damaged`). Keberatan klien dalam batas konfirmasi (BR-REQ-10) juga membuka/menambah DSC. | F1 | [A-35](04-keputusan-dan-asumsi.md#a-35), [A-63](04-keputusan-dan-asumsi.md#a-63), [A-64](04-keputusan-dan-asumsi.md#a-64) |
| BR-SJ-07 | SJ mencatat **cara kirim** `shipment_method`: `own_fleet` → kendaraan & driver wajib `*`; `carrier` → nama ekspedisi & nomor resi wajib `*`; `self_delivered` (transfer dalam proyek, [A-50](04-keputusan-dan-asumsi.md#a-50)) → nama pembawa wajib `*`. Ongkir tidak dicatat WMS (D-07); nama ekspedisi & resi ikut kejadian `goods_shipped`. | F1 | Blueprint 6.3a, [A-50](04-keputusan-dan-asumsi.md#a-50), [A-57](04-keputusan-dan-asumsi.md#a-57) |
| BR-SJ-08 | Driver Fase 1: draf bukti terima disimpan di perangkat (localStorage) bila offline dan dikirim saat online; ini bukan sinkron penuh (Fase 2). | F1 | [A-49](04-keputusan-dan-asumsi.md#a-49) |
| BR-SJ-09 | **Gabung pengiriman:** satu SJ boleh memuat beberapa PCK dari beberapa REQ selama proyek/tujuan dan gudang asal sama; satu REQ boleh dikirim bertahap. Saat membuat SJ, sistem menawarkan semua PCK `completed` di Loading Area gudang itu dengan tujuan sama. Portal klien dan tab Pengiriman menampilkan status per REQ (per baris). | F1 | Pemilik produk 23 Sep 2026, [A-31](04-keputusan-dan-asumsi.md#a-31) |
| BR-SJ-10 | **Selisih & posisi barang rusak.** Baris DSC = jenis (`missing`/`damaged`) × jumlah × disposisi × keputusan klien (`still_needed` default / `not_needed`). Disposisi: `returned_to_warehouse` (fisik kembali; rusak → GRN retur → bin Retur → pemilahan `damaged`), `adjusted` (hilang; keluar ledger dengan alasan), `claimed` (klaim ekspedisi; keluar ledger dengan `claim_ref`), `reship` (kirim pengganti: jumlah kembali ke backorder baris REQ → SJ berikutnya; barang baru dari stok). Rusak boleh gabungan fisik + `reship`; `not_needed` menutup sisa baris. **Posisi:** saat bukti terima, rusak & kurang tetap di bin `in_transit` gudang asal; rusak berkondisi `damaged` seketika, kurang `available`. `own_fleet`/`self_delivered`: rusak **wajib dibawa balik saat itu**; `carrier`: ditinggal (tidak masuk stok klien/site) → RET + `claimed`. Laporan *Posisi Barang Rusak & Selisih*: rusak dalam perjalanan, di bin Retur, diklaim, DSC terbuka > N hari. | F1 | [A-64](04-keputusan-dan-asumsi.md#a-64), [A-65](04-keputusan-dan-asumsi.md#a-65) |

<a id="br-grn"></a>
## 6. Penerimaan, QC, put-away, retur ke vendor — `BR-GRN`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-GRN-01 | Ledger diposting saat GRN `received` ke bin `receiving`, atau ke `quarantine` bila item/company mewajibkan QC. Kejadian stok terbit pada saat yang sama. Baris GRN vendor merujuk baris **catatan pemesanan** PRQ bila ada ([A-51](04-keputusan-dan-asumsi.md#a-51)). | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-02 | QC adalah langkah per baris dengan hasil `passed`/`quarantined`/`rejected`; `rejected` menunggu RTV, `quarantined` menunggu keputusan ulang. | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-03 | Put-away menyarankan bin berdasarkan kategori penyimpanan item, kapasitas, dan kedekatan zona; staf boleh mengganti. | F1 | Blueprint 6.3 |
| BR-GRN-04 | Retur ke vendor (RTV) hanya dari bin `quarantine`; barang pengganti masuk lewat GRN baru yang merujuk RTV. | F1 | [A-34](04-keputusan-dan-asumsi.md#a-34) |
| BR-GRN-05 | GRN dari SJ/RET tidak boleh menerima lebih dari yang dikirim; kelebihan dicatat sebagai baris tanpa rujukan dan memicu ADJ. | F1 | audit |

<a id="br-ret"></a>
## 7. Transfer & retur — `BR-RET`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-RET-01 | TRF dan RET adalah dokumen niat + approval; pergerakan fisik lewat SJ + GRN (BR-GEN, [§1](#1-relasi-antar-dokumen)). | F1 | [A-33](04-keputusan-dan-asumsi.md#a-33) |
| BR-RET-02 | Transfer antar proyek = transfer antar Gudang Site (atau `on_site` untuk aset) dengan proyek asal/tujuan; menggantikan "Next Project" prototipe. **Transfer dalam proyek** = TRF antar Gudang Site proyek yang sama (`from_project_id` = `to_project_id`) dengan jalur ringan: tanpa approval bila tidak ada aturan, SJ boleh `self_delivered`, GRN = konfirmasi PIC titik tujuan. | F1 | Blueprint 7, [A-50](04-keputusan-dan-asumsi.md#a-50) |
| BR-RET-03 | Retur merujuk SJ asal bila ada; baris jual-putus yang diretur menghasilkan `goods_returned` dengan `ownership = sold` (retur penjualan), baris dari Gudang Site dengan `ownership = company`. | F1 | [A-26](04-keputusan-dan-asumsi.md#a-26) |
| BR-RET-04 | Pemilahan retur: `good` → bin penyimpanan (PUT), `damaged` → kondisi `damaged`, `offcut` → potongan baru dengan ID & silsilah ke potongan asal, `waste` → bin Waste. | F1 | Blueprint 7 |
| BR-RET-05 | Klien hanya bisa mengajukan RET untuk barang yang tercatat *Terkirim ke Klien*, aset `on_loan` di proyeknya, atau barang rusak yang ditinggal ekspedisi (DSC `claimed`, BR-SJ-10). | F1 | [A-21](04-keputusan-dan-asumsi.md#a-21), [A-65](04-keputusan-dan-asumsi.md#a-65) |

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
| BR-AST-07 | Aset dalam `maintenance` tidak bisa dicadangkan; jadwal servis mengubah state otomatis; `[F2]` pemicu dari meter/skor kondisi. | F2 | Blueprint 6.8 |
| BR-AST-08 | **Meter, umur pakai, skor kondisi:** serial menyimpan `acquired_at`, `meter_unit` (hour/km/none), `meter_total`, `expected_life_days`/`expected_life_hours`, `condition_score` (0–100 %). Serah terima keluar mencatat `meter_out`, kembali mencatat `meter_in` (`meter_in ≥ meter_out`, kecuali penggantian meter dengan alasan). Pemeriksaan wajib mengisi grade **dan** skor + catatan komponen; ini riwayat kondisi aset. Sisa umur % = 100 − max(hari pakai/umur hari, jam pakai/umur jam) × 100; peringatan bila < `asset_life_alert_pct` (default 20 %). Nilai penyusutan tidak dihitung WMS (D-07); kejadian `asset_checked_out`/`asset_returned` membawa meter, jam/hari pakai, grade, skor. | F1 | [A-66](04-keputusan-dan-asumsi.md#a-66) |

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
| BR-OPN-09 | **Pemisahan tugas opname:** penghitung suatu sesi tidak boleh menyetujui sesi itu; sesi `annual` dan sesi audit (`adhoc`/`spot_check` yang dibuat Auditor) disetujui oleh Auditor Internal atau Manajemen, bukan Kepala Gudang gudang dalam cakupan. Diterapkan lewat guard `count.approve` dan aturan approval bawaan OPN. | F1 | Pemilik produk 23 Sep 2026, BR-APR-03 |
| BR-OPN-10 | **Pemeriksaan mendadak** (`count_type = spot_check`) oleh Auditor/Kepala Gudang: cakupan beberapa bin/item, **tanpa pembekuan** (BR-OPN-02 tidak berlaku), hitung buta, klasifikasi selisih sama (BR-OPN-04), hasil masuk dashboard akurasi. Sesi spot check tidak memposting ADJ sendiri; selisih `major` ditindaklanjuti lewat sesi opname biasa atau ADJ manual. | F1 | Pemilik produk 23 Sep 2026 |

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
| BR-APR-07 | Kondisi "jumlah di atas batas" = jumlah satuan dasar per baris **atau** jumlah baris; WMS tidak memakai nilai uang. PRQ juga boleh memakai kondisi **jenis vendor** dan **asal PRQ** (backorder/manual/titik pesan ulang); REQ `[F2]` kondisi "melebihi rencana proyek". Approval berdasarkan nilai uang ada di modul Purchasing dengan mesin approval yang sama ([D-28](04-keputusan-dan-asumsi.md#d-28)). | F1 | D-07, D-28, [A-52](04-keputusan-dan-asumsi.md#a-52) |
| BR-APR-08 | Batas eskalasi 24 jam kalender (bisa diubah per aturan). | F1 | [A-18](04-keputusan-dan-asumsi.md#a-18) |
| BR-APR-09 | Cara putus "cukup salah satu": keputusan pertama yang tercatat menang; keputusan berikutnya ditolak dengan pesan. | F1 | [A-45](04-keputusan-dan-asumsi.md#a-45) |
| BR-APR-10 | Keputusan via WhatsApp mencatat nomor pengirim, `wa_message_id`, id token, dan waktu; token sekali pakai dan kedaluwarsa. | F2 | Blueprint 8.2 |
| BR-APR-11 | Simulasi aturan wajib tersedia sebelum aturan disimpan ("siapa yang akan approve dokumen contoh ini?"). | F1 | Blueprint 8.1 |

<a id="br-prj"></a>
## 12. Proyek, klien, portal — `BR-PRJ`

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-PRJ-01 | Status proyek: `active` → `closed` / `cancelled` → `archived`. Hanya proyek `active` yang menerima dokumen baru. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) |
| BR-PRJ-02 | Guard penutupan: tidak ada REQ `approved`/`in_progress`, SJ `shipped`, DSC `open`, aset `on_loan`, saldo ≠ 0 di **salah satu** Gudang Site proyek (satu proyek boleh punya beberapa). Checklist per Gudang Site menawarkan: retur ke gudang, transfer ke proyek lain, ISU (pemakaian akhir), atau jadikan waste. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) (diubah 23 Sep 2026) |
| BR-PRJ-03 | Barang jual-putus yang sudah *Terkirim ke Klien* **tidak** masuk checklist penutupan (milik klien); hanya ditampilkan sebagai ringkasan serah terima akhir. | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-PRJ-04 | **Setiap** Gudang Site proyek dinonaktifkan otomatis saat proyek `closed` dan saldonya nol. | F1 | [A-40](04-keputusan-dan-asumsi.md#a-40) |
| BR-PRJ-05 | Tab **Stok On-site** terdiri dari tiga sub-tampilan: *Di Gudang Site* (stok company), *Aset di Proyek* (bin `on_site`), *Terkirim ke Klien* (riwayat jual-putus). | F1 | [A-25](04-keputusan-dan-asumsi.md#a-25) |
| BR-PRJ-06 | Cakupan Klien = proyeknya; klien melihat ketiga sub-tampilan untuk proyeknya, tetapi tidak melihat gudang company lain. | F1 | [A-21](04-keputusan-dan-asumsi.md#a-21) diperjelas |
| BR-PRJ-07 | Portal klien di `<subdomain-company>/portal`; user klien memakai login lokal. | F1 | [A-48](04-keputusan-dan-asumsi.md#a-48) |
| BR-PRJ-08 | Pemakaian material (ISU) hanya dari Gudang Site proyek itu dan hanya item habis pakai; hasilnya kolom *Terpakai* di laporan Material per Proyek. | F1 | [A-32](04-keputusan-dan-asumsi.md#a-32) |
| BR-PRJ-09 | **Rencana kebutuhan material** per proyek (`project_material_plans`: item × jumlah rencana, berversi, unggah Excel oleh klien/PIC); laporan Material per Proyek menambah kolom *Rencana*; kondisi approval REQ "melebihi rencana". Fase 1 dibangun sebagai stub (BR-GEN-10). | F2 | [A-62](04-keputusan-dan-asumsi.md#a-62) |

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

<a id="br-acc"></a>
## 13a. Akses & autentikasi — `BR-ACC`

Aturan yang lahir dari [spesifikasi modul Access](10-access.md); berlaku untuk semua modul yang memakai cakupan.

| ID | Aturan | Fase | Sumber |
|---|---|---|---|
| BR-ACC-01 | User hanya bisa login bila punya minimal satu penugasan role yang berlaku (tanggal) — kecuali role `company_admin` bawaan. | F1 | 10-access §5 |
| BR-ACC-02 | Admin Company aktif terakhir tidak bisa dinonaktifkan dan rolenya tidak bisa dicabut. | F1 | 10-access §5 |
| BR-ACC-03 | Role Klien eksklusif: user dengan `client_id` hanya boleh role Klien, dan role Klien tidak bisa digabung role internal (memperjelas BR-GEN-09). | F1 | [A-46](04-keputusan-dan-asumsi.md#a-46) |
| BR-ACC-04 | Cakupan `all` hanya untuk role internal; role Klien selalu bercakupan klien/proyek. | F1 | 10-access §5 |
| BR-ACC-05 | Cakupan efektif user = gabungan semua penugasan role yang berlaku; setiap query data bergudang/berproyek melewati global scope `ScopedToUser`; Admin Company & Manajemen = semua. | F1 | [BR-GEN-09](#br-gen), [AD-06](08-arsitektur.md#2-keputusan-arsitektur) |
| BR-ACC-06 | Sesi terikat subdomain company; ganti/atur ulang password menghapus sesi lain; password minimal 10 karakter dan tidak sama dengan 3 password terakhir; 5 gagal login → kunci 15 menit (NFR-04). | F1 | Blueprint 13, NFR-04 |

---

## 14. Matriks kejadian stok

Satu kejadian per satu pergerakan ledger. Ini menyelesaikan tumpang tindih di Akuntansi v0.2 (`goods_delivered` vs `stock_transferred` vs `asset_checked_out`).

| Pergerakan ledger | Dokumen & status pemicu | Kejadian | Penanda tambahan |
|---|---|---|---|
| Masuk dari vendor → bin receiving/quarantine | GRN (vendor) `received` | `goods_received` | `po_ref` bila ada |
| Karantina → keluar ke vendor | RTV `shipped` | `goods_rejected` | `grn_ref` |
| Bin → Loading Area | PCK `completed` | *(tidak ada kejadian; internal gudang)* | — |
| Loading Area → in_transit | SJ `shipped` | `goods_shipped` | tujuan (proyek/klien/gudang) |
| in_transit → keluar (jual putus ke klien) | SJ `delivered`/`partially_delivered` (bagian **baik** yang diterima) | `goods_delivered` | `ownership = sold` |
| in_transit (available) → in_transit (damaged) | bukti terima, baris rusak (BR-SJ-10) | *(tidak ada kejadian; kondisi berubah, lokasi tetap)* | — |
| in_transit → on_site (aset) | SJ `delivered` | `asset_checked_out` | serial, proyek, `due_return_date`, `meter_out` |
| in_transit → bin gudang tujuan | GRN (dari SJ) `received` | `stock_transferred` | gudang asal/tujuan, proyek asal/tujuan |
| in_transit → disposisi selisih | DSC `resolved` | `delivery_discrepancy` | jenis (kurang/rusak), disposisi per baris, keputusan klien; `reship` tidak menerbitkan kejadian sendiri (SJ berikutnya → `goods_shipped`) |
| Gudang Site → dipakai proyek | ISU `confirmed` | `material_consumed` | proyek |
| Retur masuk → bin return, lalu dipilah | RET `sorted` | `goods_returned` | `ownership = sold \| company`, hasil pilah |
| on_site → bin return (aset) | GRN retur `received` + AST `inspected` | `asset_returned` | grade kondisi, `condition_score`, `usage_days`, `usage_hours`, `meter_in` |
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
