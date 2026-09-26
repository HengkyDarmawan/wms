# Dokumentasi WMS Proyek (SaaS Multi-Company)

**Versi:** 0.54
**Tanggal:** 26 September 2026
**Status:** Part 1–3 selesai; **Part 4 berjalan** — modul **Access, Master, Warehouse, Stock, Request, Picking/Shipment, Receipt/Putaway, Approval, Count/Adjustment, Return/Transfer, Template dokumen & label, Issue (pemakaian material di site), Conversion/Waste, Asset, PurchaseRequest, Platform (company, langganan, tagihan), dan Pendukung F1 (strategi pengambilan, notifikasi, laporan & Beranda, wizard, impor Excel, PWA) selesai untuk Fase 1** (663 uji hijau di MariaDB 10.4). **Kode aplikasi ada di repo ini** (`app/`, `routes/`, `resources/`). Penutup Fase 1 selesai (uji rantai penuh, E2E, tinjauan kode 25 Sep); sesi kantor 25 Sep: sisa Fase 1 (pindai REQ/ISU, impor vendor & saldo awal), **Purchasing inti Fase 1b** ([purchasing/02](purchasing/02-purchasing-inti.md)), dan **landing page Part 5** ([30-landing-page](wms/30-landing-page.md)) selesai. **26 Sep 2026:** pemilik produk memutus 31 asumsi ⚠ (+A-111, A-116); fitur turunannya dibangun ([A-246–A-256](wms/04b-asumsi-lanjutan.md): PO melebihi PRQ, SJ jemput, aset antar proyek, dokumen terkait, potong banyak batang, denah gudang 2D), ditambah kompresi foto (A-257) dan impor struktur gudang (A-258). D-01–D-29 berlaku (peta rilis: WMS → Purchasing inti → WhatsApp → PWA offline → SSO); **A-01–A-71 disetujui**; asumsi A-72 dst. divalidasi lewat [tinjauan asumsi](00-tinjauan-asumsi-2026-09-25.md) — **106 masih menunggu**; berikutnya: meneruskan tinjauan itu. Menjalankan aplikasi: [00-setup-lokal.md](00-setup-lokal.md) · Progres: [00-laporan-progres-2026-09-24.md](00-laporan-progres-2026-09-24.md) · Laporan: [00-laporan-audit-2026-09-24.md](00-laporan-audit-2026-09-24.md)

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
├── 00-tinjauan-asumsi-2026-09-25.md     ← daftar kerja 100 asumsi *Perlu validasi* (A-72–A-194) untuk pemilik produk, ⚠ = paling berdampak
├── 00-catatan-perubahan-arsip.md         ← catatan perubahan README v0.2–v0.10 (dipindah agar README ≤ 450 baris)
├── 00-setup-lokal.md                      ← instalasi & menjalankan aplikasi: profil rumah (Laragon) dan kantor (XAMPP + MariaDB), hosts, seed, skenario uji manual
├── 00-audit/README.md                     ← indeks ID temuan prototipe (BUG/UX/SEC/T) yang dirujuk
├── 00-audit/alur-proses.html              ← bacaan cepat: 10 alur BPMN sebagai diagram alir sederhana — DIBUAT OTOMATIS dari diagram/_generate_alur_html.py
├── 00-audit/alur-per-peran.html           ← alur yang sama diputar PER PERAN (apa yang dikerjakan tiap pengguna) — DIBUAT OTOMATIS dari diagram/_generate_peran_html.py
├── wms/
│   ├── 01-blueprint.md                    ← gambaran produk, konsep inti, dokumen, fase rilis
│   ├── 02-riset-wms-sejenis.md            ← riset produk sejenis & keputusan adopsi
│   ├── 03-glosarium.md                    ← istilah UI ↔ nama di kode (wajib)
│   ├── 04-keputusan-dan-asumsi.md         ← D-xx, A-xx, O-xx
│   ├── 04a-matriks-ketertelusuran.md      ← matriks ketertelusuran (dipisah dari 04 v0.31)
│   ├── 04b-asumsi-lanjutan.md             ← asumsi A-246 dst. (lanjutan §2 dari 04 v0.39)
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
│   ├── 17-platform-login.md               ← Part 4: modul Platform — company & provisioning, paket & trial, tagihan & bukti bayar, siklus langganan, akses dukungan, gerbang langganan — v0.2
│   ├── 18-template-dokumen-label.md       ← Part 4: modul Template dokumen & label (layout induk, cetak PDF dokumen, label barcode/QR) — v0.1, §13 catatan implementasi
│   ├── 19-receipt-putaway.md              ← Part 4: modul Receipt/Putaway (GRN vendor & transfer, QC, PUT, RTV) — v0.4, §13 status & catatan implementasi
│   ├── 20-approval.md                     ← Part 4: mesin approval bersama (aturan, lapis, SoD, delegasi, eskalasi, simulasi; REQ, RTV, ADJ, OPN tersambung) — v0.3, §13 status & catatan implementasi
│   ├── 21-opname-penyesuaian.md           ← Part 4: modul Count/Adjustment (sesi opname, hitung buta, hitung ulang, approval sesi, ADJ manual & pembalik) — v0.2, §13 status & catatan implementasi
│   ├── 22-retur-transfer.md               ← Part 4: modul Transfer/Return (TRF antar gudang/proyek/titik, TRF backorder REQ, RET dari proyek, SJ balik, GRN retur, pemilahan) — v0.2, §13 status & catatan implementasi
│   ├── 23-pemakaian.md                    ← Part 4: modul Issue (ISU pemakaian material di Gudang Site, ISU pembalik dengan approval, laporan Material per Proyek, cetak Bukti Pemakaian) — v0.3, §13 catatan implementasi
│   ├── 24-konversi-waste.md               ← Part 4: modul Conversion/Waste (CNV potong/rakit/bongkar/kemas dengan silsilah & neraca, CNV pembalik, BA waste dengan bukti, cetak) — v0.2, §13 catatan implementasi
│   ├── 25-aset.md                         ← Part 4: modul Asset (AST otomatis dari SJ/RET, pemeriksaan grade+skor+foto, state aset dari kartu stok, aset hilang → ADJ, laporan aset dipinjamkan) — v0.2, §13 catatan implementasi
│   ├── 26-purchase-request.md             ← Part 4: modul PurchaseRequest (PRQ backorder REQ / titik pesan ulang / manual, approval opsional jenis vendor, catatan pemesanan per vendor, GRN merujuk catatan, reservasi ke REQ) — v0.1, §13 catatan implementasi
│   ├── 27-pendukung-f1.md                 ← Part 4: Pendukung Fase 1 (strategi pengambilan, konfirmasi terima, penutupan proyek, notifikasi, laporan inti + PDF, Beranda, wizard setup, impor Excel, PWA) — v0.1
│   ├── 30-landing-page.md                 ← Part 5: landing page produk di domain pusat (Blade + Alpine, gaya indonesia.travel) — v0.1
│   └── _template-spesifikasi-modul.md     ← template Part 4
├── prompts/                               ← paket prompt Part 6 per modul, plus prompt audit lama dan serah terima 00-lanjutkan-di-rumah.md v2.0 (kantor → rumah, aktif) / 00-lanjutkan-di-kantor.md (arsip)
├── akuntansi/01-lingkup-dan-integrasi-wms.md
├── purchasing/01-lingkup-dan-integrasi-wms.md
├── purchasing/02-purchasing-inti.md          ← spesifikasi Purchasing inti Fase 1b (PO dari PRQ, harga beli vendor, approval nilai, PO → GRN) — v0.1
└── diagram/
    ├── _generate.py                       ← SUMBER data alur Part 2; `py -3 docs/diagram/_generate.py` menulis ulang 07* dan bpmn-*.drawio
    ├── _generate_peran_html.py            ← alur per peran (A-226); `py -3 docs/diagram/_generate_peran_html.py` menulis ulang 00-audit/alur-per-peran.html
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
| 3 | Arsitektur & ERD (DB pusat + DB per company), verifikasi paket | `wms/08-arsitektur.md` + `08a`–`08c` (Mermaid `erDiagram`) + `diagram/erd-*.drawio`, dari `diagram/_generate_erd.py` | **Arsitektur v0.13, model data v0.14** — 114 tabel, 15 keputusan AD-xx, paket terverifikasi (O-01 ✔); 24 Sep 2026 diselaraskan dengan implementasi (`projects.site_warehouse_id` dihapus per A-40, kolom implementasi modul Master s.d. Asset masuk) |
| 4 | Spesifikasi modul BE (per modul, dari template) | `wms/10-…` s.d. `wms/2x-…` | **Berjalan** — [10-access](wms/10-access.md) v0.5, [11-master](wms/11-master.md) v0.3, [12-warehouse](wms/12-warehouse.md) v0.4, [13-stock](wms/13-stock.md) v0.3, [14-request](wms/14-request.md) v0.7, [15-picking-shipment](wms/15-picking-shipment.md) v0.6, [19-receipt-putaway](wms/19-receipt-putaway.md) v0.7, [20-approval](wms/20-approval.md) v0.7, [21-opname-penyesuaian](wms/21-opname-penyesuaian.md) v0.3, [22-retur-transfer](wms/22-retur-transfer.md) v0.4, [18-template-dokumen-label](wms/18-template-dokumen-label.md) v0.4, [23-pemakaian](wms/23-pemakaian.md) v0.3, [24-konversi-waste](wms/24-konversi-waste.md) v0.2, [25-aset](wms/25-aset.md) v0.2, dan [26-purchase-request](wms/26-purchase-request.md) v0.1 **selesai Fase 1**; [17-platform-login](wms/17-platform-login.md) v0.2 **selesai Fase 1**; [16-shared-laporan-berkas](wms/16-shared-laporan-berkas.md) mendokumentasikan kode bersama yang dibangun tanpa spesifikasi (16 v0.5: laporan Material per Proyek dengan kolom konversi & waste, laporan Aset dipinjamkan); [27-pendukung-f1](wms/27-pendukung-f1.md) v0.1 **selesai Fase 1**; berikutnya penutup. Pola per modul: spesifikasi → prompt → kode |
| 5 | FE landing page (gaya indonesia.travel) | `wms/30-landing-page.md` | **Selesai** 25 Sep 2026 — [30-landing-page](wms/30-landing-page.md) v0.1, [A-220](wms/04-keputusan-dan-asumsi.md#a-220)–[A-225](wms/04-keputusan-dan-asumsi.md#a-225) menunggu validasi; brand & harga paket tetap [O-07](wms/04-keputusan-dan-asumsi.md#o-07), [O-08](wms/04-keputusan-dan-asumsi.md#o-08) |
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

### v0.54 — 26 September 2026 (sinkronisasi dokumen dengan kode)
- Spesifikasi [16-shared](wms/16-shared-laporan-berkas.md) v0.14: §2 tabel permission 7 → 41 laporan (21 permission); §3.2 baris `kinerja-pengiriman`/`penerimaan-vendor` yang terpotong diperbaiki, + 5 laporan inti (A-190); §1, §3.1, §6.2, §6.3, §13.3 ekspor PDF `/reports/{report}/pdf`; §10 TC-RPT-01 (8 → 41, Driver 12), TC-RPT-01b (+404), rujukan TC-RPT-02–10; §11 dan §13.4 pernyataan basi dihapus; §12 enam butir dicentang, dua tetap terbuka; §13.1 no. 1 (PDF, A-190, TC-RPT-05) dan no. 3 (404, TC-RPT-01b) ditandai *Sudah diperbaiki*.
- README *Status*: 579 → 663 uji, fitur 26 Sep (A-246–A-258), sisa asumsi 106 lewat tinjauan asumsi (daftar tautan A-72–A-205 diringkas); laporan progres v1.24: §1 ditandai potret 24 Sep, baris *Asumsi menunggu validasi* dan kalimat penutup diperbarui.

### v0.53 — 26 September 2026 (sesi kantor: impor struktur gudang)
- **Impor struktur gudang dari Excel**: kartu *Struktur gudang* di `/imports` (templat `/imports/bins/template`, permission `bin.manage` yang sudah ada) untuk gudang yang sudah ada — zona, rak, dan level dibuat otomatis dari baris bin lewat `SaveLocation`, bin lewat `SaveBin`; semua-atau-tidak, kode yang ada ditolak. Asumsi baru [A-258](wms/04b-asumsi-lanjutan.md#a-258) (Perlu validasi; menjawab sebagian O-12, mengubah bagian bin di A-192). Uji TC-WH-26–26d; `ui-check` 6c.
- 04 v0.40 (Validasi A-192); 04b v0.3; spesifikasi [12](wms/12-warehouse.md) v0.10 (§2, §6, §10, §11, §13.4c, §13.5), [27](wms/27-pendukung-f1.md) v0.6; laporan progres v1.23 (§5.10); prompt rumah v2.3; prompt rumah **v2.4** (pekerjaan sore A-257/A-258 di §1/§2, php.ini batas unggah di Langkah 0, jatah nomor dicek dari dokumen, urutan kerja mulai dari tindak lanjut *Ubah*).

### v0.52 — 26 September 2026 (sesi kantor: kompresi foto otomatis)
- **Kompresi foto di server** menjalankan [A-23](wms/04-keputusan-dan-asumsi.md#a-23): satu titik `ImageCompressor` di `StoreUpload::handle()` (GD, tanpa paket baru) dipakai semua jalur foto — item, tanda tangan, lampiran ISU/AST, inspeksi aset, BA waste, bukti terima SJ (driver & `/terima/{token}`), keberatan, bukti bayar, logo. Mentah ≤ 20 MB, tersimpan ≤ 5 MB, sisi terpanjang 1920 px, JPEG kualitas 80, diputar sesuai EXIF; PNG transparan, tanda tangan, dan logo tetap PNG; PDF tidak diubah. Asumsi baru [A-257](wms/04b-asumsi-lanjutan.md#a-257) (Perlu validasi); `config/livewire.php` baru (unggah sementara 20 MB).
- Uji TC-FIL-03–03e (`ImageCompressionTest`), TC-FIL-01d & TC-TPL-07b menolak > 20 MB. php.ini perlu dinaikkan: [setup lokal](00-setup-lokal.md#batas-unggah-php) v1.6.
- 04b v0.2; spesifikasi [16](wms/16-shared-laporan-berkas.md) v0.13; laporan progres v1.22 (§5.9); arsip changelog v1.6 (blok v0.12 dipindah).

### v0.51 — 26 September 2026 (sesi kantor: keputusan pemilik produk atas asumsi ⚠)
- **Validasi:** 31 asumsi ⚠ diputus — 28 Setuju, A-229 Setuju sebagian, A-210 diubah; A-111 dan A-116 diubah; asumsi baru [A-246–A-256](wms/04b-asumsi-lanjutan.md) (04 v0.39 + berkas lanjutan 04b v0.1; [tinjauan asumsi](00-tinjauan-asumsi-2026-09-25.md) v1.13 — 119 asumsi tanpa ⚠ masih menunggu).
- **PO boleh melebihi PRQ** beralasan (A-246, migrasi tenant `000250`, TC-PO-11); **SJ tanpa PCK** & **SJ jemput retur** + kolom asal per baris SJ (A-247, A-248, `000260`, TC-RET-05, TC-RET-18–21, TC-SJ-19); **transfer aset On-site antar proyek** — TRF aset, SJ antar site, AST `transferred`, kejadian `asset_transferred`, pindahan sisa proyek dari hub, jejak lokasi aset, kirim tautan via WA (A-249–A-251, `000270`, TC-AST-14–16, TC-TRF-18–20); **dokumen terkait** di 14 layar detail & linimasa dokumen proyek (A-252, TC-DOC-01–03); **CNV potong banyak batang** (A-253, TC-CNV-15/16); **denah gudang 2D**, rak area & bin ikut terpakai, tanggal masuk FIFO, ubah bin (A-254–A-256, `000280`, TC-WH-21–25).
- Katalog v0.21 (§2.3, §2.7, §2.8, §2.11, §3); aturan bisnis v0.11 (BR-RET-02/03, BR-SJ-07, §14); model data v0.22; spesifikasi [11](wms/11-master.md) v0.8, [12](wms/12-warehouse.md) v0.9, [15](wms/15-picking-shipment.md) v0.14, [16](wms/16-shared-laporan-berkas.md) v0.12, [22](wms/22-retur-transfer.md) v0.7, [24](wms/24-konversi-waste.md) v0.5, [25](wms/25-aset.md) v0.7, [purchasing/02](purchasing/02-purchasing-inti.md) v0.2; laporan progres v1.21 (§5.8); arsip changelog v1.5 (blok v0.11 dipindah); prompt serah terima kantor → rumah v2.2.

### v0.50 — 25 September 2026 (sesi rumah: Sisa Fase 1 butir 2f)
- **Rekonsiliasi saldo terjadwal** `stock:reconcile` 02:00, hanya melapor ([A-243](wms/04-keputusan-dan-asumsi.md#a-243), TC-STK-35); **bukti terima per unit** serial/potongan ([A-244](wms/04-keputusan-dan-asumsi.md#a-244), TC-SJ-18); **kelebihan terima GRN transfer/retur → ADJ `over_receipt`**, migrasi tenant `000240_add_over_receipt_columns` ([A-245](wms/04-keputusan-dan-asumsi.md#a-245), TC-GRN-11). Transfer aset On-site antar proyek menunggu keputusan ([A-116](wms/04-keputusan-dan-asumsi.md#a-116)).
- Katalog v0.20 (`adjustment_origin` + `over_receipt`); model data (`qty_excess`, `stock_adjustments.goods_receipt_id`); spesifikasi [13](wms/13-stock.md) v0.12, [15](wms/15-picking-shipment.md) v0.13, [19](wms/19-receipt-putaway.md), [21](wms/21-opname-penyesuaian.md), [27](wms/27-pendukung-f1.md); 04 v0.38; tinjauan asumsi v1.12; laporan progres v1.20; arsip changelog v1.4 (blok v0.10 dipindah).
- Prompt serah terima **[00-lanjutkan-di-kantor](prompts/00-lanjutkan-di-kantor.md) v2.0** (rumah → kantor, aktif; jatah A-246 dst.), [00-lanjutkan-di-rumah](prompts/00-lanjutkan-di-rumah.md) v2.1 (arsip).

### v0.49 — 25 September 2026 (sesi rumah: laporan §9 modul 19–22, short pick TRF)
- **16 laporan §9** modul 19–22 (`penerimaan-vendor` … `rusak-bin-retur`, total 41 laporan) + `Reports\Concerns\{LatestInbound, ApprovalScope}` — [A-241](wms/04-keputusan-dan-asumsi.md#a-241), uji TC-RPT-07–10; **short pick PCK TRF → TRF backorder pengganti** untuk REQ penunggu — [A-242](wms/04-keputusan-dan-asumsi.md#a-242), TC-TRF-17. SJ balik barang di tangan klien menunggu keputusan pemilik ([A-111](wms/04-keputusan-dan-asumsi.md#a-111)).
- Spesifikasi [16](wms/16-shared-laporan-berkas.md) v0.11 (§3.2 +16 baris), [19](wms/19-receipt-putaway.md) v0.10, [20](wms/20-approval.md) v0.11, [21](wms/21-opname-penyesuaian.md) §9/§13, [22](wms/22-retur-transfer.md) v0.6; 04 v0.37; tinjauan asumsi v1.11; laporan progres v1.19; arsip changelog v1.3 (blok v0.9 dipindah).

### v0.48 — 25 September 2026 (sesi rumah: override bin beku, penuaan penggantian)
- **Override SJ mendesak dari bin beku** (BR-OPN-02): migrasi tenant `000230_add_freeze_override_to_pick_tasks`, `OverrideFrozenBinPick` (`bin.manage`), `MovementRequest::allowFrozenSource`, `FrozenBinPickShift` (angka sesi −X, bin ⚑) — [A-240](wms/04-keputusan-dan-asumsi.md#a-240), TC-OPN-21; **`requests:expire-substitutions`** tiap jam (BR-REQ-13) — [A-239](wms/04-keputusan-dan-asumsi.md#a-239), TC-REQ-20.
- Spesifikasi [14](wms/14-request.md) v0.11, [15](wms/15-picking-shipment.md) v0.12, [21](wms/21-opname-penyesuaian.md) v0.6; model data (`pick_tasks.freeze_override_*`); 04 v0.36; tinjauan asumsi v1.10; laporan progres v1.18.

### v0.47 — 25 September 2026 (sesi rumah: lampiran generik)
- **Lampiran generik** (Sisa Fase 1 butir 2b): migrasi tenant `000220_create_attachments_table`, `Shared\Attachments` (model, `AttachmentStore`), `GET /attachments/{id}` berizin; foto pemakaian ISU (`POST /issues/{id}/photos`), foto serah terima keluar AST (`POST /asset-handovers/{id}/photo-out`), arsip PDF laporan opname saat sesi ditutup — [A-238](wms/04-keputusan-dan-asumsi.md#a-238); uji TC-ISU-18, TC-AST-13, TC-OPN-20.
- Katalog v0.20 (`attachment_kind`); model data v0.21 (`attachments`, `photo_out_id`, `report_attachment_id`); spesifikasi [16](wms/16-shared-laporan-berkas.md) v0.10, [21](wms/21-opname-penyesuaian.md) v0.5, [23](wms/23-pemakaian.md) v0.5, [25](wms/25-aset.md) v0.6; 04 v0.35; tinjauan asumsi v1.9; laporan progres v1.17; arsip changelog v1.2 (blok v0.8 dipindah).

### v0.46 — 25 September 2026 (sesi rumah: notifikasi §8 Sisa Fase 1)
- **Notifikasi §8** 11/13/14/25: 11 kunci kejadian baru (`item.provisional_created`, `project.closed`, `stock.period_locked`, `stock.reservation_stale`, `request.review_overdue`, `request.decided`, `request.line_substituted`, `request.promise_changed`, `request.line_cancel_requested`, `request.line_cancel_decided`, `asset.life_alert`); `notifications:daily` memakai `DailyReminders` (SLA tinjau, reservasi menggantung, aset jatuh tempo + PIC, sisa umur); job harian melewati company ditangguhkan — [A-233–A-237](wms/04-keputusan-dan-asumsi.md#a-233); uji TC-NTF-07–11.
- Spesifikasi [11](wms/11-master.md) v0.7, [13](wms/13-stock.md) v0.11, [14](wms/14-request.md) v0.10, [17](wms/17-platform-login.md) v0.6, [25](wms/25-aset.md) v0.5, [26](wms/26-purchase-request.md) v0.4, [27](wms/27-pendukung-f1.md) v0.5 (§8, §13); 04 v0.34; [tinjauan asumsi](00-tinjauan-asumsi-2026-09-25.md) v1.8 (+A-233–A-237); laporan progres v1.16 (§5.7: 621 uji hijau); arsip changelog v1.1 (blok v0.7 dipindah).

### v0.45 — 25 September 2026 (penutup putaran)
- Laporan progres v1.15 (§4 baris navigasi/hub/konversi/pengaturan/laporan; §5.6 putaran 25 Sep sore: 616 uji, E2E `alur-req-sj` 10/10, `alur-pendukung` 9/9, `ui-check` 87 cek — 0 error console); prompt serah terima **[00-lanjutkan-di-rumah](prompts/00-lanjutkan-di-rumah.md) v2.0** (kantor → rumah, XAMPP3: langkah 0, jatah A-233, daftar selesai/belum) dan [00-lanjutkan-di-kantor](prompts/00-lanjutkan-di-kantor.md) v1.1 (arsip); E2E `go()` menunggu kesiapan halaman, P5 memantau baris preferensi; [27-pendukung-f1](wms/27-pendukung-f1.md) E2E P1–P9. **Sapuan §13**: butir sisa yang sudah terbangun dicoret — 12-warehouse v0.8, 13-stock v0.10, 15-picking-shipment v0.11, 16-shared v0.9 (Status), 17-platform v0.5, 18-template v0.7, 19-receipt v0.9, 20-approval v0.10, 21-opname v0.4, 22-retur-transfer v0.5, 23-pemakaian v0.4, 24-konversi v0.4, 25-aset v0.4 (BR-PRJ-04, BR-REQ-10, A-187/A-188/A-191/A-207/A-228/A-232).

### v0.44 — 25 September 2026 (laporan & template cetak yang tersisa)
- **11 laporan §9** Stock/Request/Picking-Shipment (`kartu-stok` … `kinerja-pengiriman`) dengan penyaring standar `PeriodFilter`, dan **4 template cetak** `transfer`, `goods_return`, `purchase_request`, `goods_receipt` + tombol *Cetak* di detailnya — [A-232](wms/04-keputusan-dan-asumsi.md#a-232); [16-shared-laporan-berkas](wms/16-shared-laporan-berkas.md) v0.8 §3.2, §10, §13.4; [18-template-dokumen-label](wms/18-template-dokumen-label.md) v0.6 §13.2; Katalog v0.19 §3; 13-stock v0.9, 14-request v0.9, 15-picking-shipment v0.10 (§13 sisa). Uji TC-RPT-06, TC-TPL-15/16; TC-RPT-01 (25 laporan). Keputusan & asumsi v0.33; tinjauan asumsi v1.7.

### v0.43 — 25 September 2026 (layar Pengaturan company)
- **Pengaturan company** `/settings/company`: ambang hari/persen (`CompanySettingCatalog`), saklar fitur P-08 dengan penanda *dipakai n item*, zona waktu; `SaveCompanySettings` hanya menulis kunci yang berubah — [A-230](wms/04-keputusan-dan-asumsi.md#a-230); [11-master](wms/11-master.md) v0.6 §6, §13.5 no. 8. Uji TC-MST-27. Keputusan & asumsi v0.32; tinjauan asumsi v1.6.

### v0.42 — 25 September 2026 (bukti terima: halaman penerima bertoken, foto & tanda tangan)
- **Halaman penerima bertoken** `/terima/{token}` (OTP → formulir → ringkasan; 410 bila tidak berlaku) dan unggah **foto serah terima, tanda tangan kanvas, foto kerusakan** di layar driver; berkas dilayani `shipments.proof.file`; policy `issueToken` memperbaiki tombol *Terbitkan tautan* yang sebelumnya tidak pernah aktif — [A-231](wms/04-keputusan-dan-asumsi.md#a-231); [15-picking-shipment](wms/15-picking-shipment.md) v0.9 §6, §13.1 no. 6, §13.3, §13.4. Uji TC-SJ-05d/05d2/05e; E2E langkah 5b (`alur-req-sj.mjs`).
- Keputusan & asumsi v0.31 (§4 matriks ketertelusuran dipindah ke [04a-matriks-ketertelusuran](wms/04a-matriks-ketertelusuran.md) v1.0 agar ≤ 450 baris); tinjauan asumsi v1.5.

### v0.41 — 25 September 2026 (rapi UI kecil)
- Font Inter (`@fonts`) di semua layout; `theme-color` & manifest PWA = `--bs-primary` NexaDash (`#6366f1`); kode internal `(A-xx)`/`(BR-xx)`/`(D-xx)` dihapus dari teks yang tampil ke pengguna (pindah ke komentar kode; badge kode aturan tetap); tanggal-jam memakai `->lokal()` (BR-GEN-07) di aset, PRQ, platform, dan tempat yang masih memakai `timezone()` langsung; `table-responsive` pada simulasi approval & form pemesanan PRQ.
- **Beranda portal klien** diisi (kartu, daftar proyek, Stok On-site per proyek; BR-PRJ-05/07) — [10-access](wms/10-access.md) v0.8 §13.3, TC-MST-26; form pengguna memilih klien dari daftar (TC-ACC-UI-07).
- Laporan dengan kunci tak dikenal → 404 — [16-shared-laporan-berkas](wms/16-shared-laporan-berkas.md) v0.7 §13.4, TC-RPT-01b. Uji TC-PWA-01 memeriksa warna tema; ui-check menambah pemeriksaan lebar HP untuk layar lapangan & form.

### v0.40 — 25 September 2026 (form Konversi per jenis)
- **Konversi** dirancang ulang: jenis sebagai tombol pilihan, mode Potong (satu batang → ukuran × jumlah, kerf dari master, sisa otomatis offcut/waste), Ganti kemasan (susut otomatis waste), Rakit/Bongkar bebas; ringkasan otomatis satu sumber dengan simpan (`ConversionPlanner`) menutup bug neraca layar ≠ server; *Simpan & selesaikan / & ajukan*; detail CNV berkalimat ringkas + *Buat BA Waste* (prefill WST); pindai batang — [A-229](wms/04-keputusan-dan-asumsi.md#a-229); [24-konversi-waste](wms/24-konversi-waste.md) v0.3. Uji TC-CNV-12 (ditulis ulang), TC-CNV-14, TC-CNV-15; E2E P9.
- Keputusan & asumsi v0.30; tinjauan asumsi v1.4.

### v0.39 — 25 September 2026 (navigasi & hub Proyek)
- **Sidebar** dirampingkan: 2 butir atas + 11 grup lipat + kotak *Cari menu* (markup lipat NexaDash yang sudah ada), gate Kunci periode & Laporan diperbaiki, ikon ganda diganti, *Impor Excel* masuk sidebar — [A-227](wms/04-keputusan-dan-asumsi.md#a-227); [10-access](wms/10-access.md) v0.7.
- **Hub proyek** `/projects/{id}` (Blueprint §6.9): kartu ringkas, tombol aksi berisi proyek (`?project=` di REQ/RET, `?from_warehouse=` di TRF), 9 tab daftar, tutup proyek dengan checklist BR-PRJ-02 dari hub; tautan cepat di halaman gudang — [A-228](wms/04-keputusan-dan-asumsi.md#a-228); [11-master](wms/11-master.md) v0.5 (§6, §13.4), [12-warehouse](wms/12-warehouse.md) v0.7. Uji TC-MST-25/25b; `ui-check.mjs` memeriksa grup lipat, filter menu, dan hub.
- Keputusan & asumsi v0.29 (§2.19); tinjauan asumsi v1.3 (A-226–A-228).

### v0.38 — 25 September 2026 (alur aktivitas per peran)
- **Berkas baru `00-audit/alur-per-peran.html`:** alur Part 2 diputar per peran — "sebagai Kepala Gudang, apa saja yang saya kerjakan" — untuk panduan uji manual, penjelasan ke calon pengguna, dan bahan presentasi. Sebelas peran (Blueprint §4.2 + Super Admin), 62 diagram Mermaid, penanda serah-terima antar peran, tabel langkah berisi status/aturan, daftar layar per peran, dan gaya cetak satu peran per halaman.
- Generator baru `diagram/_generate_peran_html.py` membaca `FLOWS` dari `_generate.py` (data tidak diketik ulang) dan memeriksa bahwa **setiap lane terpetakan** sehingga tidak ada langkah yang hilang: 85 node non-Sistem → 161 langkah peran.
- Pemetaan lane → peran dicatat sebagai [A-226](wms/04-keputusan-dan-asumsi.md#a-226) *(Perlu validasi)*; termasuk keputusan menampilkan langkah lane *Approver* di Kepala Gudang, Manajemen, dan Admin Company sebagai langkah **bersyarat aturan approval** ([A-08](wms/04-keputusan-dan-asumsi.md#a-08), [A-09](wms/04-keputusan-dan-asumsi.md#a-09)).
- Aktivitas konfigurasi Admin Company, Manajemen, dan Auditor **tidak ada di Part 2**; sementara disusun dari spesifikasi modul dan ditandai bukan turunan BPMN. Menambahkan alur ke-11 (setup & konfigurasi company) ke `FLOWS` dicatat sebagai tindak lanjut.
- **Tidak ada ID lain yang berubah**; `_generate.py`, `bpmn-*.drawio`, dan `07*` tidak disentuh. Keputusan & asumsi v0.28.

### v0.37 — 25 September 2026 (sesi kantor: sisa Fase 1, Purchasing inti Fase 1b, landing page)
- **Sisa Fase 1:** pindai di form REQ & ISU (`Master\Support\ScanCode`) — [A-206](wms/04-keputusan-dan-asumsi.md#a-206), TC-REQ-34, TC-ISU-18; impor vendor & saldo awal (saldo awal = ADJ manual per gudang beralasan `OPENING`, tetap approval) — [A-207](wms/04-keputusan-dan-asumsi.md#a-207), TC-MST-24, TC-ADJ-12; [27-pendukung-f1](wms/27-pendukung-f1.md) v0.4. Isi balik `from_stock_status` (A-194) tetap ditunda: belum ada data produksi.
- **Purchasing inti Fase 1b** ([D-29](wms/04-keputusan-dan-asumsi.md#d-29)): spesifikasi baru [purchasing/02](purchasing/02-purchasing-inti.md) v0.1; migrasi tenant `000210` (`vendor_prices`, `purchase_orders`, `purchase_order_lines`, rujukan PO di catatan pemesanan); [A-208](wms/04-keputusan-dan-asumsi.md#a-208)–[A-218](wms/04-keputusan-dan-asumsi.md#a-218) (A-219 tidak dipakai); Katalog v0.18 §2.17 `PO` (status umum, tanpa nilai status baru), `approval_document_type` & `document_template_type` + `purchase_order`; glosarium v0.9 (Purchase Order, Baris PO, Harga Beli Vendor, Harga Satuan, Nilai PO); model data v0.20 (area Purchasing, 119 tabel); 20-approval v0.9 (`order_value_min`), 18-template v0.5 (cetak PO), 26-purchase-request v0.3 (`po_created`/`po_updated`/`po_cancelled`), purchasing/01 v0.4, arsitektur v0.17; 00-akun-uji v1.11 (harga demo, aturan demo ke-8). Uji TC-PO-01–10, TC-VPR-01; TC-APR-17, TC-APR-21, TC-ACC-27b diperbarui.
- **Landing page Part 5:** [30-landing-page](wms/30-landing-page.md) v0.1, [A-220](wms/04-keputusan-dan-asumsi.md#a-220)–[A-225](wms/04-keputusan-dan-asumsi.md#a-225); uji TC-LND-01–06; `tests/e2e/ui-check.mjs` memeriksa landing (konsol, tema, lebar 390 px).
- Keputusan & asumsi v0.27; tinjauan asumsi v1.2; laporan progres v1.14. Verifikasi: 600 uji hijau, E2E `alur-req-sj` 9/9, `alur-pendukung` 8/8 (P8 PO), `ui-check` 74 cek, 0 error console.

### v0.36 — 25 September 2026 (tinjauan kode Pendukung & Platform)
- **Perbaikan hasil tinjauan kode** (tanpa status/enum baru):
  - email notifikasi dikirim setelah commit, galat SMTP tidak membatalkan aksi (A-199; TC-NTF-05);
  - bayar terlambat memulai periode satu bulan dari hari verifikasi (A-195; TC-PLT-12);
  - update Livewire cari/filter/halaman lolos di mode hanya-baca (A-196; TC-ACC-28g);
  - tautan akses dukungan sekali pakai — migrasi pusat `000030` `support_accesses.link_nonce_hash`, `link_used_at` (A-199; TC-PLT-11);
  - keberatan terima hanya atas baris REQ sendiri + kunci kirim ganda (A-197); `still_needed` keberatan membuka lagi baris REQ (A-198; TC-REQ-33);
  - checklist penutupan proyek: REQ menunggu keputusan & SJ `prepared` menghalangi, diperiksa dalam transaksi (A-187 diperluas; TC-MST-20).
- Notifikasi tugas approval menyebut asal tugas (delegasi/eskalasi; TC-NTF-06).
- **Pengingat tagihan langganan** ke company: kejadian ke-9 `subscription.billing` (lonceng + email) saat tagihan terbit, jatuh tempo, ditangguhkan (A-202; TC-PLT-04 diperluas).
- **Pindai** di pencarian Item, Saldo stok (barcode item) & Aset, dan kolom *Pindai kode bin* di Put-away (A-201; TC-PUT-08, TC-STK-27 diperluas) dan alur *pindai bin lalu item* di layar PCK (A-203; TC-PCK-16; `wms/15-picking-shipment.md` → v0.8; `wms/19-receipt-putaway.md` → v0.8, `wms/13-stock.md` → v0.8).
- **Tinjauan kode kedua** (malam yang sama): sisa baris REQ (kurang ambil, `reship`, keberatan) bisa dipetik ulang (A-204; TC-PCK-17); DSC & konfirmasi otomatis dikunci; draf REQ proyek tertutup tidak bisa diajukan + bin Gudang Site ikut nonaktif (A-187; TC-MST-20); kode 2FA Super Admin salah ikut kunci akun (A-200; TC-PLT-13); pindai PCK item berlacak wajib nomor lot/serial (A-203; TC-PCK-18); job harian per company melewati company `provisioning`/`terminated` dan tidak berhenti karena galat satu company (`Platform\Support\OperatingCompanies`).
- **Pengetatan 2FA** tenant & Super Admin: kode TOTP tidak bisa dipakai ulang (migrasi tenant `000180`, pusat `000050` — `two_factor_last_step`), kode salah ikut kunci akun, kode pemulihan tidak peka huruf besar-kecil (A-205; TC-ACC-09, TC-PLT-13 diperluas). `wms/10-access.md` → v0.6; model data `08a`–`08c` → v0.19 (kolom 2FA `platform_users`, `support_accesses.link_*`).
- **2FA Super Admin** opsional: *Keamanan akun* `/admin/security`, langkah kode `/admin/two-factor`, migrasi pusat `000040`; `ManageTwoFactor` & partial 2FA dipakai bersama tenant (A-200; TC-PLT-13).
- **Asumsi baru [A-195](wms/04-keputusan-dan-asumsi.md#a-195)–[A-205](wms/04-keputusan-dan-asumsi.md#a-205)** (*Perlu validasi*, `04` → v0.26, §2.14); A-187 diperluas.
- **Uji:** 10 uji baru (TC-NTF-05/06, TC-PLT-12/13, TC-ACC-28g, TC-REQ-33, TC-PCK-16/17/18 + TC-MST-20, TC-PLT-04/11, TC-PUT-08, TC-STK-27 diperluas). **579 uji hijau**; `ui-check.mjs` 67 cek (+ layar Super Admin), `alur-req-sj.mjs` 9/9, `alur-pendukung.mjs` 7/7, 0 error console. `TenantTestCase` memakai manajer transaksi uji Laravel (callback `afterCommit` berjalan di uji).
- **Prompt serah terima baru** [`prompts/00-lanjutkan-di-kantor.md`](prompts/00-lanjutkan-di-kantor.md) v1.0 (rumah → kantor: langkah 0, jatah A-206, terapkan keputusan tinjauan asumsi); `prompts/00-lanjutkan-di-rumah.md` → v1.9 (arsip).
- `wms/17-platform-login.md` → v0.4 (§5.1, §5.2, §10 TC-PLT-12/13, §13); `wms/27-pendukung-f1.md` → v0.3 (§5, §10 TC-REQ-33, TC-NTF-05/06, §13.1 butir 11, §13.2); `00-tinjauan-asumsi-2026-09-25.md` → v1.1.

### v0.35 — 25 September 2026 (Pendukung Fase 1 selesai)
- **Berkas baru `wms/27-pendukung-f1.md` v0.1.** Migrasi tenant `000160` (`notifications`, `notification_preferences`).
  - **Strategi pengambilan** (`Stock\Support\RemovalOrder`): FIFO/FEFO/sisa potongan/manual, lot kedaluwarsa dilewati; alokasi keras tidak lagi menjanjikan baris saldo yang sama dua kali (A-185).
  - **Beranda** antrean pekerjaan per izin & cakupan (A-186); **guard penutupan proyek** BR-PRJ-02/04 (A-187).
  - **Konfirmasi & keberatan terima** BR-REQ-10 di layar REQ & portal, `deliveries:auto-confirm`; DSC `client_dispute` tanpa pergerakan stok (A-188).
  - **Notifikasi** in-app & email: lonceng, halaman, preferensi, 8 kejadian, `notifications:daily` (A-189).
  - **Laporan inti** saldo stok, mutasi periode, permintaan terbuka & barang rusak, konversi & waste, akurasi stok (total 14) + **ekspor PDF** semua laporan (A-190).
  - **Wizard setup awal** (A-191), **impor item & proyek (+klien) dari Excel** (A-192), **PWA** installable + pindai kamera + draf lokal (A-193).
- **Asumsi baru [A-185](wms/04-keputusan-dan-asumsi.md#a-185)–[A-194](wms/04-keputusan-dan-asumsi.md#a-194)** (*Perlu validasi*, `04` → v0.25, §2.13).
- **Model data** 08a–08c → v0.18 (kolom `notifications.title/body/url`, A-189; `stock_movements.from_stock_status`, A-194).
- **Modul lain:** `11-master` → v0.4, `13-stock` → v0.6, `14-request` → v0.8, `15-picking-shipment` → v0.7, `16-shared-laporan-berkas` → v0.6, `17-platform-login` → v0.3, `20-approval` → v0.8, `25-aset` → v0.3, `26-purchase-request` → v0.2, `08-arsitektur` → v0.16, laporan progres → v1.12, `prompts/00-lanjutkan-di-rumah.md` → v1.7, `cek.md`.
- **Perbaikan tampilan waktu (BR-GEN-07):** 62 tampilan tanggal-jam di 34 view memakai jam UTC; kini macro `lokal()` → zona company.
- **Temuan & perbaikan kartu stok ([A-194](wms/04-keputusan-dan-asumsi.md#a-194), migrasi tenant `000170`):** uji rantai penuh baru TC-E2E-01 (`tests/Feature/FullLifecycleTest.php`: GRN → QC → put-away → REQ → SJ → konfirmasi → site → ISU → retur → CNV → OPN → ADJ → aset) menunjukkan perubahan kondisi (Karantina → Tersedia, Rusak) tidak bisa dibangun ulang dari kartu stok dan pembaliknya salah kondisi. `stock_movements.from_stock_status` ditambahkan; `13-stock` naik versi, `04` → v0.25, model data → v0.18.
- **Berkas baru `00-tinjauan-asumsi-2026-09-25.md`:** tabel ringkas semua asumsi *Perlu validasi* (A-72–A-194) dengan kolom keputusan (butir Penutup).
- **E2E:** `alur-req-sj.mjs` membaca saldo awal dari database; skrip baru `alur-pendukung.mjs` (P1–P6). Pada data demo segar: 9/9 + 6/6 lulus, 0 error console; `ui-check.mjs` 61 pemeriksaan OK.
- **Uji:** 23 uji baru (TC-PCK-12–15, TC-DSH-01, TC-MST-20–22, TC-REQ-30–32, TC-NTF-01–04, TC-RPT-02–05, TC-PWA-01, TC-GEN-07, TC-STK-26, TC-E2E-01); TC-RPT-01 (14 laporan) disesuaikan. **569 uji hijau** (MariaDB 10.4.32, XAMPP3).

### v0.34 — 25 September 2026 (modul Platform penuh selesai Fase 1)
- **`wms/17-platform-login.md` → v0.2 (selesai Fase 1).** Domain `app/Domain/Platform`, migrasi pusat `000020` (tanpa tabel tenant baru).
  - **Buat company** dari layar Super Admin: database → migrasi → `TenantDatabaseSeeder` → Admin Company + undangan → `active`; gagal → tetap `provisioning`, bisa dilanjutkan (A-176).
  - **Paket & trial** (durasi trial per paket), **tagihan** H-7 `INV/<yymm>/<urut>`, **bukti bayar** diunggah Admin Company (`/billing`, juga saat ditangguhkan) dan diverifikasi/ditolak Super Admin (A-177, A-178).
  - **Siklus harian** `subscriptions:cycle` 00:30: `trial/active → past_due` (tenggang 7 hari) `→ suspended` (30 hari) `→ terminated` (simpan 90 hari) (BR-SUB-01).
  - **Penangguhan manual** company, **flag fitur** stub, **masuk lewat akses dukungan** hanya-baca atas nama Admin pemberi izin (A-179, A-180, A-183).
  - Gerbang: status company ikut menentukan; `terminated` hanya Beranda/Laporan/Tagihan/Profil (A-181); login Super Admin terkunci setelah gagal berulang + `platform_login_attempts` + `audit_logs` pusat (A-182).
  - Temuan v0.1 §13.2 diselesaikan: data acuan company baru, `TenantDeleted` tidak lagi menghapus database, docblock `ProductionSeeder`.
- **Asumsi baru [A-176](wms/04-keputusan-dan-asumsi.md#a-176)–[A-184](wms/04-keputusan-dan-asumsi.md#a-184)** (*Perlu validasi*, `04` → v0.23, §2.12).
- **Permission baru** modul `billing` (2): `billing.view`, `billing.pay` (Admin Company).
- **Katalog** `06` → v0.17: enum `subscription_invoice_status`, `subscription_payment_status` didaftarkan (nilai ERD) — **tanpa status baru**.
- **Model data** 08a–08c → v0.16 (digenerate ulang): kolom & tabel pusat A-184.
- **Modul lain:** `08-arsitektur` → v0.15 (§3 langkah 5, §7, §12), `00-akun-uji` → v1.10, laporan progres → v1.11, `prompts/00-lanjutkan-di-rumah.md` → v1.6, `cek.md`.
- **Uji:** 9 uji baru TC-PLT-03–11 (termasuk pembuatan database company sungguhan). TC-ACC-27b (2 permission `billing`) disesuaikan. **546 uji hijau** (MariaDB 10.4.32, XAMPP3).

### v0.33 — 25 September 2026 (modul Purchase Request selesai Fase 1)
- **Berkas baru `wms/26-purchase-request.md` v0.1 (selesai Fase 1).** Domain `app/Domain/PurchaseRequest`, migrasi tenant `000150` (`purchase_requests`, `purchase_request_lines`, `purchase_request_orders`, `purchase_request_order_lines`; indeks `goods_receipt_lines.purchase_request_order_line_id`).
  - PRQ dari tiga asal (KS §2.15): **backorder** baris REQ bersumber pembelian saat REQ disetujui (BR-REQ-05), **titik pesan ulang** (job harian 06:00 `purchase-requests:reorder`, draf, BR-REQ-11), **manual** (`pr.create`). Kejadian `purchase_requested` / `purchase_request_cancelled`.
  - Approval opsional lewat mesin approval (tanpa aturan = disetujui); kondisi jenis vendor & asal (BR-APR-07). Semua jenis `approval_document_type` kini tersambung.
  - **Catatan pemesanan** per vendor oleh Penindak Lanjut PR (`pr.order`, A-51): PO eksternal, nomor pesanan toko online, resi, ETA; baris boleh dipecah ke beberapa vendor; vendor tetap item disarankan; vendor baru sementara (A-53).
  - **GRN vendor** merujuk baris catatan (BR-GRN-01/05); PRQ `partially_fulfilled`/`fulfilled`; `goods_received` membawa nomor PRQ & PO; barang backorder yang ditaruh direservasi ke REQ penunggu (BR-REQ-08). REQ batal/tutup → PRQ yang belum diteruskan ikut batal (BR-REQ-09/15).
  - Layar daftar/form/detail PRQ, kartu *Pesanan PRQ ke vendor ini* di form GRN, menu **Pembelian**, palet.
- **Asumsi baru [A-170](wms/04-keputusan-dan-asumsi.md#a-170)–[A-175](wms/04-keputusan-dan-asumsi.md#a-175)** (*Perlu validasi*, `04` → v0.22, §2.11): permission & cakupan, PRQ backorder, kolom di luar ERD, jenis vendor untuk aturan, sambungan GRN, job titik pesan ulang.
- **Permission baru** modul `purchase_request` (6): `pr.view`, `pr.create`, `pr.submit`, `pr.approve`, `pr.order`, `pr.cancel`. Aturan demo **PRQ toko online** (00-akun-uji §5).
- **Katalog** `06` → v0.16: catatan §2.15 — **tanpa status baru**.
- **Model data** 08a–08c → v0.15 (digenerate ulang): kolom implementasi PRQ (A-172).
- **Modul lain:** `14-request` → v0.7, `19-receipt-putaway` → v0.7, `20-approval` → v0.7, `08-arsitektur` → v0.14 (§7 job, §12), `00-akun-uji` → v1.9, laporan progres → v1.10 (PRQ ✅), `prompts/00-lanjutkan-di-rumah.md` → v1.5.
- **Uji:** 12 uji TC-PRQ-01–12 (termasuk rantai REQ pembelian → PRQ → catatan pemesanan → GRN → put-away → reservasi → PCK → SJ). TC-ACC-27b (6 permission), TC-APR-17 (jenis di luar katalog), TC-APR-21 (7 aturan demo) disesuaikan. **537 uji hijau** (MariaDB 10.4.32, XAMPP3).

### v0.32 — 24 September 2026 (modul Aset dipinjamkan selesai Fase 1)
- **Berkas baru `wms/25-aset.md` v0.2 (selesai Fase 1).** Domain `app/Domain/Asset`, migrasi tenant `000140` (`asset_handovers`, `asset_inspections`, `maintenance_schedules` stub F2).
  - AST lahir otomatis saat SJ aset diterima proyek (jatuh tempo bawaan = target selesai proyek, meter keluar = pembacaan terakhir), `returned` saat GRN retur diterima (hari pakai BR-AST-05), `inspected` lewat pemeriksaan grade + skor + catatan komponen + foto (BR-AST-03/08).
  - State aset mengikuti lokasi & kondisi dari kartu stok dan reservasi (BR-AST-01) lewat observer — `reserved`, `in_transit`, `on_loan`, `returned`, hasil pemeriksaan, `written_off`.
  - Aset dipilah dari bin Retur setelah diperiksa, sesuai grade; `asset_returned` membawa hasil pemeriksaan (melengkapi stub A-116). Grade C/D dan aset hilang menerbitkan `asset_lost_or_damaged`.
  - Aset hilang → ADJ asal `asset_lost` lewat approval → `written_off`; ditemukan kembali bila ADJ ditolak.
  - Layar *Aset*, *Serah terima aset*, cetak **BA Serah Terima Aset**, laporan **Aset dipinjamkan** (lewat jatuh tempo, hari pakai), peringatan sisa umur.
- **Asumsi baru [A-163](wms/04-keputusan-dan-asumsi.md#a-163)–[A-169](wms/04-keputusan-dan-asumsi.md#a-169)** (*Perlu validasi*, `04` → v0.21, §2.10): AST mengikuti SJ/RET, sinkron state, kolom di luar ERD, guard pemeriksaan, aset hilang, permission & cakupan, jatuh tempo tanpa notifikasi.
- **Permission baru** modul `asset` (4): `asset.view`, `asset.manage`, `asset.inspect`, `asset.mark_lost`.
- **Katalog** `06` → v0.15: catatan §2.11, `asset_handover` tidak lagi stub, `adjustment_origin = asset_lost` tersambung — **tanpa status baru**.
- **Model data** 08a–08c → v0.14 (digenerate ulang): kolom implementasi `asset_handovers`, `asset_inspections` (A-165).
- **Modul lain:** `15-picking-shipment` → v0.6 (AST dari SJ), `21-opname-penyesuaian` → v0.3 (ADJ `asset_lost`), `22-retur-transfer` → v0.4 (pemeriksaan sebelum pilah), `18-template-dokumen-label` → v0.4, `16-shared-laporan-berkas` → v0.5 (laporan kesembilan), `08-arsitektur` → v0.13, `00-akun-uji` → v1.8, laporan progres → v1.9 (Aset ✅), `prompts/00-lanjutkan-di-rumah.md` → v1.4. Master: `Serial::usagePercent` memakai max(hari, meter) dan ambang `asset_life_alert_pct`.
- **Uji:** 12 uji TC-AST-01–12 (termasuk rantai REQ pinjam → PCK → SJ → RET → GRN → periksa → pilah → pinjam lagi, dan aset hilang diputus dari kotak tugas approval). TC-RET-13 kini memeriksa aset dulu; TC-TPL-04, TC-ACC-27b (4 permission `asset`), TC-RPT-01 (9 laporan) disesuaikan. **525 uji hijau** (MariaDB 10.4.32, XAMPP3).

### v0.31 — 24 September 2026 (modul Konversi & Waste selesai Fase 1, mesin rumah XAMPP3)
- **Berkas baru `wms/24-konversi-waste.md` v0.2 (selesai Fase 1).** Domain `app/Domain/Conversion` dan `app/Domain/Waste`, migrasi tenant `000130` (dari branch `wip/konversi-waste`, digabung lalu diselesaikan; berkas rencana WIP dihapus).
  - CNV potong/rakit/bongkar/ganti kemasan di satu gudang untuk satu proyek: input dari bin penyimpanan (potongan utuh, item *Bisa dipotong/dikonversi*), hasil output/offcut/waste/kerf dengan neraca ukuran (BR-CNV-02), offcut di bawah minimum otomatis waste (BR-CNV-03), potongan baru bersilsilah (BR-CNV-04), kejadian `material_converted`.
  - Approval opsional: *Ajukan* hanya bila ada aturan, *Selesaikan* hanya bila tidak; ditolak kembali Draf. **CNV pembalik** hanya bila semua hasil masih utuh (BR-CNV-05).
  - WST (Berita Acara Waste): baris dari bin Waste, disposisi dibuang/dijual scrap/dipakai ulang, disetujui otomatis tanpa aturan, ditutup dengan foto atau nomor BA; kejadian `waste_disposed`.
  - Cetak **Bukti Konversi Material** (jenis baru) dan **BA Waste** (tidak lagi stub); menu *Konversi & waste* dan palet Ctrl+K; laporan Material per proyek menambah kolom *Dikonversi*, *Hasil konversi*, *Waste*, *Waste didisposisi*.
- **Asumsi baru [A-153](wms/04-keputusan-dan-asumsi.md#a-153)–[A-162](wms/04-keputusan-dan-asumsi.md#a-162)** (*Perlu validasi*, `04` → v0.20, §2.9).
  - A-153: approval CNV opsional tanpa status baru. A-154: input & baris CNV. A-155: kolom di luar ERD. A-156: neraca per jenis konversi.
  - A-157: CNV pembalik. A-158: permission & cakupan. A-159: baris & alur WST. A-160: bukti tutup WST dan cetak. A-161: kolom laporan.
  - A-162: mesin dev rumah pindah ke XAMPP3 + MariaDB 10.4.32 (A-76 kini berlaku di kedua mesin).
- **Permission baru** modul `conversion` (6) dan `waste` (5); Staf Gudang tanpa `approve`, Manajemen `view/approve`, Auditor `view`.
- **Katalog** `06` → v0.14: enum `conversion_type`, `conversion_output_kind`; `document_template_type` + `conversion`, `waste_disposal` aktif; CNV & WST tersambung ke mesin approval; catatan §2.10 dan §2.14 — **tanpa status baru**.
- **Model data** 08a–08c → v0.13 (digenerate ulang): kolom implementasi `conversions`, `conversion_inputs`, `conversion_outputs`, `waste_disposals`, `waste_disposal_lines` (A-155).
- **Modul lain:** `Piece::nextPieceNo()` dipakai GRN, ADJ, pilah RET, dan CNV; `20-approval` → v0.6, `18-template-dokumen-label` → v0.3, `22-retur-transfer` → v0.3, `23-pemakaian` → v0.3, `16-shared-laporan-berkas` → v0.4, `08-arsitektur` → v0.12 (§10 profil rumah XAMPP3, §12), `00-akun-uji` → v1.7 (CNV/WST tanpa aturan demo), laporan progres → v1.8 (Konversi ✅), `prompts/00-lanjutkan-di-rumah.md` → v1.3.
- **Uji:** 20 uji TC-CNV-01–14 dan TC-WST-01–06 (termasuk rantai RET waste → CNV lewat kotak tugas approval → CNV pembalik → WST → laporan). TC-ACC-27b menghitung 6 + 5 permission baru; TC-APR-17 memakai `purchase_request` sebagai jenis belum tersambung; TC-TPL-04 tidak lagi mengharap 501 untuk BA Waste. **513 uji hijau** (MariaDB 10.4.32, XAMPP3); E2E langkah 0 di mesin rumah: `ui-check` 0 error console, `alur-req-sj` 9/9.

### v0.30 — 24 September 2026 (serah terima kantor → rumah)
- **Berkas baru [prompts/00-lanjutkan-di-rumah.md](prompts/00-lanjutkan-di-rumah.md):** keadaan saat serah terima, langkah setup Laragon + MySQL 8.4 setelah pull, cara kerja per modul, jatah nomor (asumsi berikutnya A-153; A-120–A-149 milik modul Template), dan urutan sisa: Konversi/Waste (branch `wip/konversi-waste`, setengah jadi), Aset, Purchase Request, Platform penuh, pendukung F1, penutup.
- **Uji browser masuk repo:** `tests/e2e/ui-check.mjs` dan `tests/e2e/alur-req-sj.mjs` (langkah approval kini oleh Kepala Gudang CKG sesuai aturan demo 20-approval), dapat diatur lewat `WMS_BASE`, `CHROME_PATH`, `MYSQL_BIN`.
- **`.gitignore`:** `/storage/tenant*/` (disk per company, termasuk artefak uji yang sempat ter-commit) dan keluaran E2E.
- **Pengujian:** 493 uji hijau (MariaDB 10.4); alur E2E 9/9 lulus.
- **prompts/00-lanjutkan-di-rumah.md v1.1:** versi database rumah dikoreksi MySQL 8.3 → 8.4 LTS (selaras CLAUDE.md, 00-setup-lokal, 08-arsitektur); PHP 8.3.33 tetap.
- **Profil mesin rumah pindah ke XAMPP3** (`C:\xampp3`, PHP 8.3.33 = `php`, MariaDB 10.4.32, `php artisan serve` :8000; Laragon tidak dipakai lagi, A-76 berlaku di kedua mesin — dicatat sebagai [A-162](wms/04-keputusan-dan-asumsi.md#a-162) di v0.31): prompts/00-lanjutkan-di-rumah.md v1.2, 00-setup-lokal v1.5 §1–§4, `CLAUDE.md` *Lingkungan lokal*, `tests/e2e/README.md`.

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
