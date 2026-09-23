# Dokumentasi WMS Proyek (SaaS Multi-Company)

**Versi:** 0.13
**Tanggal:** 24 September 2026
**Status:** Part 1; **Part 4 berjalan** — **modul Access, Master, dan Warehouse selesai untuk Fase 1** (192 uji hijau); audit menyeluruh 24 Sep 2026 menutup empat cacat kritis, lihat [laporan audit](00-laporan-audit-2026-09-24.md). **Kode aplikasi ada di repo ini** (`app/`, `routes/`, `resources/`). Modul berikutnya: Stock. D-01–D-29 berlaku (peta rilis: WMS → Purchasing inti → WhatsApp → PWA offline → SSO); **A-01–A-49 dan A-51–A-66 disetujui 23 Sep 2026** (A-40 diubah, A-16 diganti A-42); **A-50 menunggu validasi**; Part 2 & 3 v0.4 (final, A-50 tertunda). Laporan: [00-laporan-validasi-2026-09-23.md](00-laporan-validasi-2026-09-23.md) · [00-laporan-audit-dokumentasi.md](00-laporan-audit-dokumentasi.md)

Dokumentasi ini adalah acuan tunggal untuk membangun WMS baru dari nol. Prototipe lama (`warehouse.sipembantu.com`) hanya referensi; indeks temuan auditnya ada di [00-audit](00-audit/README.md).

---

## Struktur folder

```
docs/
├── README.md                              ← dokumen ini
├── 00-laporan-audit-dokumentasi.md        ← audit v0.2 → v0.3: temuan, tindakan, asumsi baru
├── 00-laporan-validasi-2026-09-23.md      ← validasi A-25–A-49 (v0.4): hasil, file berubah, audit ringan, A-50
├── 00-laporan-audit-2026-09-24.md         ← audit menyeluruh dokumentasi, kode, infrastruktur; modul Warehouse; A-67, A-68
├── 00-checklist-persiapan.md              ← checklist pemilik produk sebelum Part 4 & coding; peta rilis D-29; halaman login; WhatsApp
├── 00-akun-uji.md                         ← SATU-SATUNYA daftar akun seed/demo (Super Admin, company DEMO, 14 akun tenant, org, aturan approval demo)
├── 00-audit/README.md                     ← indeks ID temuan prototipe (BUG/UX/SEC/T) yang dirujuk
├── wms/
│   ├── 01-blueprint.md                    ← gambaran produk, konsep inti, dokumen, fase rilis
│   ├── 02-riset-wms-sejenis.md            ← riset produk sejenis & keputusan adopsi
│   ├── 03-glosarium.md                    ← istilah UI ↔ nama di kode (wajib)
│   ├── 04-keputusan-dan-asumsi.md         ← D-xx, A-xx, O-xx, matriks ketertelusuran
│   ├── 05-aturan-bisnis.md                ← BR-xx, relasi antar dokumen, matriks kejadian stok
│   ├── 06-katalog-status-dan-enum.md      ← satu-satunya sumber status & enum
│   ├── 07-proses-bisnis.md                ← Part 2: alur 1–3 (teks per lane, tabel langkah, Mermaid) — DIBUAT OTOMATIS
│   ├── 07a-proses-bisnis-lanjutan.md      ← Part 2: alur 4–7 — DIBUAT OTOMATIS
│   ├── 07b-proses-bisnis-pendukung.md     ← Part 2: alur 8–10 — DIBUAT OTOMATIS
│   ├── 08-arsitektur.md                   ← Part 3: arsitektur, keputusan AD-xx, verifikasi paket, antrean, NFR
│   ├── 08a-model-data-inti.md             ← Part 3: ERD pusat, akses, master, gudang — DIBUAT OTOMATIS
│   ├── 08b-model-data-stok-dokumen.md     ← Part 3: ERD stok, outbound, inbound — DIBUAT OTOMATIS
│   ├── 08c-model-data-pendukung.md        ← Part 3: ERD konversi/aset, opname, approval, umum — DIBUAT OTOMATIS
│   ├── 10-access.md                       ← Part 4: modul Access (auth, user, role × cakupan, organisasi, perangkat, akses dukungan) — v0.4, §13 status & catatan implementasi
│   ├── 11-master.md                       ← Part 4: modul Master (klien, proyek, vendor, item, satuan, referensi) — v0.2, §13 status & catatan implementasi
│   ├── 12-warehouse.md                    ← Part 4: modul Warehouse (gudang, zona, rak, level, bin) — v0.3, §13 status & catatan implementasi
│   ├── 13-stock.md                        ← Part 4: modul Stock (kartu stok, saldo, reservasi, kejadian) — v0.2, §13 status & catatan implementasi
│   ├── 14-request.md                      ← Part 4: modul Request (REQ permintaan material, portal klien) — v0.2, §13 status & catatan implementasi
│   ├── 15-picking-shipment.md             ← Part 4: modul Picking & Shipment (PCK, SJ, bukti terima, DSC) — v0.2, §13 status & catatan implementasi
│   └── _template-spesifikasi-modul.md     ← template Part 4
├── prompts/                               ← paket prompt Part 6 per modul, plus prompt audit lama
├── akuntansi/01-lingkup-dan-integrasi-wms.md
├── purchasing/01-lingkup-dan-integrasi-wms.md
└── diagram/
    ├── _generate.py                       ← SUMBER data alur Part 2; `py -3 docs/diagram/_generate.py` menulis ulang 07* dan bpmn-*.drawio
    ├── _generate_erd.py                   ← SUMBER model data Part 3; `py -3 docs/diagram/_generate_erd.py` menulis ulang 08a–c dan erd-*.drawio
    ├── _verify.py                         ← `py -3 docs/diagram/_verify.py`: cek link & anchor, ID terdefinisi/ganda, batas 450 baris
    ├── bpmn-01…10-*.drawio                ← BPMN (diagrams.net) — DIBUAT OTOMATIS, jangan diedit manual
    └── erd-*.drawio                       ← ERD per area — DIBUAT OTOMATIS, jangan diedit manual
../CLAUDE.md                               ← konteks untuk agen AI (urutan baca, larangan)
```

## Rencana part

| Part | Isi | Keluaran | Status |
|---|---|---|---|
| 1 | Blueprint, riset, glosarium, keputusan & asumsi, aturan bisnis, katalog status | `wms/01`–`06`, `akuntansi/01`, `purchasing/01` | **Selesai** — tiap berkas punya versinya sendiri (blueprint 0.7, glosarium 0.6, keputusan 0.8, aturan 0.9, katalog 0.6). A-25–A-49 divalidasi dan A-51–A-66 disetujui 23 Sep 2026; [A-50](wms/04-keputusan-dan-asumsi.md#a-50), [A-67](wms/04-keputusan-dan-asumsi.md#a-67), [A-68](wms/04-keputusan-dan-asumsi.md#a-68) menunggu validasi |
| 2 | Proses bisnis to-be (BPMN 2.0) | `diagram/bpmn-*.drawio` + `wms/07`, `07a`, `07b` (teks per lane + tabel langkah + Mermaid), dari satu sumber `diagram/_generate.py` | **v0.4** — final; alur 1/2/7/8 diperluas (A-51–A-66, BR-OPN-09–10); alur 5 memuat varian transfer dalam proyek (A-50) |
| 3 | Arsitektur & ERD (DB pusat + DB per company), verifikasi paket | `wms/08-arsitektur.md` + `08a`–`08c` (Mermaid `erDiagram`) + `diagram/erd-*.drawio`, dari `diagram/_generate_erd.py` | **Arsitektur v0.6, model data v0.5** — 112 tabel, 15 keputusan AD-xx, paket terverifikasi (O-01 ✔); 24 Sep 2026 diselaraskan dengan implementasi (`projects.site_warehouse_id` dihapus per A-40, kolom implementasi modul Master & Warehouse masuk) |
| 4 | Spesifikasi modul BE (per modul, dari template) | `wms/10-…` s.d. `wms/2x-…` | **Berjalan** — [10-access](wms/10-access.md) v0.4, [11-master](wms/11-master.md) v0.2, [12-warehouse](wms/12-warehouse.md) v0.3, [13-stock](wms/13-stock.md) v0.2, [14-request](wms/14-request.md) v0.2, dan [15-picking-shipment](wms/15-picking-shipment.md) v0.2 **selesai Fase 1**; berikutnya modul Receipt/Putaway. Pola per modul: spesifikasi → prompt → kode |
| 5 | FE landing page (gaya indonesia.travel) | `wms/30-landing-page.md` | — |
| 6 | Paket prompt Claude Code (per modul) | `prompts/*.md` | **Berjalan** — [prompts/10-access.md](prompts/10-access.md), [prompts/11-master.md](prompts/11-master.md), [prompts/12-warehouse.md](prompts/12-warehouse.md), [prompts/13-stock.md](prompts/13-stock.md), [prompts/14-request.md](prompts/14-request.md), [prompts/15-picking-shipment.md](prompts/15-picking-shipment.md) |

## Jalur baca per audiens

| Audiens | Baca | Waktu |
|---|---|---|
| **Pemilik produk** (validasi) | [Checklist persiapan](00-checklist-persiapan.md) → [Laporan validasi](00-laporan-validasi-2026-09-23.md) → [A-50](wms/04-keputusan-dan-asumsi.md#a-50) → isu [O-12, O-13, O-15](wms/04-keputusan-dan-asumsi.md#3-isu-terbuka) → isi [00-audit](00-audit/README.md) | ±15 menit |
| **Analis** (Part 2–3) | [Blueprint](wms/01-blueprint.md) → [Aturan Bisnis](wms/05-aturan-bisnis.md) → [Katalog Status](wms/06-katalog-status-dan-enum.md) → [Akuntansi](akuntansi/01-lingkup-dan-integrasi-wms.md), [Purchasing](purchasing/01-lingkup-dan-integrasi-wms.md) | ±2 jam |
| **Developer** (Part 4+) | [Glosarium](wms/03-glosarium.md) → [Katalog Status](wms/06-katalog-status-dan-enum.md) → [Aturan Bisnis](wms/05-aturan-bisnis.md) → spesifikasi modul terkait → Blueprint bagian terkait | per modul |
| **Agen AI** | [`../CLAUDE.md`](../CLAUDE.md) lalu urutan yang sama dengan developer | — |

## Konvensi

- Bahasa dokumen & UI: **Bahasa Indonesia** (siap i18n). Nama di kode: Inggris, sesuai glosarium.
- Format tanggal tampilan `23 Sep 2026`; simpan ISO `2026-09-23`, waktu UTC.
- **Skema ID:** `D-xx` keputusan produk · `A-xx` asumsi · `O-xx` isu terbuka · `P-xx` prinsip · `NFR-xx` non-fungsional · `BR-<AREA>-nn` aturan bisnis · `AD-xx` keputusan arsitektur (Part 3) · `TC-<MOD>-nn` kasus uji (Part 4).
- **Tautan:** selalu link Markdown relatif beranchor, mis. `[A-30](wms/04-keputusan-dan-asumsi.md#a-30)`. Baris D/A/O punya anchor `<a id="…">` per ID.
- **Tag fase** `[F1]` `[F2]` `[F3]` pada kebutuhan; tanpa tag = F1.
- Kepala setiap file: Versi · Tanggal · Status · Dokumen terkait. Setiap perubahan menaikkan versi dan menambah baris di catatan perubahan **dengan menyebut ID yang berubah**.
- Keputusan tidak diedit; buat keputusan baru dan isi *Diganti oleh*. Perubahan substantif yang belum disetujui = asumsi baru berstatus *Perlu validasi*.
- Satu file ≤ ±450 baris.

## Catatan perubahan

### v0.16 — 24 September 2026 (modul Picking & Shipment selesai Fase 1)
- **`wms/15-picking-shipment.md` v0.2 (selesai Fase 1):** sepuluh tabel, tujuh aksi domain, enam layar, dua belas permission; 44 uji TC-PCK, TC-SJ, dan TC-DSC.
- **Tidak ada aturan baru:** BR-SJ-01 s.d. BR-SJ-10 sudah lengkap sejak Part 2 dan kini ditegakkan di kode. Status PCK, SJ, dan DSC diambil apa adanya dari Katalog Status §2.2–§2.4.
- **Buku besar stok diperluas dua kali** (`13-stock.md` tidak berubah versinya karena kontraknya tetap): `MovementRequest::$fromStockStatus` membolehkan bin asal sama dengan bin tujuan bila kondisi stoknya berubah — bentuk yang dituntut BR-SJ-10 dan akan dipakai QC; `StockLedger::emitEvent()` menerbitkan kejadian tanpa pergerakan, yang dituntut BR-SJ-04 saat barang diterima gudang tujuan tetapi masih milik gudang asal.
- **Efek `delivered` per tujuan dan kepemilikan** (BR-SJ-04) ditegakkan: ke gudang tetap *Dalam Perjalanan*, jual putus keluar ledger, aset pindah ke bin On-site proyek.
- **Kurang dan rusak tidak hilang dari pembukuan** (BR-SJ-10): keduanya tetap tercatat milik gudang asal sampai DSC diselesaikan; rusak berkondisi `damaged` sejak bukti terima.
- **Kolom baru di luar ERD** dicatat di §13.1 dan digenerate ulang ke `08a`–`08c`: `number` pada `pick_tasks`, `shipments`, dan `delivery_discrepancies`; `shipments.destination_vendor_id`; berkas tanda tangan, foto, dan foto kerusakan sebagai path ([A-68](wms/04-keputusan-dan-asumsi.md#a-68)) karena tabel `attachments` belum ada.
- **Dua belas permission baru** modul `picking` dan `shipment`. Kepala Gudang tidak memegang `shipment.confirm_delivery`: bukti terima diisi driver atau penerima, bukan yang mengirim.
- **Pengujian:** 274 → **318 uji / 1.796 asersi**, semua hijau.

### v0.15 — 24 September 2026 (modul Request selesai Fase 1)
- **`wms/14-request.md` v0.2 (selesai Fase 1):** dua tabel REQ, sebelas aksi domain, lima layar termasuk dua halaman portal klien, empat belas permission; 36 uji TC-REQ-01–26.
- **Tidak ada aturan baru:** BR-REQ-01 s.d. BR-REQ-15 sudah lengkap sejak Part 2 dan kini ditegakkan di kode. Status diambil apa adanya dari Katalog Status §2.1.
- **REQ tidak menyentuh kartu stok.** Satu-satunya sentuhannya ke gudang adalah reservasi lunak lewat `ManageReservation` saat `approved` (BR-REQ-05), dan pelepasannya saat `cancelled`, `closed_short`, penolakan penggantian (BR-REQ-13), atau pembatalan baris yang dikonfirmasi staf (BR-REQ-15).
- **Klien sebagai pihak kedua:** tambahan setelah `approved` melahirkan REQ Tambahan bernomor sendiri (BR-REQ-12); diam sampai tenggat penggantian dianggap setuju (BR-REQ-13); pembatalan baris dua langkah (BR-REQ-15).
- **Kolom baru di luar ERD** dicatat di §13.1 dan digenerate ulang ke `08a`–`08c`: `material_requests.number`, `approved_by`, `approved_at`, `cancel_reason_id`; `material_request_lines.mapped_at`, `cancel_confirmed_at`, dan `status` per baris.
- **`request.approve` hanya dipegang Admin Company** sampai modul `approval` ada ([BR-GEN-10](wms/05-aturan-bisnis.md#br-gen)); Kepala Gudang meninjau, bukan menyetujui.
- **Pengujian:** 238 → **274 uji / 1.353 asersi**, semua hijau.

### v0.14 — 24 September 2026 (modul Stock selesai Fase 1)
- **`wms/13-stock.md` v0.2 (selesai Fase 1):** lima tabel area Stok, lima layar, `StockLedger` sebagai satu-satunya pintu tulis saldo (P-01, AD-04), reservasi lunak dan keras, outbox kejadian, penguncian periode, penomoran dokumen; 46 uji TC-STK-01–33.
- **Aturan baru BR-LED-01 s.d. BR-LED-06** (`05-aturan-bisnis.md` → v0.10): pergerakan wajib punya asal atau tujuan, arah ditentukan pasangan bin bukan tanda bilangan, pelacakan wajib disebut sesuai mode item, satu serial hanya di satu bin, koreksi lewat baris pembalik sekali saja, dan outbox ditulis dalam transaksi yang sama.
- **Asumsi baru A-71** (`04-keputusan-dan-asumsi.md` → v0.10): pelepasan reservasi manual sebagai katup darurat, karena BR-STK-05 dan BR-STK-16 menulis pelepasan hanya lewat aksi dokumen sedangkan modul dokumennya belum lengkap di Fase 1. **Disetujui**; tidak ada lagi asumsi berstatus *Perlu validasi*.
- **BR-GEN-04 akhirnya ditegakkan:** `StockGuard` menolak menonaktifkan gudang, bin, atau item yang masih bersaldo atau punya reservasi aktif — sisa pekerjaan yang tercatat di `12-warehouse` §13.4 nomor 2 (`12-warehouse.md` → v0.3).
- **P-01 dijaga dua lapis:** selain `booted()` di model, `stock_movements` punya trigger `BEFORE UPDATE` dan `BEFORE DELETE`. Kunci unik saldo memakai kolom turunan `COALESCE(x, 0)` supaya NULL tidak meloloskan baris kembar.
- **Lima permission baru** modul `stock`: `stock.view`, `stock.lock_period`, `reservation.view`, `reservation.release`, `stock_event.view`. Tidak ada `stock.post` — memposting stok adalah akibat dokumen, izinnya melekat pada aksi dokumen.
- **Generator ERD diselaraskan** (`08a`–`08c` → v0.6): `stock_movements` ditambah `document_number` dan `notes` serta kunci unik `reverses_movement_id`, kunci unik `stock_balances` memakai kolom turunan, `stock_reservations` ditambah `released_at`, dan `document_sequences` memakai nama kolom yang benar-benar dipakai (`segment`, `last_number`).
- **Pengujian:** 192 → **238 uji / 1.160 asersi**, semua hijau.

### v0.13 — 24 September 2026 (audit menyeluruh + modul Warehouse)
- **Audit menyeluruh** dokumentasi, kode, dan infrastruktur; hasilnya di [00-laporan-audit-2026-09-24.md](00-laporan-audit-2026-09-24.md). **Empat cacat kritis ditutup:** aksi Livewire yang berjalan di luar middleware tenant (melanggar BR-SUB-02, BR-SUB-03, BR-PRJ-07), dua form yang bisa dipakai menaikkan hak sendiri menjadi Admin Company (BR-GEN-09), dan seeder produksi yang membuat company demo beserta databasenya.
- **`wms/12-warehouse.md` v0.2 (selesai Fase 1):** enam tabel area Gudang & lokasi, empat layar, delapan aksi domain, seeder gudang demo, 28 uji TC-WH-01–20.
- **Aturan baru BR-WH-01 s.d. BR-WH-07** (`05-aturan-bisnis.md` → v0.9): kode bin hierarkis dan terkunci, bin bawaan dan bin virtual otomatis, bin on-site per proyek, Gudang Site wajib proyek, hierarki tidak melingkar, kapasitas peringatan atau blokir, gudang dan bin dinonaktifkan bukan dihapus.
- **Asumsi baru** `04-keputusan-dan-asumsi.md` → v0.8: **A-67** kolom `bins.count_flag` (istilahnya sudah ada di glosarium dan dipakai BR-SJ-02 tetapi kolomnya belum pernah ada) dan **A-68** penyimpanan berkas di disk lokal per company sampai O-14 diputuskan. Keduanya **menunggu validasi**.
- **BR-ACC-05 akhirnya ditegakkan:** trait pembatas cakupan yang sejak modul Access tidak dipakai satu model pun kini dipasang di `Warehouse` dan `Bin`; arti "cakupan kosong" yang sebelumnya berbeda di tiga tempat disatukan.
- **Kode mati dihidupkan:** pengaturan verifikasi dua langkah di profil (QR + kode pemulihan), route masuk Super Admin, tujuh laporan Access §9 / Master §9 / Warehouse §9 dengan ekspor Excel, serta unggah tanda tangan dan foto item.
- **Paket dipasang** sesuai AD-08 dan AD-09: `maatwebsite/excel` 4.0.3, `bacon/bacon-qr-code` 3.1.1, `barryvdh/laravel-dompdf` 3.1.2, `picqer/php-barcode-generator` 3.3.0.
- **Generator ERD diselaraskan:** `projects.site_warehouse_id` dihapus (A-40), ditambah `item_uom_conversions.is_active`, `is_active` pada zona/rak/level/tipe gudang, serta `bins.count_flag` dan `bins.freeze_reason`; 08a–08c dan seluruh `.drawio` dibuat ulang.
- **Antarmuka:** 72 tautan mati berbahasa Inggris dari template dibuang dari bundel produksi dan diganti menu sungguhan yang disaring izin; menu "Master data" yang tersembunyi bagi pemegang `item_category.view` diperbaiki.
- **Pengujian:** 119 → **192 uji / 997 asersi**, semua hijau; delapan uji unit pertama, dan uji HTTP yang menembak endpoint Livewire sungguhan.

### v0.12 — 23 September 2026 (modul Master selesai Fase 1)
- **`wms/11-master.md` → v0.2 (selesai Fase 1):** §13 baru berisi penyimpangan implementasi, keputusan implementasi, daftar sembilan layar, dan sisa pekerjaan. Definisi selesai §12 dicentang.
- **BR-MST-01 s.d. BR-MST-05 ditegakkan di kode:** kode master huruf besar dan terkunci (`MasterCode`), satuan dasar terkunci setelah ada lot/serial/potongan, kategori satuan wajib punya satuan acuan berfaktor 1, Proyek Internal tanpa klien dan tidak bisa ditutup, master hanya bisa dinonaktifkan bila tidak dipakai data aktif.
- **Matriks kombinasi pelacakan** (BR-STK-08, BR-STK-09, BR-STK-11, BR-STK-12, BR-CNV-03) dipusatkan di `TrackingCombination`, dipakai form item sebagai petunjuk dan `SaveItem` sebagai penjaga.
- **Sembilan layar §6:** klien, proyek (dengan dialog ubah status §4), vendor, daftar item, form item, detail item bertab, kategori item, satuan, dan data referensi bertab. Menu "Master data" ditambahkan ke sidebar.
- **Tiga belas aksi domain** dan tujuh policy; 24 permission modul `master` ditambahkan ke seeder referensi beserta pembagiannya per role bawaan (§2).
- **Seeder:** `MasterReferenceSeeder` (kategori & satuan standar, alasan baku delapan konteks, kategori penyimpanan, saklar fitur P-08) dan `MasterDemoSeeder` (klien, proyek, vendor, dan empat item yang mewakili empat mode pelacakan) sesuai [00-akun-uji](00-akun-uji.md) §2. `DemoSeeder` tidak lagi memakai id klien/proyek sementara.
- **Cakupan proyek pada penugasan role** kini memakai daftar nama proyek, bukan id angka (menutup satu butir sisa pekerjaan `wms/10-access.md` §13.4).
- **Penyimpangan tercatat:** `projects.site_warehouse_id` tidak dibuat (A-40); kode kategori satuan huruf besar; konversi kemasan per item boleh lintas kategori satuan (BR-STK-09); `company_settings` dan `feature_settings` mencatat audit manual.
- **Pengujian:** 82 → **119 uji / 697 asersi**, semua hijau (TC-MST-01–23 di `tests/Feature/Master`).

### v0.11 — 23 September 2026 (struktur organisasi, perangkat, akses dukungan)
- **Layar §6.5 Struktur organisasi:** pohon unit dengan tambah, ubah, sub-unit, dan penonaktifan; penjagaan agar induk tidak menjadi dirinya sendiri atau turunannya; jabatan per unit beserta level; daftar user di unit lengkap dengan jabatan dan atasan langsung (D-16, dasar aturan approval Blueprint §8.1).
- **Layar §6.6 Perangkat:** daftar perangkat PWA dengan pencarian dan filter status; pemegang `device.view` melihat seluruh company, user lain hanya miliknya; cabut dan aktifkan kembali tanpa menghapus data (P-03).
- **Layar §6.7 Akses dukungan:** Admin Company memberi izin berperiode kepada Super Admin dengan *Alasan* `*`, batas maksimum 7 hari, daftar izin yang berlaku, tombol cabut, dan riwayat (A-27, BR-SUB-04).
- **Aksi domain baru:** `SaveOrgUnit`, `DeactivateOrgUnit`, `SavePosition`, `RevokeDevice`; unit maupun jabatan yang masih dipakai user aktif tidak bisa dinonaktifkan.
- **Perbaikan:** `device.manage` tanpa `device.view` kini hanya berlaku untuk perangkat sendiri; waktu akses dukungan disimpan UTC meski diisi memakai zona waktu company (BR-GEN-07).
- **Pengujian:** 60 → **82 uji / 542 asersi**, semua hijau (TC-ACC-UI-30–37, 40–45, 50–54 baru). `wms/10-access.md` → v0.4 (status selesai Fase 1, §13.4 sisa pekerjaan).

### v0.10 — 23 September 2026 (layar Pengguna & Role)
- **Layar §6.3 Pengguna:** daftar dengan pencarian dan filter role/status/unit/cakupan; form tambah-ubah dengan penugasan role × cakupan berulang; detail bertab (Ringkasan, Penugasan Role, Perangkat, Riwayat). Aksi: undang ulang, kirim tautan atur ulang password, nonaktifkan (*Alasan* `*` + *Keterangan* opsional, BR-GEN-11), aktifkan kembali.
- **Layar §6.4 Role:** daftar role dengan jumlah permission & penugasan; form dengan matriks permission per modul, salin dari role lain, dan penonaktifan role buatan company. Role bawaan tetap tidak bisa dinonaktifkan.
- **Aksi domain baru:** `CreateUser`, `UpdateUser`, `ReactivateUser`, `SendPasswordReset`, `SaveRole`, `DeactivateRole`; menegakkan BR-ACC-01 (wajib punya penugasan), BR-ACC-02 (Admin Company terakhir), BR-ACC-03 (role Klien eksklusif), BR-ACC-04 (cakupan `all`).
- **Menu Administrasi** di sidebar tampil sesuai permission; pesan aksi memakai event Livewire + Alpine.
- **Pengujian:** 40 → **60 uji / 414 asersi**, semua hijau (TC-ACC-UI-01–11 dan TC-ACC-UI-20–27 baru). `wms/10-access.md` → v0.3 (§13.3 daftar layar yang sudah ada, §13.4 sisa pekerjaan).

### v0.9 — 23 September 2026 (kerangka aplikasi & modul Access terbangun)
- **Kode aplikasi masuk repo:** Laravel **13.33** di atas **PHP 8.3.33** (Laragon), MySQL **8.4.3**. Paket: `stancl/tenancy` 3.10.1 (AD-01), `spatie/laravel-permission` 8.3.0 (AD-06), `spatie/laravel-activitylog` **4.12.3** (AD-07; 5.x butuh PHP 8.4), `livewire/livewire` 4.4.6. Front-end di-bundle Vite tanpa CDN (Bootstrap 5.3 + NexaDash dari `template/`, font Inter di-host sendiri).
- **Tenancy jalan:** company = tenant, identifikasi lewat `companies.subdomain`, database per company (`wms_tenant_<kode>`); migrasi pusat 2 berkas, migrasi tenant 3 berkas; `wms_tenant_demo` terbentuk dari seeder.
- **Modul Access:** login lokal, undangan, lupa/atur ulang password, 2FA TOTP (ditulis sendiri), kunci akun, profil, portal klien, gerbang langganan, akses dukungan; domain `app/Domain/{Platform,Access}`; halaman error bermerek (NFR-01). **40 uji / 285 asersi hijau** (TC-ACC).
- **Dokumen:** `wms/10-access.md` → v0.2 (§13 catatan implementasi & penyimpangan), `wms/08-arsitektur.md` → v0.6 (§9 paket terpasang), `prompts/10-access.md` baru, `00-akun-uji.md` disesuaikan (klien2 → proyek PRJ-002).
- **Belum dikerjakan di modul ini:** layar pengelolaan Pengguna/Role/Organisasi/Perangkat/Akses Dukungan (Livewire), unggah tanda tangan, pengaturan 2FA di profil, laporan §9 — tercatat di 10-access §13.3.

### v0.8 — 23 September 2026 (mulai Part 4)
- **Lingkungan:** Laragon, PHP **8.3.33** (`php83`), MySQL **8.4 LTS** standar dev & produksi, Composer 2.8, Node 22 — CLAUDE.md dan 08-arsitektur §10 (v0.5) diperbarui dari XAMPP.
- **Baru:** `00-akun-uji.md` (akun seed/demo satu berkas: Super Admin, company DEMO, 14 akun tenant, organisasi, aturan approval demo); `wms/10-access.md` spesifikasi modul Access v0.1 (27 kasus uji TC-ACC).
- **Aturan v0.7:** bagian baru `BR-ACC` (BR-ACC-01–06) untuk login, Admin terakhir, role Klien eksklusif, cakupan `all`, global scope, sesi & password.

### v0.7 — 23 September 2026 (peta rilis & persiapan)
- **D-29** peta rilis: Fase 1 WMS inti → 1b Purchasing inti → 2a WhatsApp → 2b PWA offline → 3 SSO & Purchasing lengkap; login lokal dulu (D-26) ditegaskan. **Baru:** `00-checklist-persiapan.md` (keputusan O-12/O-13/O-15/A-50, bahan & akses, pemetaan halaman auth template, checklist WhatsApp Cloud API, urutan mulai).
- O-03 target → awal Fase 2a; O-12/O-13/O-15 merujuk checklist. Blueprint §13, §18, §20; purchasing/01 v0.3 (§4 inti vs lengkap); 08-arsitektur v0.4 (§10 template `template/`, §12 langkah 4). Template NexaDash tersedia di `template/` (referensi UI, tidak di-deploy).

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
