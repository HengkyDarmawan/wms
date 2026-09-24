# Dokumentasi WMS Proyek (SaaS Multi-Company)

**Versi:** 0.30
**Tanggal:** 24 September 2026
**Status:** Part 1–3 selesai; **Part 4 berjalan** — modul **Access, Master, Warehouse, Stock, Request, Picking/Shipment, Receipt/Putaway, Approval, Count/Adjustment, Return/Transfer, Template dokumen & label, dan Issue (pemakaian material di site) selesai untuk Fase 1** (493 uji hijau di MariaDB 10.4). **Kode aplikasi ada di repo ini** (`app/`, `routes/`, `resources/`). Modul berikutnya: Conversion/Waste. D-01–D-29 berlaku (peta rilis: WMS → Purchasing inti → WhatsApp → PWA offline → SSO); **A-01–A-71 disetujui**; **[A-72–A-76](wms/04-keputusan-dan-asumsi.md#25-baru-dari-pencocokan-dokumen-dengan-kode--24-sep-2026) dan [A-77–A-116](wms/04-keputusan-dan-asumsi.md#26-baru-dari-pembangunan-modul-lanjutan--24-sep-2026), [A-120–A-126](wms/04-keputusan-dan-asumsi.md#27-baru-dari-modul-template-dokumen--label--24-sep-2026), [A-117–A-119 dan A-150–A-152](wms/04-keputusan-dan-asumsi.md#28-baru-dari-modul-issue-pemakaian-material-di-site--24-sep-2026) menunggu validasi** (lahir dari pencocokan dokumen dengan kode dan dari pembangunan modul). Menjalankan aplikasi: [00-setup-lokal.md](00-setup-lokal.md) · Progres: [00-laporan-progres-2026-09-24.md](00-laporan-progres-2026-09-24.md) · Laporan: [00-laporan-audit-2026-09-24.md](00-laporan-audit-2026-09-24.md)

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
├── 00-akun-uji.md                         ← SATU-SATUNYA daftar akun seed/demo (Super Admin, company DEMO, 14 akun tenant, org, stok awal, aturan approval demo)
├── 00-laporan-progres-2026-09-24.md     ← hasil E2E skenario §5, cakupan TC 178/178, peta progres Fase 1, bug pengiriman ↔ REQ
├── 00-setup-lokal.md                      ← instalasi & menjalankan aplikasi: profil rumah (Laragon) dan kantor (XAMPP + MariaDB), hosts, seed, skenario uji manual
├── 00-audit/README.md                     ← indeks ID temuan prototipe (BUG/UX/SEC/T) yang dirujuk
├── 00-audit/alur-proses.html              ← bacaan cepat: 10 alur BPMN sebagai diagram alir sederhana — DIBUAT OTOMATIS dari diagram/_generate_alur_html.py
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
│   ├── 10-access.md                       ← Part 4: modul Access (auth, user, role × cakupan, organisasi, perangkat, akses dukungan) — v0.5, §13 status & catatan implementasi
│   ├── 11-master.md                       ← Part 4: modul Master (klien, proyek, vendor, item, satuan, referensi) — v0.3, §13 status & catatan implementasi
│   ├── 12-warehouse.md                    ← Part 4: modul Warehouse (gudang, zona, rak, level, bin) — v0.4, §13 status & catatan implementasi
│   ├── 13-stock.md                        ← Part 4: modul Stock (kartu stok, saldo, reservasi, kejadian) — v0.3, §13 status & catatan implementasi
│   ├── 14-request.md                      ← Part 4: modul Request (REQ permintaan material, portal klien) — v0.5, §13 status & catatan implementasi
│   ├── 15-picking-shipment.md             ← Part 4: modul Picking & Shipment (PCK, SJ, bukti terima, DSC) — v0.3, §13 status & catatan implementasi
│   ├── 16-shared-laporan-berkas.md        ← Part 4: kerangka laporan bersama + ekspor Excel, 7 laporan, unggah berkas, aset global — v0.2 (mendokumentasikan kode yang sudah ada)
│   ├── 17-platform-login.md               ← Part 4: Platform Fase 1 — login Super Admin, gerbang langganan, seeder pusat — v0.1
│   ├── 18-template-dokumen-label.md       ← Part 4: modul Template dokumen & label (layout induk, cetak PDF dokumen, label barcode/QR) — v0.1, §13 catatan implementasi
│   ├── 19-receipt-putaway.md              ← Part 4: modul Receipt/Putaway (GRN vendor & transfer, QC, PUT, RTV) — v0.4, §13 status & catatan implementasi
│   ├── 20-approval.md                     ← Part 4: mesin approval bersama (aturan, lapis, SoD, delegasi, eskalasi, simulasi; REQ, RTV, ADJ, OPN tersambung) — v0.3, §13 status & catatan implementasi
│   ├── 21-opname-penyesuaian.md           ← Part 4: modul Count/Adjustment (sesi opname, hitung buta, hitung ulang, approval sesi, ADJ manual & pembalik) — v0.2, §13 status & catatan implementasi
│   ├── 22-retur-transfer.md               ← Part 4: modul Transfer/Return (TRF antar gudang/proyek/titik, TRF backorder REQ, RET dari proyek, SJ balik, GRN retur, pemilahan) — v0.2, §13 status & catatan implementasi
│   ├── 23-pemakaian.md                    ← Part 4: modul Issue (ISU pemakaian material di Gudang Site, ISU pembalik dengan approval, laporan Material per Proyek, cetak Bukti Pemakaian) — v0.2, §13 catatan implementasi
│   └── _template-spesifikasi-modul.md     ← template Part 4
├── prompts/                               ← paket prompt Part 6 per modul, plus prompt audit lama dan 00-lanjutkan-di-rumah.md (serah terima kantor → rumah)
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
| 1 | Blueprint, riset, glosarium, keputusan & asumsi, aturan bisnis, katalog status | `wms/01`–`06`, `akuntansi/01`, `purchasing/01` | **Selesai** — tiap berkas punya versinya sendiri (blueprint 0.7, glosarium 0.6, keputusan 0.13, aturan 0.10, katalog 0.8). A-25–A-49 divalidasi dan A-51–A-66 disetujui 23 Sep 2026; A-50–A-71 disetujui 24 Sep 2026; [A-72–A-84](wms/04-keputusan-dan-asumsi.md#25-baru-dari-pencocokan-dokumen-dengan-kode--24-sep-2026) menunggu validasi |
| 2 | Proses bisnis to-be (BPMN 2.0) | `diagram/bpmn-*.drawio` + `wms/07`, `07a`, `07b` (teks per lane + tabel langkah + Mermaid), dari satu sumber `diagram/_generate.py` | **v0.4** — final; alur 1/2/7/8 diperluas (A-51–A-66, BR-OPN-09–10); alur 5 memuat varian transfer dalam proyek (A-50) |
| 3 | Arsitektur & ERD (DB pusat + DB per company), verifikasi paket | `wms/08-arsitektur.md` + `08a`–`08c` (Mermaid `erDiagram`) + `diagram/erd-*.drawio`, dari `diagram/_generate_erd.py` | **Arsitektur v0.11, model data v0.12** — 114 tabel, 15 keputusan AD-xx, paket terverifikasi (O-01 ✔); 24 Sep 2026 diselaraskan dengan implementasi (`projects.site_warehouse_id` dihapus per A-40, kolom implementasi modul Master s.d. Issue masuk) |
| 4 | Spesifikasi modul BE (per modul, dari template) | `wms/10-…` s.d. `wms/2x-…` | **Berjalan** — [10-access](wms/10-access.md) v0.5, [11-master](wms/11-master.md) v0.3, [12-warehouse](wms/12-warehouse.md) v0.4, [13-stock](wms/13-stock.md) v0.3, [14-request](wms/14-request.md) v0.5, [15-picking-shipment](wms/15-picking-shipment.md) v0.4, [19-receipt-putaway](wms/19-receipt-putaway.md) v0.4, [20-approval](wms/20-approval.md) v0.5, [21-opname-penyesuaian](wms/21-opname-penyesuaian.md) v0.2, [22-retur-transfer](wms/22-retur-transfer.md) v0.2, [18-template-dokumen-label](wms/18-template-dokumen-label.md) v0.2, dan [23-pemakaian](wms/23-pemakaian.md) v0.2 **selesai Fase 1**; [16-shared-laporan-berkas](wms/16-shared-laporan-berkas.md) dan [17-platform-login](wms/17-platform-login.md) v0.1 mendokumentasikan kode bersama yang dibangun tanpa spesifikasi (16 v0.3 menambah laporan Material per Proyek); berikutnya modul Conversion/Waste. Pola per modul: spesifikasi → prompt → kode |
| 5 | FE landing page (gaya indonesia.travel) | `wms/30-landing-page.md` | — |
| 6 | Paket prompt Claude Code (per modul) | `prompts/*.md` | **Berjalan** — [prompts/10-access.md](prompts/10-access.md), [prompts/11-master.md](prompts/11-master.md), [prompts/12-warehouse.md](prompts/12-warehouse.md), [prompts/13-stock.md](prompts/13-stock.md), [prompts/14-request.md](prompts/14-request.md), [prompts/15-picking-shipment.md](prompts/15-picking-shipment.md), [prompts/16-shared.md](prompts/16-shared.md), [prompts/17-platform-login.md](prompts/17-platform-login.md), [prompts/18-template-label.md](prompts/18-template-label.md). Prompt 13–15 menyebut dirinya "Part 7–9"; itu nomor sesi, bukan Part rencana ini |

## Jalur baca per audiens

| Audiens | Baca | Waktu |
|---|---|---|
| **Pemilik produk** (validasi) | [Checklist persiapan](00-checklist-persiapan.md) → [Laporan validasi](00-laporan-validasi-2026-09-23.md) → [A-72–A-76](wms/04-keputusan-dan-asumsi.md#25-baru-dari-pencocokan-dokumen-dengan-kode--24-sep-2026) → isu [O-12, O-13, O-15](wms/04-keputusan-dan-asumsi.md#3-isu-terbuka) → isi [00-audit](00-audit/README.md) | ±15 menit |
| **Analis** (Part 2–3) | [Blueprint](wms/01-blueprint.md) → [Aturan Bisnis](wms/05-aturan-bisnis.md) → [Katalog Status](wms/06-katalog-status-dan-enum.md) → [Akuntansi](akuntansi/01-lingkup-dan-integrasi-wms.md), [Purchasing](purchasing/01-lingkup-dan-integrasi-wms.md) | ±2 jam |
| **Developer** (Part 4+) | [Setup lokal](00-setup-lokal.md) → [Glosarium](wms/03-glosarium.md) → [Katalog Status](wms/06-katalog-status-dan-enum.md) → [Aturan Bisnis](wms/05-aturan-bisnis.md) → spesifikasi modul terkait → Blueprint bagian terkait | per modul |
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

### v0.30 — 24 September 2026 (serah terima kantor → rumah)
- **Berkas baru [prompts/00-lanjutkan-di-rumah.md](prompts/00-lanjutkan-di-rumah.md):** keadaan saat serah terima, langkah setup Laragon + MySQL 8.4 setelah pull, cara kerja per modul, jatah nomor (asumsi berikutnya A-153; A-120–A-149 milik modul Template), dan urutan sisa: Konversi/Waste (branch `wip/konversi-waste`, setengah jadi), Aset, Purchase Request, Platform penuh, pendukung F1, penutup.
- **Uji browser masuk repo:** `tests/e2e/ui-check.mjs` dan `tests/e2e/alur-req-sj.mjs` (langkah approval kini oleh Kepala Gudang CKG sesuai aturan demo 20-approval), dapat diatur lewat `WMS_BASE`, `CHROME_PATH`, `MYSQL_BIN`.
- **`.gitignore`:** `/storage/tenant*/` (disk per company, termasuk artefak uji yang sempat ter-commit) dan keluaran E2E.
- **Pengujian:** 493 uji hijau (MariaDB 10.4); alur E2E 9/9 lulus.

### v0.29 — 24 September 2026 (modul Issue — pemakaian material di site — selesai Fase 1)
- **Berkas baru `wms/23-pemakaian.md` v0.2 (selesai Fase 1).** Domain `app/Domain/Issue`, migrasi tenant `000120`.
  - ISU dari bin penyimpanan Gudang Site proyek, hanya item habis pakai; aset ditolak (BR-PRJ-08, BR-STK-08).
  - Konfirmasi = barang keluar lewat `StockLedger` dengan kejadian `material_consumed` penanda proyek (matriks §14); stok diperiksa ulang saat konfirmasi.
  - ISU pembalik: baris terpilih, jumlah negatif, Alasan `*`, approval minimal satu lapis (BR-GEN-04), `StockLedger::reverse()` + `reverses_event_id`, sekali per baris (BR-LED-05).
  - Laporan **Material per proyek** (diminta, terkirim, terpakai, diretur, di Gudang Site, aset di proyek) dari kartu stok, terdaftar di `ReportRegistry`.
  - Cetak **Bukti Pemakaian Material** lewat modul Template; menu *Pemakaian di site* dan palet Ctrl+K.
- **Asumsi baru [A-117](wms/04-keputusan-dan-asumsi.md#a-117)–[A-119](wms/04-keputusan-dan-asumsi.md#a-119), [A-150](wms/04-keputusan-dan-asumsi.md#a-150)–[A-152](wms/04-keputusan-dan-asumsi.md#a-152)** (*Perlu validasi*, `04` → v0.19, §2.8).
  - A-117: sumber & bentuk baris ISU (bin penyimpanan, potongan utuh, serial per unit, draf tidak memegang stok).
  - A-118: kolom di luar ERD.
  - A-119: permission `issue.view`/`issue.approve` & cakupan gudang + proyek.
  - A-150: ISU pembalik tanpa status baru (tetap `draft` selama approval; ditolak tetap `draft`), lapis minimum Kepala Gudang Site → Manajemen.
  - A-151: definisi kolom laporan Material per proyek.
  - A-152: cetak Bukti Pemakaian Material.
- **Permission baru** modul `issue` (5): `issue.view`, `issue.create`, `issue.confirm`, `issue.cancel`, `issue.approve`. Staf Gudang tanpa `approve`; Pemohon Internal `view/create/cancel`; Manajemen `view/approve`; Auditor `view`.
- **Katalog** `06` → v0.13: `document_template_type` + `material_issue`, ISU pembalik tersambung ke mesin approval, catatan implementasi §2.9 — **tanpa status baru**.
- **Model data** 08a–08c → v0.12 (digenerate ulang): kolom implementasi `material_issues`, `material_issue_lines` (A-118).
- **Modul lain:** `20-approval` → v0.5 (ISU pembalik tersambung), `18-template-dokumen-label` → v0.2 (dokumen ISU), `16-shared-laporan-berkas` → v0.3 (laporan kedelapan), `08-arsitektur` → v0.11 (§12), `00-akun-uji` → v1.6 (§5 lapis minimum ISU pembalik, tanpa aturan demo), laporan progres → v1.7 (ISU ✅).
- **Uji:** 17 uji TC-ISU (termasuk rantai REQ → SJ ke Gudang Site → GRN → PUT → ISU → laporan). TC-ACC-27b menghitung 5 permission `issue`, TC-RPT-01 menghitung 8 laporan; pemeriksaan "tanpa nilai uang" TC-TPL-01 kini membuang gambar base64 dulu (QR bisa memuat "Rp" secara acak). **493 uji hijau.**

### v0.28 — 24 September 2026 (modul Template dokumen & label selesai Fase 1)
- **Berkas baru `wms/18-template-dokumen-label.md` v0.1 (selesai Fase 1)** dan `prompts/18-template-label.md`. Dibangun paralel di branch `feat/template-label`, lalu digabung setelah v0.27.
  - Domain `app/Domain/Template`.
  - Layout induk per company: kop, logo, warna, footer, blok tanda tangan. Layar *Administrasi → Layout dokumen*.
  - Cetak PDF dari tombol **Cetak** di detail dokumen: SJ, bukti terima, picklist PCK, BA selisih DSC, surat retur RTV, BA penyesuaian ADJ. Laporan opname memakai kop yang sama.
  - Label Code128 + QR untuk bin, item, lot, potongan, dalam dua kertas (thermal 50×30 mm, A4 3×8). Layar *Cetak label*.
  - Editor template dan template AST/WST berupa stub (BR-GEN-10).
- **Asumsi baru [A-120](wms/04-keputusan-dan-asumsi.md#a-120)–[A-126](wms/04-keputusan-dan-asumsi.md#a-126)** (*Perlu validasi*, `04` → v0.18, §2.7). A-120–A-149 dicadangkan untuk modul ini.
  - A-120: ukuran label sementara sampai O-09; batas 200 label per cetak karena memori/waktu.
  - A-121: isi barcode/QR.
  - A-122: template F1 = Blade bawaan, kop teks biasa.
  - A-123: kolom di luar ERD.
  - A-124: cetak memakai izin `view`.
  - A-125: blok tanda tangan.
  - A-126: tanda air "DIBATALKAN", tanpa log cetak.
- **Permission baru** modul `template` (2): `document_layout.manage` (Admin Company) dan `label.print` (Admin, Kepala Gudang, Staf Gudang).
- **Katalog** `06` → v0.12: enum `document_template_type` dan `paper_size`, **tanpa status baru**.
- **Glosarium** `03` → v0.8: Layout Induk, Template Dokumen, Label, Blok Tanda Tangan.
- **Model data** 08c digenerate ulang: `document_layouts.logo_path`, `document_templates.paper` varchar(20). Migrasi tenant `000200`.
- **Modul lain:**
  - `12-warehouse` → v0.6: label bin selesai.
  - `19-receipt-putaway` → v0.6: cetak RTV selesai.
  - Laporan progres → v1.6: Template ✅.
- **Uji:** 15 uji TC-TPL. `phpunit.xml` diberi `memory_limit` 512M karena render PDF dalam satu proses suite melewati 128 MB. **476 uji hijau.**

### v0.27 — 24 September 2026 (modul Transfer & Retur selesai Fase 1)
- **Berkas baru `wms/22-retur-transfer.md` v0.2 (selesai Fase 1):** domain `app/Domain/Transfer` dan `app/Domain/Return` (Arsitektur §4). **TRF** antar gudang, antar proyek, dan antar titik dalam proyek (jalur ringan [A-50](wms/04-keputusan-dan-asumsi.md#a-50), [BR-RET-02](wms/05-aturan-bisnis.md#br-ret)); TRF otomatis dari baris REQ bersumber transfer saat REQ disetujui ([BR-REQ-05](wms/05-aturan-bisnis.md#br-req)); disetujui → reservasi lunak gudang asal + PCK otomatis → SJ → bukti terima → GRN transfer → `completed`; barang TRF backorder direservasi ke REQ penunggu setelah put-away lalu dipetik dan dikirim ke proyek ([BR-REQ-08](wms/05-aturan-bisnis.md#br-req)). **RET** dari stok Gudang Site, aset On-site, barang terkirim ke klien (retur penjualan, [BR-RET-03](wms/05-aturan-bisnis.md#br-ret)), dan barang rusak ditinggal ekspedisi ([BR-RET-05](wms/05-aturan-bisnis.md#br-ret)); SJ balik dari Gudang Site; GRN retur ke bin Retur; pemilahan layak/rusak/offcut/waste dengan `goods_returned`/`asset_returned` ([BR-RET-04](wms/05-aturan-bisnis.md#br-ret), matriks §14). Enam layar (RET juga di portal klien `/portal/returns`), menu *Transfer & retur* + palet. Migrasi `2026_01_01_000110_create_transfer_return_tables`. Uji TC-TRF-01–16 dan TC-RET-01–17 (33 uji); **461 uji hijau**.
- **Asumsi baru [A-106](wms/04-keputusan-dan-asumsi.md#a-106)–[A-116](wms/04-keputusan-dan-asumsi.md#a-116)** (*Perlu validasi*, `04` → v0.17): TRF backorder & pemilihan gudang asal (A-106), siklus TRF & PCK otomatis (A-107), reservasi ke REQ saat put-away (A-108), permission & cakupan (A-109), asal baris RET (A-110), SJ balik hanya stok Gudang Site (A-111), GRN retur tanpa kejadian (A-112), pemilahan bertahap & offcut (A-113), DSC kembali ke gudang tanpa GRN retur (A-114), kolom di luar ERD (A-115), aset tanpa modul Aset (A-116).
- **Sembilan permission baru** modul `transfer` (4: `transfer.view`, `transfer.create`, `transfer.approve`, `transfer.cancel`) dan `return` (5: `return.view`, `return.create`, `return.approve`, `return.sort`, `return.cancel`); Pemohon Internal dan Klien memegang `return.view/create/cancel`.
- **Katalog** `06` → v0.11: enum `transfer_origin`, `return_ownership`; `receipt_type = return` aktif; TRF & RET tersambung ke mesin approval — **tanpa status baru**. **Model data** 08a–08c → v0.11 (digenerate ulang): kolom implementasi `transfers`, `transfer_lines`, `goods_returns`, `goods_return_lines` (A-115); `goods_receipts.goods_return_id` dan `goods_receipt_lines.goods_return_line_id` ber-FK.
- **Modul lain:** `14-request` → v0.6 (§13 TRF backorder, §13.4 no. 2–3), `15-picking-shipment` → v0.5 (§13.1 no. 8–10: PCK/SJ untuk TRF & RET, `unshippedQty` tanpa SJ batal, relasi lintas cakupan, A-114), `19-receipt-putaway` → v0.5 (GRN retur, penyelesaian TRF; TC-GRN-12 disesuaikan, ID tetap), `20-approval` → v0.4 (§2, §3.9, §6, §13.4 no. 1), `08-arsitektur` → v0.10 (§4, §12), `00-akun-uji` → v1.5 (§5: TRF/RET tanpa aturan demo), laporan progres → v1.5 (*Retur & transfer* ✅), `cek.md`.

### v0.26 — 24 September 2026 (modul Count/Adjustment selesai Fase 1)
- **Berkas baru `wms/21-opname-penyesuaian.md` v0.2 (selesai Fase 1):** domain `app/Domain/Count` dan `app/Domain/Adjustment` (Arsitektur §4). OPN: sesi bulanan/tahunan/ad-hoc/pemeriksaan mendadak per gudang/zona/bin/item, pembekuan bin dan snapshot saldo fisik (BR-OPN-01, BR-OPN-02), hitung buta di halaman ramah HP, toleransi relatif **dan** absolut (BR-OPN-04), hitung ulang otomatis oleh penghitung berbeda (BR-OPN-05), akar masalah selisih besar (BR-OPN-07), approval tingkat sesi lewat mesin approval dengan SoD penghitung (BR-OPN-06, BR-OPN-09), satu ADJ per gudang diposting lewat `StockLedger` dengan kejadian `stock_adjusted` + `count_session_ref`, bin berpenanda hitung didahulukan (A-67), sesi bulanan memajukan kunci periode (BR-STK-15). ADJ manual ± per bin/lot/serial/potongan, selalu minimal satu lapis approval (A-09), ADJ pembalik sekali saja (BR-GEN-03, BR-LED-05). Delapan layar (dua ramah HP), laporan PDF sesi, menu *Opname & penyesuaian* + palet. Migrasi `2026_01_01_000100_create_count_adjustment_tables`. Uji TC-OPN-01–19 dan TC-ADJ-01–11 (36 uji); **428 uji hijau**.
- **Asumsi baru [A-95](wms/04-keputusan-dan-asumsi.md#a-95)–[A-105](wms/04-keputusan-dan-asumsi.md#a-105)** (*Perlu validasi*, `04` → v0.16): permission di luar Katalog (A-95), approval sesi minimal satu lapis & SoD (A-96), penolakan tetap `reconciling` (A-97), DSC `adjusted` tanpa ADJ (A-98), klasifikasi & hitung ulang (A-99), cakupan & snapshot (A-100), penutupan & kunci periode (A-101), baris ADJ & pembalik (A-102), batas hitung buta (A-103), kolom di luar ERD (A-104), aturan demo ADJ/OPN (A-105).
- **Dua belas permission baru** modul `count` (8: `count.view`, `count.create`, `count.start`, `count.assign`, `count.record`, `count.reconcile`, `count.approve`, `count.cancel`) dan `adjustment` (4: `adjustment.view`, `adjustment.create`, `adjustment.approve`, `adjustment.cancel`); Auditor Internal memegang `count.approve` sehingga melihat *Tugas approval saya* (TC-APR-20 disesuaikan, ID tetap).
- **Katalog** `06` → v0.10: enum `count_assignment_status`, `adjustment_origin`; ADJ & OPN tersambung ke mesin approval — **tanpa status baru**. **Model data** 08a–08c → v0.10 (digenerate ulang): kolom implementasi `stock_counts`, `count_assignments`, `count_lines`, `stock_adjustments`, `stock_adjustment_lines` (A-104); `bins.frozen_by_count_id` ber-FK (TC-WH-09 memakai sesi sungguhan).
- **Dokumen lain:** `20-approval` → v0.3 (§2, §3.9, §13.4 no. 1; TC-APR-17 memakai `waste_disposal` sebagai jenis belum tersambung, TC-APR-21 enam aturan), `13-stock` → v0.5 (§13.1 no. 7–8: kunci periode dari OPN bulanan, `StockLedger::reverse()` untuk dokumen pembalik), `12-warehouse` → v0.5 (§13.1 no. 3), `08-arsitektur` → v0.9 (§4, §12), `00-akun-uji` → v1.4 (§5 aturan ADJ & OPN diseed), laporan progres → v1.4 (*Stock opname* ✅), `cek.md`.

### v0.25 — 24 September 2026 (modul Approval selesai Fase 1)
- **Berkas baru `wms/20-approval.md` v0.2 (selesai Fase 1):** mesin approval bersama di `app/Domain/Approval` dengan kontrak `ApprovalHandler` + `ApprovalRegistry` (D-28); aturan per jenis dokumen tanpa nilai uang (BR-APR-07), lapis dan cara putus, snapshot saat diajukan (BR-APR-01), SoD (BR-APR-03), orang sama di lapis berurutan (BR-APR-04), delegasi (BR-APR-05), eskalasi terjadwal `approval:escalate` + manual (BR-APR-06, BR-APR-08), keputusan pertama menang (BR-APR-09), simulasi (BR-APR-11), tanpa aturan = disetujui otomatis (A-08); WhatsApp dan token stub Fase 2a (BR-APR-10). Lima layar (Tugas approval saya, Aturan approval, form + simulasi draf, Delegasi, Simulasi), panel *Riwayat approval* di REQ/RTV, menu *Approval* + palet. Migrasi `2026_01_01_000090_create_approval_tables`. Uji TC-APR-01–22 (29 uji); **392 uji hijau**.
- **REQ dan RTV lewat mesin:** `14-request` → v0.5 (§2, §3.1, §13, §13.2 no. 4, §13.4 no. 1), `19-receipt-putaway` → v0.4 (§2, §4, §5, §13). TC-REQ-05, -11–16, -17, -26d, -26e dan TC-RTV-01, -03–07, -09 memasang aturan satu lapis; ID tidak berubah.
- **Asumsi baru [A-86](wms/04-keputusan-dan-asumsi.md#a-86)–[A-94](wms/04-keputusan-dan-asumsi.md#a-94)** (*Perlu validasi*, `04` → v0.15): permission approve per dokumen (A-86), pencocokan & "kategori Aset" = kepemilikan aset (A-87), resolusi approver saat diajukan (A-88), delegasi (A-89), eskalasi (A-90), notifikasi stub (A-91), lokasi domain vs `packages/approval` (A-92), RTV tanpa aturan otomatis — bagian approval [A-80](wms/04-keputusan-dan-asumsi.md#a-80) diganti (A-93), kolom di luar ERD (A-94).
- **Permission baru** modul `approval` (5): `approval_rule.view`, `approval_rule.manage`, `approval.simulate`, `approval.delegate`, `approval.escalate`; `request.approve` ke Kepala Gudang & Manajemen, `vendor_return.approve` ke Manajemen (A-86).
- **Katalog** `06` → v0.9: enum `approval_document_type`, `approver_type`, `decision_mode`, `approval_snapshot_status`, `approval_task_status`, `condition_match` (tanpa status dokumen baru). **Glosarium** `03` → v0.7: Tugas Approval, Cara Putus, Jenis Approver, Approver Cadangan, Simulasi Aturan.
- **Model data** 08a–08c → v0.9 (digenerate ulang): `approval_snapshots.document_number/rule_name/context`, waktu `datetime(6)`; `approval_delegations.notes`; `vendor_returns.approval_snapshot_id`; `material_requests.approval_snapshot_id` ber-FK. `08-arsitektur` → v0.8 (§4 keadaan kode mesin approval, §12).
- **Data demo** `00-akun-uji` → v1.3: §5 aturan REQ dan RTV **sudah diseed** (`ApprovalDemoSeeder`); ADJ/PRQ/OPN menunggu modulnya. Laporan progres → v1.3 (Approval engine ✅). `00-setup-lokal` → v1.4: skenario §5 langkah 3 disetujui Kepala Gudang CKG lewat *Tugas approval saya* (+ langkah 3b simulasi).

### v0.24 — 24 September 2026 (stok tersedia)
- **Asumsi baru [A-85](wms/04-keputusan-dan-asumsi.md#a-85)** (*Perlu validasi*, `04` → v0.14): Stok Tersedia hanya dari bin `storage`, sama dengan sumber picking (A-84); menutup celah `19-receipt-putaway` §13.3 no. 5. `13-stock` → v0.4, `19-receipt-putaway` → v0.3; TC-GRN-11b disesuaikan (ditolak di approval, BR-REQ-05). 363 uji hijau.

### v0.23 — 24 September 2026 (halaman baca alur proses)
- **Berkas baru `00-audit/alur-proses.html`:** menampilkan alur 1–10 sebagai diagram alir Mermaid sederhana. Warna kotak menandai pelaku, dan setiap alur punya tabel langkah berisi status/aturan. Isinya dibuat oleh generator baru `diagram/_generate_alur_html.py` dari `FLOWS` di `_generate.py` (data tidak diketik ulang). **Tidak ada ID yang berubah**, dan `bpmn-*.drawio` serta `07*` tidak disentuh. `00-audit/README.md` → v0.2.

### v0.22 — 24 September 2026 (modul Receipt/Putaway selesai Fase 1)
- **Berkas baru `wms/19-receipt-putaway.md` v0.2 (selesai Fase 1):** GRN vendor manual dan GRN transfer masuk, QC per baris, PUT dengan saran bin, RTV; dua belas aksi (satu per permission), delapan layar, menu *Penerimaan*; 39 uji TC-GRN-01–21, TC-PUT-01–08, TC-RTV-01–09.
- **Lima belas permission baru** modul `receipt` (6), `putaway` (3), `vendor_return` (6), termasuk `receipt.qc`. Pembagian role mengikuti kolom *Aktor* Katalog: Staf Gudang tanpa `receipt.cancel`, `putaway.cancel`, `vendor_return.approve`.
- **Asumsi baru [A-78](wms/04-keputusan-dan-asumsi.md#a-78)–[A-84](wms/04-keputusan-dan-asumsi.md#a-84)** (*Perlu validasi*, `04` → v0.13, §2.6): efek QC pada stok, QC dua lapis, `receipt.qc` + approval RTV tanpa modul approval, `stock_transferred` saat GRN transfer, GRN transfer setelah bukti terima, cross-dock hanya saran, aturan saran bin.
- **Katalog Status v0.8:** enum `receipt_type` didaftarkan; catatan §2.5 menyebut `receipt.qc`. **Tidak ada status baru.**
- **Model data v0.8** (`_generate_erd.py` → `08a`–`08c`): `number` pada `goods_receipts`, `putaway_tasks`, `vendor_returns`; `goods_receipts.received_by`; isian draf `lot_no`, `expiry_date`, `serial_no`, `piece_length`, `qc_reason_id`, `notes` pada `goods_receipt_lines`; `from_bin_id`, `override_reason` pada `putaway_task_lines`; `submitted_by`, `approved_by`, `approved_at`, `reject_reason_id` pada `vendor_returns`; `bin_id`, `stock_status` pada `vendor_return_lines`.
- **`15-picking-shipment.md` → v0.4:** §13.1 no. 7 — `CreatePickTask` hanya mengalokasikan dari bin `storage` (A-84); sebelumnya stok di Dalam Perjalanan ikut dipetik.
- **Laporan progres v1.2:** baris *Penerimaan (GRN), QC, put-away, RTV* ✅. **Pengujian:** 324 → **363 uji / 2.240 asersi**, hijau di MariaDB 10.4.

### v0.21 — 24 September 2026 (perbaikan pengiriman → REQ)
- **Bug laporan progres §5.1 diperbaiki:** `RequestFulfillment` mencatat `qty_shipped` saat SJ berangkat dan `qty_received` saat bukti terima, lalu menurunkan status REQ `partially_fulfilled`/`completed`. Pembatalan REQ yang sudah dikirim kini ditolak BR-REQ-09.
- **`14-request.md` → v0.4:** TC-REQ-27, TC-REQ-28, TC-REQ-29 (uji alur penuh); layar REQ dan portal punya kolom *Terkirim / Diterima*.
- **Asumsi baru [A-77](wms/04-keputusan-dan-asumsi.md#a-77)** (*Perlu validasi*, `04` → v0.12, §2.6): REQ selesai tanpa menunggu konfirmasi pemohon sampai layar BR-REQ-10 ada.

### v0.20 — 24 September 2026 (E2E dan peta progres)
- **Berkas baru `00-laporan-progres-2026-09-24.md`:** delapan skenario [00-setup-lokal §5](00-setup-lokal.md#5-skenario-uji-manual) dijalankan di Chrome headless (9/9 lulus); 178/178 TC spesifikasi 10–17 punya uji dan lulus; Fase 1 = 5 selesai, 6 sebagian, 12 belum dari 23 butir Blueprint §18.
- **Bug berat ditemukan, belum diperbaiki:** pengiriman tidak memperbarui `material_request_lines.qty_shipped/qty_received`, sehingga REQ yang barangnya sudah diterima bisa dibatalkan (melanggar BR-REQ-09) dan REQ tidak pernah *completed*. Lihat laporan progres §5.1.
- **`00-setup-lokal.md` → v1.3:** tabel §5 mengikuti alur sebenarnya (langkah tinjau 2b oleh Kepala Gudang).

### v0.19 — 24 September 2026 (perilaku template NexaDash)
- **Perbaikan front-end, tanpa perubahan ID bisnis:** (1) `resources/js/globals.js` baru memasang jQuery, Bootstrap, dan SimpleBar ke `window` dan diimpor paling awal. Sebelumnya `nexadash/app.js` berjalan sebelum `window.jQuery` diisi (impor ES dievaluasi lebih dulu) lalu gagal, sehingga toggle sidebar, tema, palet ⌘K, dan tooltip mati. (2) SimpleBar dipasang lokal (`simplebar` di `package.json`, tanpa CDN) dengan cadangan `overflow-y` supaya sidebar bisa di-scroll. (3) Tombol tampilkan password (`.btn-toggle-pw`) diport dari `template/assets/js/pages/auth.js` ke `resources/js/wms/ui.js` dengan delegasi event. (4) `layouts/app.blade.php` diberi `data-nx-layout="app"` dan `#nxSidebarBackdrop`. Pencocokan menu aktif di `nexadash/app.js` disesuaikan untuk rute Laravel.
- **Diverifikasi di Chrome headless:** 27 halaman menu tanpa error console; scroll sidebar, tombol mata, toggle sidebar, tema, ⌘K, dropdown pengguna, dan sidebar HP berfungsi. `00-setup-lokal.md` → v1.2.

### v0.18 — 24 September 2026 (tampilan halaman company tanpa gaya)
- **Perbaikan:** `config/tenancy.php` `asset_helper_tenancy` → `false`. Saat aktif, stancl/tenancy mengarahkan `asset()` dan `@vite` di halaman company ke `/tenancy/assets/…` (storage company), sehingga CSS/JS/logo 404 dan seluruh back-office dan portal tampil berantakan. Halaman pusat tidak kena. Berkas company tetap lewat route berotorisasi ([A-68](wms/04-keputusan-dan-asumsi.md#a-68)).
- **`wms/16-shared-laporan-berkas.md` → v0.2:** uji baru TC-FIL-02, TC-FIL-02b; §13 mencatat alasannya. **`00-setup-lokal.md` → v1.1:** baris pemecahan masalah.
- **Pengujian:** 319 → **321 uji**, hijau.

### v0.17 — 24 September 2026 (pencocokan dokumen dengan kode + setup mesin kantor)
- **Asumsi baru A-72 s.d. A-76, semuanya *Perlu validasi*** (`04-keputusan-dan-asumsi.md` → v0.11, §2.5): A-72 stok awal demo lewat seeder; A-73 satu kelas aksi memegang beberapa transisi (`ProcessPickTask`, `ShipShipment`, `GrantSupportAccess`); A-74 kolom konvensi `created_by`/`updated_by`, `submitted_at`, `line_no`, `uom_id`, `qty_input`, `proofs_of_delivery.disputed_at`/`device_id` belum ada di kode; A-75 `audit_logs` memakai skema spatie; A-76 dev kantor di MariaDB 10.4.
- **Spesifikasi baru untuk kode yang dibangun tanpa spesifikasi:** `wms/16-shared-laporan-berkas.md` v0.1 (kerangka laporan, tujuh laporan, `StoreUpload`; TC-RPT-01, TC-FIL-01) dan `wms/17-platform-login.md` v0.1 (login Super Admin, `EnsureSubscriptionState`, seeder pusat; TC-PLT-01, TC-PLT-02), plus `prompts/16-shared.md` dan `prompts/17-platform-login.md`. §13 keduanya mencatat dua belas selisih kode dengan aturan, antara lain BR-SUB-03 (`terminated` lebih longgar), BR-SUB-01/P-03 (`TenantDeleted` menghapus database), dan laporan tanpa ekspor PDF (Blueprint §6.9a).
- **Model data v0.7** (`_generate_erd.py` → `08a`–`08c`): tabel `login_attempts`, `password_histories` (114 tabel); kolom `users` 2FA/penguncian, `roles.is_active`, `permissions.name/guard_name/label`, `role_assignments.assigned_by`, `user_invitations.sent_count`, `is_active` di enam tabel master/organisasi, `stock_events.source_number`, `platform_users.last_login_at`, `companies.data`, `notes` di `material_request_lines` dan `proofs_of_delivery`; `audit_logs` diganti skema sebenarnya (A-75).
- **Katalog Status v0.7:** tujuh belas enum yang sudah dipakai kode didaftarkan (`request_line_status`, `fulfillment_source`, `destination_type`, `ownership_effect`, `proof_channel`, `discrepancy_origin`, `capacity_mode`, `reason_context`, `login_result`, dan lain-lain). **Tidak ada status baru.**
- **Status basi diperbarui:** `10-access` v0.5 (100 uji, `InviteUser`, `ManageTwoFactor`, tanda tangan 5 MB), `11-master` v0.3 (guard BR-PRJ-02 belum ada; enam kunci pengaturan company tanpa layar), `12-warehouse` v0.4 (delapan aksi, laporan selesai, A-67 disetujui), `13-stock` v0.3 (TC-STK-34), `14-request` v0.3 (sepuluh aksi, bukan sebelas seperti v0.15), `15-picking-shipment` v0.3, `08-arsitektur` v0.7 (§4 keadaan kode, §10 profil XAMPP, §12 A-50), laporan audit §9, checklist, `cek.md`.
- **Setup lokal:** berkas baru `00-setup-lokal.md`; `00-akun-uji.md` → v1.2: stok awal dan kendaraan demo, `--tenants=1` menggantikan `--tenants=demo` yang gagal, klaim bahwa `ProductionSeeder` menyeed data acuan tenant dikoreksi. `CLAUDE.md` mendapat profil kantor.
- **Kode:** `StockDemoSeeder` (A-72) dipanggil `DemoSeeder`; `.env.example` `CACHE_STORE=array`, karena `database` tidak mendukung tag yang dibutuhkan stancl/tenancy sehingga setiap halaman tenant galat (AD-11 menetapkan Redis).
- **Pengujian:** 318 → **319 uji / 1.830 asersi**, hijau di MariaDB 10.4.

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
