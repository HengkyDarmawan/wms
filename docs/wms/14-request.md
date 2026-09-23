# Spesifikasi Modul — `request` (Permintaan Material)

**Versi:** 0.2
**Tanggal:** 24 September 2026
**Status:** terimplementasi (Fase 1) — modul kelima setelah [Stock](13-stock.md)
**Modul:** `request`
**Fase:** F1
**Dokumen terkait:** [Blueprint §7](01-blueprint.md#7-dokumen--alur-utama) · [Aturan Bisnis](05-aturan-bisnis.md) · [Katalog Status §2.1](06-katalog-status-dan-enum.md) · [Glosarium](03-glosarium.md) · [Model data dokumen](08b-model-data-stok-dokumen.md) · [Proses bisnis alur 1](07-proses-bisnis.md)
**Ketergantungan modul:** `master` (item, proyek, klien, alasan), `warehouse` (gudang sumber), `stock` (reservasi lunak, stok tersedia). Modul `approval`, `picking`, `purchase_request`, dan `transfer` belum ada; titik sambungnya dibuat sebagai stub sesuai [BR-GEN-10](05-aturan-bisnis.md#br-gen).

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
| Manajemen | `request.view` |
| Kepala Gudang | `request.view`, `request.review`, `request.split_line`, `request.close_short`, `request.cancel`, `request.confirm_cancel` |
| Staf Gudang | `request.view`, `request.review`, `request.split_line`, `request.confirm_cancel` |
| Pemohon Internal | `request.view`, `request.create`, `request.submit`, `request.cancel`, `request.close_short`, `request.confirm_receipt`, `request.dispute_receipt` |
| Klien | `request.view`, `request.create`, `request.submit`, `request.cancel`, `request.add_lines`, `request.respond_substitution`, `request.request_cancel`, `request.confirm_receipt`, `request.dispute_receipt` |
| Auditor Internal & Eksternal | `request.view` |

Daftar: `request.view`, `request.create`, `request.submit`, `request.review`, `request.approve`, `request.split_line`, `request.add_lines`, `request.respond_substitution`, `request.request_cancel`, `request.confirm_cancel`, `request.close_short`, `request.cancel`, `request.confirm_receipt`, `request.dispute_receipt`.

`request.approve` didaftarkan di sini tetapi **dipakai modul `approval`**: yang berhak menyetujui ditentukan aturan approval, bukan role bawaan ([A-08](04-keputusan-dan-asumsi.md#a-08)). Selama modul itu belum ada, REQ `pending_approval` disetujui otomatis oleh sistem, persis seperti bunyi Katalog Status §2.1 untuk company tanpa aturan approval.

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
| `approval_snapshot_id` | bigint | stub sampai modul `approval` ada |
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
4. **`request.approve` hanya dipegang Admin Company** sampai modul `approval` ada. Kepala Gudang
   meninjau, bukan menyetujui ([BR-GEN-10](05-aturan-bisnis.md#br-gen)).
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

1. **Approval berlapis** ([A-08](04-keputusan-dan-asumsi.md#a-08)) — menunggu modul `approval`; sekarang
   REQ disetujui langsung oleh pemegang `request.approve`.
2. **Pembuatan TRF dan PRQ otomatis** untuk baris bersumber transfer dan pembelian ([BR-REQ-05](05-aturan-bisnis.md#br-req)) —
   menunggu modul `transfer` dan `purchase_request`.
3. **Cross-dock dan reservasi backorder** ([BR-REQ-08](05-aturan-bisnis.md#br-req)) — menunggu modul
   `receipt`.
4. **Konfirmasi dan keberatan penerimaan** ([BR-REQ-10](05-aturan-bisnis.md#br-req)) — permissionnya
   sudah ada, layarnya menunggu modul `shipment` dan DSC.
5. **Job penuaan tenggat penggantian dan pengingat SLA** — metodenya sudah ada, penjadwalannya menunggu
   antrean Fase 2.
6. **Notifikasi §8** — menunggu modul notifikasi.
7. **Laporan §9** beserta ekspor Excel — dibangun bersama laporan modul lain.
