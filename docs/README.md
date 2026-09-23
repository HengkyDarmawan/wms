# Dokumentasi WMS Proyek (SaaS Multi-Company)

**Versi:** 0.3
**Tanggal:** 23 September 2026
**Status:** Part 1 diaudit ulang (v0.3). D-01–D-27 berlaku; A-01–A-24 disetujui; **A-25–A-49 menunggu validasi**; A-16 diusulkan berubah. Laporan: [00-laporan-audit-dokumentasi.md](00-laporan-audit-dokumentasi.md)

Dokumentasi ini adalah acuan tunggal untuk membangun WMS baru dari nol. Prototipe lama (`warehouse.sipembantu.com`) hanya referensi; indeks temuan auditnya ada di [00-audit](00-audit/README.md).

---

## Struktur folder

```
docs/
├── README.md                              ← dokumen ini
├── 00-laporan-audit-dokumentasi.md        ← audit v0.2 → v0.3: temuan, tindakan, asumsi baru
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
│   └── _template-spesifikasi-modul.md     ← template Part 4
├── akuntansi/01-lingkup-dan-integrasi-wms.md
├── purchasing/01-lingkup-dan-integrasi-wms.md
└── diagram/
    ├── _generate.py                       ← SUMBER data alur Part 2; `py -3 docs/diagram/_generate.py` menulis ulang 07* dan bpmn-*.drawio
    ├── _generate_erd.py                   ← SUMBER model data Part 3; `py -3 docs/diagram/_generate_erd.py` menulis ulang 08a–c dan erd-*.drawio
    ├── bpmn-01…10-*.drawio                ← BPMN (diagrams.net) — DIBUAT OTOMATIS, jangan diedit manual
    └── erd-*.drawio                       ← ERD per area — DIBUAT OTOMATIS, jangan diedit manual
../CLAUDE.md                               ← konteks untuk agen AI (urutan baca, larangan)
```

## Rencana part

| Part | Isi | Keluaran | Status |
|---|---|---|---|
| 1 | Blueprint, riset, glosarium, keputusan & asumsi, aturan bisnis, katalog status | `wms/01`–`06`, `akuntansi/01`, `purchasing/01` | **Selesai (v0.3)** — menunggu validasi A-25–A-49 |
| 2 | Proses bisnis to-be (BPMN 2.0) | `diagram/bpmn-*.drawio` + `wms/07`, `07a`, `07b` (teks per lane + tabel langkah + Mermaid), dari satu sumber `diagram/_generate.py` | **Draf v0.1** — 10 alur berbasis default A-25–A-49; final setelah validasi |
| 3 | Arsitektur & ERD (DB pusat + DB per company), verifikasi paket | `wms/08-arsitektur.md` + `08a`–`08c` (Mermaid `erDiagram`) + `diagram/erd-*.drawio`, dari `diagram/_generate_erd.py` | **Draf v0.1** — 106 tabel, 15 keputusan AD-xx, paket terverifikasi (O-01 ✔) |
| 4 | Spesifikasi modul BE (per modul, dari template) | `wms/10-…` s.d. `wms/2x-…` | — |
| 5 | FE landing page (gaya indonesia.travel) | `wms/30-landing-page.md` | — |
| 6 | Paket prompt Claude Code (per modul) | `prompts/*.md` | — |

## Jalur baca per audiens

| Audiens | Baca | Waktu |
|---|---|---|
| **Pemilik produk** (validasi) | [Laporan audit §1 & §6](00-laporan-audit-dokumentasi.md) → [Asumsi A-25–A-49](wms/04-keputusan-dan-asumsi.md#2-asumsi) → isi [00-audit](00-audit/README.md) | ±30 menit |
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
