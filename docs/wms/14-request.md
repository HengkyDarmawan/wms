# Spesifikasi Modul — `request` (Permintaan Material)

**Versi:** 0.9
**Tanggal:** 25 September 2026
**Status:** terimplementasi (Fase 1) — modul kelima setelah [Stock](13-stock.md); v0.5: approval REQ lewat mesin approval ([20-approval](20-approval.md)); v0.6: baris bersumber transfer melahirkan TRF backorder ([22-retur-transfer](22-retur-transfer.md)); v0.7: baris bersumber pembelian melahirkan PRQ backorder ([26-purchase-request](26-purchase-request.md), [A-171](04-keputusan-dan-asumsi.md#a-171)); v0.8: konfirmasi & keberatan terima pemohon (BR-REQ-10) di layar REQ & portal, konfirmasi otomatis harian; notifikasi REQ perlu ditinjau ([27-pendukung-f1](27-pendukung-f1.md), [A-188](04-keputusan-dan-asumsi.md#a-188), [A-189](04-keputusan-dan-asumsi.md#a-189))
**Modul:** `request`
**Fase:** F1
**Dokumen terkait:** [Blueprint §7](01-blueprint.md#7-dokumen--alur-utama) · [Aturan Bisnis](05-aturan-bisnis.md) · [Katalog Status §2.1](06-katalog-status-dan-enum.md) · [Glosarium](03-glosarium.md) · [Model data dokumen](08b-model-data-stok-dokumen.md) · [Proses bisnis alur 1](07-proses-bisnis.md)
**Ketergantungan modul:** `master` (item, proyek, klien, alasan), `warehouse` (gudang sumber), `stock` (reservasi lunak, stok tersedia). `approval` (sejak v0.5, [20-approval](20-approval.md)), `transfer` (sejak v0.6, [22-retur-transfer](22-retur-transfer.md)); `purchase_request` (sejak v0.7, [26-purchase-request](26-purchase-request.md)).

---

## 1. Tujuan & lingkup

REQ adalah **dokumen niat**: pernyataan bahwa sejumlah material dibutuhkan sebuah proyek pada tanggal tertentu. Ia tidak memindahkan stok sebatang pun. Satu-satunya jejaknya di gudang adalah **reservasi lunak** yang lahir saat REQ disetujui — janji, bukan pergerakan.

Modul ini mengurus REQ dari dibuat sampai ditutup: pembuatan oleh pemohon internal maupun klien, peninjauan staf, pemetaan baris non-katalog, penetapan gudang sumber dan cara pemenuhan, approval, lalu penutupan setelah barang diterima. Pemenuhannya sendiri — picking, pengiriman, pembelian, transfer — dikerjakan modul lain yang membaca REQ ini.

Yang membuat modul ini rumit bukan alurnya, melainkan **klien sebagai pihak kedua**: klien menambah baris, menolak penggantian item, dan meminta pembatalan setelah disetujui. Setiap interaksi itu punya tenggat dan harus tercatat, karena yang dipersoalkan belakangan selalu "siapa menyetujui apa, kapan".

## 2. Aktor & permission

Permission modul ini disimpan dengan `module = request`.

| Role bawaan | Permission |
|---|---|
| Admin Company | semua permission modul ini |
| Manajemen | `request.view`, `request.approve` |
| Kepala Gudang | `request.view`, `request.review`, `request.approve`, `request.split_line`, `request.close_short`, `request.cancel`, `request.confirm_cancel` |
| Staf Gudang | `request.view`, `request.review`, `request.split_line`, `request.confirm_cancel` |
| Pemohon Internal | `request.view`, `request.create`, `request.submit`, `request.cancel`, `request.close_short`, `request.confirm_receipt`, `request.dispute_receipt` |
| Klien | `request.view`, `request.create`, `request.submit`, `request.cancel`, `request.add_lines`, `request.respond_substitution`, `request.request_cancel`, `request.confirm_receipt`, `request.dispute_receipt` |
| Auditor Internal & Eksternal | `request.view` |

Daftar: `request.view`, `request.create`, `request.submit`, `request.review`, `request.approve`, `request.split_line`, `request.add_lines`, `request.respond_substitution`, `request.request_cancel`, `request.confirm_cancel`, `request.close_short`, `request.cancel`, `request.confirm_receipt`, `request.dispute_receipt`.

`request.approve` didaftarkan di sini tetapi **dipakai modul `approval`**: yang berhak menyetujui ditentukan aturan approval, bukan role bawaan; approver yang ditunjuk aturan harus memegang permission ini ([A-86](04-keputusan-dan-asumsi.md#a-86)). Tanpa aturan yang cocok, REQ `pending_approval` langsung disetujui sistem ([A-08](04-keputusan-dan-asumsi.md#a-08)).

## 3. Entitas & data

### 3.1 `material_requests` — header REQ

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `number` | varchar(40) UK | `REQ/<proyek>/<yymm>/<urut>` ([BR-GEN-06](05-aturan-bisnis.md#br-gen)) |
| `project_id` | FK projects | satu REQ satu proyek ([BR-REQ-01](05-aturan-bisnis.md#br-req)) |
| `requester_id` | FK users | |
| `requester_type` | enum | `internal` \| `client` |
| `status` | enum | Katalog §2.1 |
| `required_date` | date | default tanggal dibutuhkan per baris |
| `reviewed_by`, `reviewed_at` | FK users, datetime | |
| `approval_snapshot_id` | FK approval_snapshots | snapshot pengajuan terakhir ([20-approval §3](20-approval.md#3-entitas--data)) |
| `closed_reason_id` | FK reason_codes | diisi saat `closed_short` |
| `cancel_reason_id` | FK reason_codes | diisi saat `cancelled` |
| `origin` | enum | `regular` \| `supplement` ([A-54](04-keputusan-dan-asumsi.md#a-54)) |
| `parent_request_id` | FK self | REQ induk bila `supplement` |
| `notes` | varchar(255) | |

### 3.2 `material_request_lines` — baris REQ

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint PK | |
| `material_request_id` | FK | |
| `item_id` | FK items | null selama baris non-katalog belum dipetakan |
| `non_catalog_text` | varchar(255) | teks bebas dari pemohon ([A-39](04-keputusan-dan-asumsi.md#a-39)) |
| `mapped_by`, `mapped_at` | FK users, datetime | |
| `line_ownership` | enum | `buy` \| `loan`; `loan` hanya item berserial ([BR-REQ-06](05-aturan-bisnis.md#br-req)) |
| `required_date`, `promised_date` | date | janji tampil di portal ([BR-REQ-14](05-aturan-bisnis.md#br-req)) |
| `source_warehouse_id` | FK warehouses | wajib sebelum `pending_approval` |
| `fulfillment_source` | enum | `stock` \| `transfer` \| `purchase` ([BR-REQ-05](05-aturan-bisnis.md#br-req)) |
| `qty_base` | decimal(18,4) | dalam satuan dasar item |
| `qty_reserved`, `qty_shipped`, `qty_received`, `qty_backorder` | decimal(18,4) | dipelihara modul pemenuhan |
| `nominal_length` | decimal(18,4) | item per potong: "n potongan ukuran nominal" |
| `split_from_line_id` | FK self | pemecahan antar gudang ([A-56](04-keputusan-dan-asumsi.md#a-56)) |
| `original_item_text` | varchar(255) | isi sebelum diganti ([A-55](04-keputusan-dan-asumsi.md#a-55)) |
| `substituted_at`, `substitution_deadline_at` | datetime | |
| `substitution_response` | enum | `accepted` \| `rejected` \| `expired` |
| `cancel_requested_at` | datetime | permintaan pembatalan klien ([A-61](04-keputusan-dan-asumsi.md#a-61)) |
| `cancel_reason_id` | FK reason_codes | |
| `cancel_confirmed_by`, `cancel_confirmed_at` | FK users, datetime | |
| `status` | enum | `open` \| `cancelled` \| `closed` |

### 3.3 Enum

Semua dari [Katalog Status](06-katalog-status-dan-enum.md); tidak ada nilai baru. `material_request_status`, `request_origin`, `requester_type`, `line_ownership`, `fulfillment_source`, `substitution_response`.

### 3.4 Pengaturan company

| Kunci | Bawaan | Aturan |
|---|---|---|
| `review_sla_days` | 1 hari kerja | [BR-REQ-14](05-aturan-bisnis.md#br-req) |
| `substitution_objection_days` | 1 hari | [BR-REQ-13](05-aturan-bisnis.md#br-req) |
| `receipt_confirm_days` | 3 hari | [BR-REQ-10](05-aturan-bisnis.md#br-req) |

## 4. Mesin status

Diambil apa adanya dari [Katalog Status §2.1](06-katalog-status-dan-enum.md); **tidak ada status baru**. Ringkasnya:

```
draft → submitted → under_review (klien) | pending_approval (internal)
under_review → pending_approval | rejected
pending_approval → approved | rejected | under_review (klien menambah baris)
approved → in_progress → partially_fulfilled → completed | closed_short
draft|submitted|under_review|pending_approval → cancelled
approved|in_progress → cancelled (tidak ada SJ shipped)
```

Transisi hanya lewat POST, tidak pernah lewat GET. Aksi tingkat baris tidak mengubah status dokumen.

## 5. Aturan bisnis yang berlaku

| Aturan | Ditegakkan di mana |
|---|---|
| [BR-REQ-01](05-aturan-bisnis.md#br-req) | Satu proyek per REQ; `required_date` per baris berdefault dari header |
| [BR-REQ-02](05-aturan-bisnis.md#br-req) | REQ klien masuk `under_review`; setiap perubahan baris tercatat nilai lama → baru |
| [BR-REQ-03](05-aturan-bisnis.md#br-req) | Baris non-katalog wajib dipetakan sebelum meninggalkan `under_review` |
| [BR-REQ-04](05-aturan-bisnis.md#br-req) | Gudang sumber wajib sebelum `pending_approval`; baris boleh dipecah |
| [BR-REQ-05](05-aturan-bisnis.md#br-req) | Saat `approved` tiap baris punya sumber; baris tanpa sumber menahan approval |
| [BR-REQ-06](05-aturan-bisnis.md#br-req) | `loan` hanya untuk item berserial |
| [BR-REQ-07](05-aturan-bisnis.md#br-req) | Pemohon bukan approver dokumennya sendiri |
| [BR-REQ-09](05-aturan-bisnis.md#br-req) | `closed_short` melepas reservasi dan backorder sisa |
| [BR-REQ-12](05-aturan-bisnis.md#br-req) | Klien menambah baris; setelah `approved` menjadi REQ Tambahan |
| [BR-REQ-13](05-aturan-bisnis.md#br-req) | Penggantian item punya tenggat keberatan; lewat batas = dianggap setuju |
| [BR-REQ-14](05-aturan-bisnis.md#br-req) | SLA tinjau dan tanggal janji |
| [BR-REQ-15](05-aturan-bisnis.md#br-req) | Permintaan pembatalan klien setelah `approved`, dikonfirmasi staf |
| [BR-STK-03](05-aturan-bisnis.md#br-stk) | Reservasi lunak tidak melebihi stok tersedia |
| [BR-GEN-11](05-aturan-bisnis.md#br-gen) | Tolak, batal, dan tutup-dengan-sisa selalu menuntut Alasan |
| [BR-PRJ-01](05-aturan-bisnis.md#br-prj) | REQ hanya untuk proyek aktif |

## 6. Layar

| Route | Komponen | Isi |
|---|---|---|
| `/requests` | `request.request-list` | Daftar REQ dengan penyaring status, proyek, pemohon, tanggal; penanda SLA tinjau terlampaui |
| `/requests/create` | `request.request-form` | Pembuatan REQ: proyek, tanggal dibutuhkan, baris katalog dan non-katalog |
| `/requests/{req}` | `request.request-detail` | Header, baris, timeline, dan seluruh aksi status sesuai izin |
| `/portal/requests` | `request.portal-request-list` | Daftar REQ milik klien; tanggal janji dan penanda menunggu tanggapan |
| `/portal/requests/{req}` | `request.portal-request-detail` | Klien menambah baris, menanggapi penggantian, meminta pembatalan, mengonfirmasi terima |

## 7. Kejadian stok & integrasi

REQ **tidak menulis kartu stok**. Satu-satunya sentuhannya ke modul `stock` adalah reservasi lunak lewat `ManageReservation` saat `approved`, dan pelepasannya saat `closed_short`, `cancelled`, atau baris dibatalkan.

Kejadian outbox: tidak ada yang lahir dari modul ini. `purchase_requested` lahir di modul `purchase_request` ketika REQ memicu PRQ.

## 8. Notifikasi

| Kejadian | Penerima | Kanal |
|---|---|---|
| REQ klien masuk `under_review` | Staf Gudang, Kepala Gudang | in-app |
| SLA tinjau terlampaui | Staf, Kepala Gudang | in-app, diulang harian |
| Baris diganti item lain | Klien | in-app + portal |
| REQ `approved` / `rejected` | Pemohon | in-app |
| Permintaan pembatalan baris | Staf, Kepala Gudang | in-app |
| Tanggal janji berubah | Klien | in-app |

## 9. Laporan & dashboard

| Laporan | Filter | Kolom |
|---|---|---|
| Daftar REQ | status, proyek, pemohon, rentang tanggal | nomor, proyek, pemohon, status, jumlah baris, tanggal dibutuhkan |
| REQ menunggu tinjau | umur | nomor, proyek, umur hari, SLA terlampaui |
| Baris tanpa sumber | proyek | REQ, item, jumlah, tanggal dibutuhkan |
| Penggantian item menunggu tanggapan | proyek, tenggat | REQ, item asal, item pengganti, tenggat |

## 10. Kasus uji (Given / When / Then)

| ID | Given | When | Then | BR |
|---|---|---|---|---|
| TC-REQ-01 | Proyek aktif | buat REQ dengan satu baris | status `draft`, nomor terbit | BR-GEN-06 |
| TC-REQ-02 | Proyek ditutup | buat REQ | ditolak | BR-PRJ-01 |
| TC-REQ-03 | REQ `draft` tanpa baris | submit | ditolak | BR-REQ-01 |
| TC-REQ-04 | REQ klien `draft` | submit | status `under_review` | BR-REQ-02 |
| TC-REQ-05 | REQ internal dengan gudang sumber | submit | status `pending_approval` | BR-REQ-04 |
| TC-REQ-06 | REQ internal tanpa gudang sumber | submit | tertahan di `under_review` | BR-REQ-04 |
| TC-REQ-07 | Baris non-katalog belum dipetakan | tinjau ke `pending_approval` | ditolak | BR-REQ-03 |
| TC-REQ-08 | Baris non-katalog | petakan ke item baru | item `provisional` dibuat, baris terpetakan | BR-REQ-03 |
| TC-REQ-09 | Item bukan berserial | set baris `loan` | ditolak | BR-REQ-06 |
| TC-REQ-10 | Baris 100 pcs, satu gudang punya 60 | pecah baris | dua baris, total tetap 100, `split_from_line_id` terisi | BR-REQ-04 |
| TC-REQ-11 | REQ `pending_approval`, stok tersedia cukup | setujui | status `approved`, reservasi lunak dibuat | BR-REQ-05 |
| TC-REQ-12 | Baris tanpa `fulfillment_source` | setujui | ditolak | BR-REQ-05 |
| TC-REQ-13 | Pemohon = approver | setujui | ditolak | BR-REQ-07 |
| TC-REQ-14 | REQ `approved` | batalkan tanpa alasan | ditolak | BR-GEN-11 |
| TC-REQ-15 | REQ `approved` tanpa SJ terkirim | batalkan dengan alasan | status `cancelled`, reservasi dilepas | BR-STK-05 |
| TC-REQ-16 | REQ `partially_fulfilled` | tutup dengan sisa | status `closed_short`, reservasi sisa dilepas | BR-REQ-09 |
| TC-REQ-17 | REQ klien `pending_approval` | klien menambah baris | kembali ke `under_review`, snapshot approval dibuang | BR-REQ-12 |
| TC-REQ-18 | REQ klien `approved` | klien menambah baris | REQ Tambahan `supplement` dibuat menunjuk induk | BR-REQ-12 |
| TC-REQ-19 | Staf mengganti item baris klien | klien menolak sebelum tenggat | baris `cancelled`, reservasi dilepas | BR-REQ-13 |
| TC-REQ-20 | Penggantian lewat tenggat | jalankan penuaan | `substitution_response = expired` | BR-REQ-13 |
| TC-REQ-21 | REQ `approved`, baris belum dikirim | klien minta batal, staf konfirmasi | baris `cancelled`, reservasi dilepas | BR-REQ-15 |
| TC-REQ-22 | Permintaan pembatalan ditolak staf | konfirmasi tolak | baris tetap terbuka, klien diberi tahu | BR-REQ-15 |
| TC-REQ-23 | REQ klien `under_review` lebih dari SLA | buka daftar | ditandai SLA terlampaui | BR-REQ-14 |
| TC-REQ-24 | Staf mengisi tanggal janji | simpan | tercatat di timeline, tampil di portal | BR-REQ-14 |
| TC-REQ-25 | Klien proyek lain | buka REQ | 404 | BR-ACC-05 |
| TC-REQ-26 | User tanpa `request.view` | buka daftar REQ | 403 | BR-GEN-09 |
| TC-REQ-27 | REQ 50 dipetik lalu SJ berangkat | coba batalkan REQ | baris REQ `qty_shipped` 50, `qty_reserved` 0; pembatalan ditolak | KS §2.1, BR-REQ-09 |
| TC-REQ-28 | SJ diterima baik 48, kurang 2 | lalu DSC diputus klien *tidak perlu* | REQ `partially_fulfilled` (diterima 48), lalu `completed` | KS §2.1, BR-SJ-10, A-77 |
| TC-REQ-29 | SJ diterima penuh | isi bukti terima | baris `closed`, REQ `completed` | KS §2.1, A-77 |

## 11. Di luar lingkup modul ini

Picking dan pengiriman (modul `picking`); pembuatan PRQ dan TRF dari backorder (modul `purchase_request`, `transfer`); aturan approval berlapis (modul `approval`); bukti terima dan keberatan kirim (modul `shipment`, DSC); cross-dock dari GRN ([BR-REQ-08](05-aturan-bisnis.md#br-req)); titik pesan ulang ([BR-REQ-11](05-aturan-bisnis.md#br-req), modul `purchase_request`); rencana kebutuhan material `[F2]`.

## 12. Definisi selesai

- [x] Migrasi tenant, model, dan enum untuk kedua tabel §3
- [x] Aksi domain per permission dengan validasi §5
- [x] Mesin status §4 tanpa status baru, seluruh transisi lewat POST
- [x] Reservasi lunak dibuat saat `approved` dan dilepas saat batal/tutup-dengan-sisa
- [x] Lima layar §6, menu "Permintaan", dan halaman portal klien
- [x] Penuaan tenggat penggantian dan penanda SLA tinjau
- [x] Semua TC-REQ lulus; `php83 artisan test` hijau
- [x] Dokumen ini diperbarui bila implementasi menyimpang (versi naik + changelog README)

## 13. Catatan implementasi (24 September 2026)

**Approval lewat mesin approval (v0.5, [20-approval](20-approval.md)).** `SubmitRequest` (REQ internal lengkap) dan `ReviewRequest::submitToApproval` kini memanggil `ApprovalEngine::submit()`: aturan yang cocok di-snapshot ke `approval_snapshot_id` dan tugas lapis pertama dikirim; tanpa aturan REQ langsung `approved` beserta reservasinya ([A-08](04-keputusan-dan-asumsi.md#a-08)). `ApproveRequest` tidak lagi menyetujui langsung: ia mencatat keputusan pemegang tugas pada lapis berjalan lewat `DecideApproval`; status `approved` dan reservasi lunak baru terjadi setelah lapis terakhir (`RequestApprovalHandler::onApproved`). `CancelRequest` dan `AddRequestLines` (BR-REQ-12) menghentikan snapshot yang menunggu. Tombol Setujui/Tolak di detail REQ hanya untuk pemegang tugas terbuka; panel *Riwayat approval* menampilkan lapis, tugas, dan keputusan. Uji yang dulu menyetujui langsung kini memasang aturan satu lapis (TC-REQ-05, -11–16, -17, -26d, -26e); uji modul lain mengandalkan persetujuan otomatis tanpa aturan — ID TC tidak berubah.

**TRF backorder (v0.6, [22-retur-transfer](22-retur-transfer.md)).** Baris bersumber `transfer` memakai gudang sumbernya sebagai **gudang pemenuh**. Saat REQ disetujui, `RequestApprovalHandler::onApproved` memanggil `Transfer\Support\BackorderTransfers::createFor()`: satu TRF `backorder` per pasangan gudang asal–tujuan, gudang asal dipilih sistem (induk dulu, lalu stok tersedia terbanyak); tanpa gudang asal yang cukup approval REQ tertahan BR-REQ-05 ([A-106](04-keputusan-dan-asumsi.md#a-106)). Setelah barang TRF di-put-away di gudang pemenuh, jumlahnya direservasi lunak ke baris REQ (`qty_reserved`) dan baris itu dipetik serta dikirim ke proyek seperti baris bersumber stok ([BR-REQ-08](05-aturan-bisnis.md#br-req), [A-108](04-keputusan-dan-asumsi.md#a-108)). `CancelRequest`, `CloseRequestShort`, dan `CancelRequestLine::confirm` membatalkan TRF backorder yang belum punya PCK ([BR-REQ-15](05-aturan-bisnis.md#br-req)). Detail REQ menampilkan TRF backorder-nya. Uji: TC-TRF-10–13.

**Perbaikan 24 Sep 2026 — jejak pemenuhan.** `App\Domain\Request\Support\RequestFulfillment` kini satu-satunya penulis `qty_shipped` dan `qty_received` pada baris REQ. `ShipShipment` memanggil `shipped()`, `ConfirmDelivery` memanggil `received()` (jumlah baik saja), dan `ResolveDiscrepancy` memanggil `refresh()` saat klien memutus *tidak perlu*. Status REQ diturunkan otomatis `in_progress` → `partially_fulfilled` → `completed` ([A-77](04-keputusan-dan-asumsi.md#a-77)). Sebelumnya kolom itu tak pernah terisi di alur nyata, sehingga REQ yang barangnya sudah di site masih bisa dibatalkan; ditemukan lewat E2E ([laporan progres §5.1](../00-laporan-progres-2026-09-24.md#51-pengiriman-tidak-mencatat-balik-ke-baris-req-berat)). Layar REQ dan portal menampilkan kolom *Terkirim / Diterima*. Uji TC-REQ-27–29 menjalankan rantai REQ → PCK → SJ → bukti terima tanpa mengisi kolom REQ secara manual.

Sepuluh aksi domain di `app/Domain/Request/Actions`: `SaveRequest`, `SubmitRequest`, `ReviewRequest`, `ApproveRequest`, `SplitRequestLine`, `AddRequestLines`, `RespondSubstitution`, `CancelRequest`, `CancelRequestLine`, `CloseRequestShort`. (Catatan perubahan v0.15 menyebut sebelas; yang benar sepuluh.) Tenggat keberatan penggantian dibaca dari pengaturan company `substitution_objection_days` dan SLA tinjauan dari `review_sla_days` ([11-master §13.4](11-master.md#135-sisa-pekerjaan-modul-ini)).

### 13.1 Penyimpangan dari spesifikasi

1. **Nomor REQ terbit saat dibuat, bukan saat diajukan.** Pemohon menyebut nomor itu ketika bertanya,
   dan draf tanpa nomor tidak bisa dirujuk. Segmennya kode **proyek**, bukan kode gudang seperti dokumen
   gudang, karena barisnya boleh mengambil dari beberapa gudang sekaligus.
2. **REQ internal yang belum lengkap ikut `under_review`, tidak ditolak.** Katalog Status menulis REQ
   internal langsung ke `pending_approval` bila gudang sumbernya terisi; yang belum terisi tidak
   dijelaskan nasibnya. Pemohon internal tidak selalu tahu gudang mana yang punya stok, jadi yang belum
   lengkap dikirim ke staf, bukan dikembalikan ke pemohon.
3. **Kolom `approved_at`, `approved_by`, `mapped_at`, dan `cancel_confirmed_at` ditambahkan** di luar
   ERD. Tanpa keempatnya, "siapa memutuskan apa, kapan" hanya ada di log aktivitas, padahal itu
   pertanyaan yang paling sering diadu belakangan.
4. **Baris REQ punya kolom `status` sendiri** (`open`/`closed`/`cancelled`), yang tidak ada di ERD.
   Diperlukan karena baris bisa dibatalkan sendiri-sendiri — lewat penolakan penggantian ([BR-REQ-13](05-aturan-bisnis.md#br-req))
   maupun permintaan pembatalan klien ([BR-REQ-15](05-aturan-bisnis.md#br-req)) — sementara dokumennya tetap berjalan.
5. **Tombol pembatalan baris yang ditolak staf melepas penandanya**, sehingga klien bisa mengajukan
   lagi bila keadaannya berubah. Penolakannya tetap tercatat di timeline.

### 13.2 Keputusan implementasi

1. **REQ tidak pernah menyentuh kartu stok.** Satu-satunya sentuhannya ke gudang adalah reservasi lunak
   lewat `ManageReservation`, dan itu pun hanya untuk baris bersumber `stock`. Baris bersumber transfer
   atau pembelian belum punya barang untuk dijanjikan.
2. **Penolakan buku besar diterjemahkan ke bahasa REQ.** Bila stok keburu habis antara tinjau dan
   approval, pesannya menyebut baris mana yang harus dipindahkan ke transfer atau pembelian, bukan
   sekadar "stok tidak cukup".
3. **BR-REQ-07 ditegakkan dua kali** — di policy supaya tombolnya tidak muncul, dan di kelas aksi supaya
   tetap ditolak bila dipanggil langsung.
4. ~~**`request.approve` hanya dipegang Admin Company** sampai modul `approval` ada.~~ Sejak v0.5
   `request.approve` juga dipegang Kepala Gudang dan Manajemen, dan hanya berlaku bagi approver yang
   ditugaskan aturan approval ([A-86](04-keputusan-dan-asumsi.md#a-86)).
5. **Baris tidak pernah dihapus.** Yang dilepas dari form ditandai `cancelled` (P-03), termasuk apa yang
   dulu diminta klien sebelum diganti staf.
6. **Klien di luar proyeknya mendapat 404, bukan 403**, sama seperti gudang di modul Warehouse: global
   scope pada `project_id` membuat REQ milik proyek lain tidak ditemukan sama sekali.
7. **Penuaan tenggat penggantian dibuat sebagai metode biasa** (`RespondSubstitution::expireOverdue()`),
   bukan hanya isi job, supaya bisa diuji tanpa menjalankan penjadwal.

### 13.3 Layar yang sudah ada

| Layar | Route | Komponen Livewire | Isi |
|---|---|---|---|
| Daftar REQ | `/requests` | `request.request-list` | Penyaring status, proyek, pemohon; penanda SLA tinjau terlampaui |
| Buat & ubah REQ | `/requests/create`, `/requests/{id}/edit` | `request.request-form` | Baris katalog atau teks bebas; simpan draf atau simpan-dan-ajukan |
| Detail REQ | `/requests/{id}` | `request.request-detail` | Pemetaan item, gudang sumber, tanggal janji, pecah baris, approval, tolak, batal, tutup dengan sisa, riwayat |
| Portal: daftar | `/portal/requests` | `request.portal-request-list` | Tanggal janji dan penanda baris menunggu tanggapan klien |
| Portal: detail | `/portal/requests/{id}` | `request.portal-request-detail` | Tambah baris, tanggapi penggantian, minta pembatalan baris |

Uji yang menopangnya ada di `tests/Feature/Request`: `RequestFlowTest` (TC-REQ-01–16),
`ClientInteractionTest` (TC-REQ-17–24), dan `RequestScreenTest` (TC-REQ-25–26).

### 13.4 Sisa pekerjaan modul ini

1. ~~**Approval berlapis**~~ — **selesai v0.5** lewat mesin approval ([20-approval §13.3](20-approval.md#133-integrasi-req-dan-rtv)).
2. ~~**Pembuatan PRQ otomatis**~~ untuk baris bersumber pembelian ([BR-REQ-05](05-aturan-bisnis.md#br-req)) — **selesai v0.7**
   ([26-purchase-request](26-purchase-request.md), [A-171](04-keputusan-dan-asumsi.md#a-171)); pembatalan/penutupan REQ ikut membatalkan PRQ yang
   belum diteruskan. TRF untuk baris bersumber transfer **selesai v0.6** ([22-retur-transfer](22-retur-transfer.md)).
3. **Cross-dock** ([BR-REQ-08](05-aturan-bisnis.md#br-req)) — masih saran ([A-83](04-keputusan-dan-asumsi.md#a-83)); reservasi
   backorder untuk barang TRF ([A-108](04-keputusan-dan-asumsi.md#a-108)) dan PRQ ([A-171](04-keputusan-dan-asumsi.md#a-171)) sudah ada.
4. ~~**Konfirmasi dan keberatan penerimaan**~~ ([BR-REQ-10](05-aturan-bisnis.md#br-req)) — **selesai v0.8**
   ([27-pendukung-f1](27-pendukung-f1.md), [A-188](04-keputusan-dan-asumsi.md#a-188)); [A-77](04-keputusan-dan-asumsi.md#a-77) tetap berlaku.
5. **Job penuaan tenggat penggantian dan pengingat SLA** — metodenya sudah ada, penjadwalannya menunggu
   antrean Fase 2.
6. **Notifikasi §8** — sebagian selesai v0.8 (REQ perlu ditinjau, barang diterima, keputusan approval); SLA tinjau, pengganti item, perubahan tanggal janji belum.
7. ~~Laporan §9 beserta ekspor Excel~~ — **selesai 25 Sep 2026**: *Daftar REQ*, *REQ menunggu tinjau*, *Baris tanpa sumber*, *Penggantian item menunggu tanggapan* di [16-shared-laporan-berkas §3.2](16-shared-laporan-berkas.md#32-definisi-laporan-terdaftar-reportsdefinitions) ([A-232](04-keputusan-dan-asumsi.md#a-232)).
