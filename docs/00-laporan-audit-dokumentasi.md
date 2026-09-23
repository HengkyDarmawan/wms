# Laporan Audit Dokumentasi WMS — Part 1 (v0.2 → v0.3)

**Versi:** 1.0
**Tanggal:** 23 September 2026
**Auditor:** system analyst (perspektif 20+ tahun) + prompt engineer, dibantu satu peninjau independen (agen AI) untuk pendapat kedua
**Objek:** `docs/` v0.2 — README, Blueprint, Riset, Glosarium, Keputusan & Asumsi, Akuntansi
**Hasil:** 8 temuan struktural, 30 temuan alur/kontradiksi, 4 temuan glosarium/riset; **semua diterapkan** di v0.3, dengan 21 asumsi baru (A-29–A-49) dan 4 isu baru (O-12–O-15) yang **menunggu validasi pemilik produk**

---

## 1. Ringkasan eksekutif

Dokumen v0.2 sudah kuat pada visi produk, prinsip desain, dan riset. Kelemahannya ada di lapisan yang justru dibutuhkan Part 2–4: **model stok belum tunggal** (status vs bin virtual), **reservasi belum didefinisikan** (lunak/keras, kapan dilepas), **beberapa alur inti tidak ada** (pemakaian material di site, retur ke vendor, selisih pengiriman, short pick), **kejadian ke Akuntansi terhitung ganda**, dan **tidak ada katalog status/aturan ber-ID** sehingga developer atau agen AI akan mengarang.

Tindakan v0.3:
1. Struktur folder disesuaikan dengan janji README; dokumen Purchasing dan indeks audit prototipe dibuat.
2. Dua dokumen acuan baru: **Aturan Bisnis (BR-xx)** dan **Katalog Status & Enum**.
3. Blueprint, Glosarium, Keputusan, Akuntansi, Riset direvisi; semua rujukan menjadi link beranchor.
4. Template spesifikasi modul dan `CLAUDE.md` disiapkan untuk Part 4 dan Part 6.

Yang **harus dilakukan pemilik produk** sebelum Part 2: validasi A-25–A-49 (dikelompokkan per tema di [04 §2.3](wms/04-keputusan-dan-asumsi.md#23-baru-dari-audit-v03--menunggu-validasi)) dan isi keterangan di [00-audit](00-audit/README.md).

## 2. Metode

- Pembacaan penuh 6 dokumen; pemetaan silang setiap klaim ke dokumen lain.
- Uji "bisa digambar?": setiap alur dicek apakah BPMN/ERD bisa dibuat tanpa menebak.
- Uji "bisa di-prompt?": setiap aturan dicek apakah bisa dirujuk dengan ID stabil.
- Pendapat kedua dari peninjau independen dengan daftar temuan awal disembunyikan agar tidak bias; temuan digabung dan dideduplikasi.

Tingkat: **Tinggi** = memblokir Part 2/3 atau menyebabkan data salah; **Sedang** = ambigu, akan menghasilkan implementasi berbeda; **Rendah** = navigasi/konsistensi.

## 3. Temuan struktural

| ID | Tingkat | Temuan | Bukti (v0.2) | Tindakan v0.3 | Status |
|---|---|---|---|---|---|
| S-1 | Rendah | README menjanjikan subfolder, file rata; dua file berprefix `01-` | `README.md:13-26` | Dipindah ke `wms/`, `akuntansi/`, `purchasing/`, `diagram/` | Diterapkan |
| S-2 | Sedang | Dokumen Purchasing dirujuk 4× tetapi tidak ada | `README.md:24`, `01-blueprint.md:46,247,340` | [purchasing/01](purchasing/01-lingkup-dan-integrasi-wms.md) dibuat | Diterapkan |
| S-3 | Rendah | 24 ID audit prototipe menggantung | `01-blueprint.md:82-93` | [00-audit/README.md](00-audit/README.md) indeks | Diterapkan; keterangan diisi pemilik |
| S-4 | Rendah | Rujukan berupa teks berkode, bukan link | semua file | Link relatif beranchor; anchor per baris D/A/O | Diterapkan |
| S-5 | Rendah | Tidak ada jalur baca per audiens & matriks ketertelusuran | `README.md:39-43` | README §Jalur baca; [04 §4](wms/04-keputusan-dan-asumsi.md#4-matriks-ketertelusuran) | Diterapkan |
| S-6 | Rendah | Log keputusan tanpa tanggal/alasan/konsekuensi | `04:12-40` | Kolom ditambah, keputusan tidak diedit (ADR-lite) | Diterapkan |
| S-7 | Tinggi | Aturan bisnis tanpa ID; tidak ada katalog status/enum; tidak ada tag fase | `01-blueprint.md:95-227` | [05-aturan-bisnis](wms/05-aturan-bisnis.md), [06-katalog-status](wms/06-katalog-status-dan-enum.md), tag `[F1]/[F2]/[F3]` | Diterapkan |
| S-8 | Sedang | Tiga catatan tumpang tindih (timeline, audit lama→baru, audit log) | `01:251`, `01:88`, `01:351` | BR-GEN-05: `document_timeline` + `audit_log` | Diterapkan |

## 4. Temuan alur & kontradiksi

| ID | Tingkat | Temuan | Bukti (v0.2) | Tindakan v0.3 | Status |
|---|---|---|---|---|---|
| F-1 | Tinggi | P-02 "tidak bisa disetujui" vs 6.6 "reservasi sebagian"; "otomatis PR" vs "transfer atau PR" | `01:83,166,235` | P-02 ditulis ulang; BR-REQ-05 "wajib punya sumber" | Menunggu validasi A-30 |
| F-2 | Tinggi | Status stok dimodelkan ganda (status saldo vs bin virtual) | `01:123,163` | Lokasi = bin, kondisi = `stock_status`, reservasi = tabel | Menunggu validasi A-29 |
| F-3 | Tinggi | Reservasi lunak/keras tidak ditentukan | `01:163-165` | Dua tahap: lunak saat approval, keras saat picking | Menunggu validasi A-30 |
| F-4 | Tinggi | Pelepasan reservasi tidak diatur; PCK/PUT/PRQ/WST tanpa Dibatalkan | `01:233-247` | BR-STK-05; status Dibatalkan ditambah | Menunggu validasi A-30, A-33 |
| F-5 | Tinggi | Tidak ada pemakaian material dari Gudang Site; beban proyek tidak pernah tercatat | `01:168-174,216`; akuntansi `47-59` | Dokumen `ISU`, kejadian `material_consumed`, kolom *Terpakai* | Menunggu validasi A-32 |
| F-6 | Tinggi | Gudang sumber REQ tidak ada padahal nomor `REQ/{GUDANG}` | `01:235`; `04:62,67` | BR-REQ-04; 1 REQ : n PCK | Menunggu validasi A-31 |
| F-7 | Tinggi | TRF/RET vs SJ tumpang tindih; relasi dokumen tidak ada | `01:237-241` | [BR §1](wms/05-aturan-bisnis.md#1-relasi-antar-dokumen); niat vs fisik | Menunggu validasi A-33 |
| F-8 | Sedang | GRN "QC" sebagai status; tanpa retur ke vendor; waktu posting ledger vs kejadian | `01:238`; akuntansi `49` | QC = langkah; `RTV`; ledger & kejadian saat *Diterima* | Menunggu validasi A-34 |
| F-9 | Sedang | SJ tanpa selisih terima, Dibatalkan, ekspedisi; short pick tidak ada | `01:236-237` | `DSC`, BR-SJ-02/06/07 | Menunggu validasi A-35 |
| F-10 | Sedang | Per potong × UoM tidak konsisten; matriks kombinasi tidak ada | `01:145,157,178` | BR-STK-09–11, BR §15 | Menunggu validasi A-36 |
| F-11 | Sedang | Stok negatif & presisi tidak ada; kapasitas bin blok/peringatan | `01:124` | BR-STK-06–07, BR-GEN-08 | Menunggu validasi A-37 |
| F-12 | Tinggi | Kejadian ganda ke Akuntansi; payload tanpa pembalik/versi | akuntansi `45-59` | [Matriks kejadian](wms/05-aturan-bisnis.md#14-matriks-kejadian-stok); payload baru | Diterapkan (akuntansi v0.3) |
| F-13 | Sedang | Siklus aset ≠ status stok; hilang/dihapuskan tanpa jalur ledger | `01:193-198` | BR-AST-01–04 | Menunggu validasi A-29 |
| F-14 | Sedang | Model kepemilikan *Keduanya* tanpa aturan | `01:144` | `line_ownership` per baris | Menunggu validasi A-38 |
| F-15 | Sedang | Baris non-katalog: siapa membuat item, kapan | `01:235`; `04:52` | Item *Sementara* saat Ditinjau Staf | Menunggu validasi A-39 |
| F-16 | Sedang | Batasan pembatalan per dokumen tidak dirinci | `01:84,252` | BR-GEN-04 | Diterapkan |
| F-17 | Tinggi | Penutupan proyek kontradiktif dengan A-25; tanpa status/guard | `01:204` | BR-PRJ-01–04 | Menunggu validasi A-40 |
| F-18 | Sedang | "Stok On-site" dua arti; A-21 vs Gudang Site | `01:203`; `04:66` | Tiga sub-tampilan; A-21 diperjelas | Diterapkan |
| F-19 | Sedang | Ditinjau Staf: boleh ubah? jejak? tanggal dibutuhkan tidak ada | `04:52`; `01:219` | BR-REQ-01–02 | Menunggu validasi A-39 |
| F-20 | Sedang | Bukti terima tanpa akun tanpa mekanisme | `04:65` | Tautan bertoken + OTP | Menunggu validasi A-41, O-15 |
| F-21 | Sedang | Opname: beku vs tugas berjalan; toleransi hanya % | `01:284-286`; `04:61` | BR-OPN-01–04 | Menunggu validasi A-42 |
| F-22 | Sedang | Penomoran untuk dokumen tanpa gudang; race; offline | `04:62` | BR-GEN-06 | Menunggu validasi A-43 |
| F-23 | Rendah | Zona waktu: simpan apa, reset mengikuti apa | `01:355` | NFR-07 diperjelas, BR-GEN-07 | Diterapkan |
| F-24 | Sedang | Efek ditangguhkan pada PWA/job/WA/login tidak ada | `01:331` | BR-SUB-02–03 | Menunggu validasi A-44 |
| F-25 | Sedang | Approval edge case (SoD, ganda, delegasi, snapshot, bersamaan) | `01:257-278` | BR-APR-01–10 | Menunggu validasi A-45 |
| F-26 | Sedang | Cakupan per user bukan per penugasan; Klien + internal; auditor read-only | `01:76`; `04:49` | BR-GEN-09, BR-OPN-08 | Menunggu validasi A-46 |
| F-27 | Sedang | Purchasing Fase 1 tak terdefinisi; PRQ tanpa tolak/batal | `01:72,247,398` | Role Penindak Lanjut PR; purchasing/01 §3 | Menunggu validasi A-47 |
| F-28 | Rendah | SSO `sub` → banyak company; URL portal | `01:324`; `04:50` | Pemilih company; `/portal` | Menunggu validasi A-48 |
| F-29 | Rendah | Pembeda #4/#5 dijual tetapi Fase 2; Driver Fase 1 tanpa offline | `01:21-22,396` | Tag `[F2]`; draf lokal | Menunggu validasi A-49 |
| F-30 | Sedang | NFR konkurensi, berkas, pemantauan, browser tidak ada | `01:345-360` | NFR-13–16 | Diterapkan |

## 5. Temuan glosarium & riset

| ID | Tingkat | Temuan | Tindakan v0.3 | Status |
|---|---|---|---|---|
| G-1 | Tinggi | `return` kata kunci PHP; `stock_ledger`/`stock_movement` dua nama | `goods_return`; `stock_movement` | Diterapkan |
| G-2 | Sedang | ±50 istilah hilang | Ditambah, dikelompokkan per tema, kolom Rujukan | Diterapkan |
| R-1 | Sedang | Tidak ada riset risiko teknis tertinggi | [Riset §2.8](wms/02-riset-wms-sejenis.md#28-riset-teknis-untuk-part-3) daftar topik | Diterapkan (riset dilakukan di Part 3) |
| R-2 | Rendah | Tautan Odoo ke pull request GitHub | Diganti ke dokumentasi resmi | Diterapkan |
| R-3 | Rendah | Keputusan "tidak dipakai" tidak terekam | Baris eksplisit di tabel adopsi | Diterapkan |

## 6. Asumsi baru untuk divalidasi

Semua default sudah dipakai di dokumen v0.3; menolak satu asumsi berarti bagian terkait harus direvisi (lihat [matriks ketertelusuran](wms/04-keputusan-dan-asumsi.md#4-matriks-ketertelusuran)). Urutan prioritas validasi:

1. **Tema A — model stok & reservasi:** A-29, A-30, A-31, A-36, A-37, A-38. Menentukan ERD inti; tanpa ini Part 3 tidak bisa dimulai.
2. **Tema B — alur dokumen:** A-32, A-33, A-34, A-35, A-39, A-41, A-43, A-47. Menentukan BPMN Part 2.
3. **Tema C — proyek, opname, approval, akses:** A-40, A-42, A-45, A-46.
4. **Tema D — platform, SSO, PWA:** A-44, A-48, A-49.
5. Asumsi v0.2 yang masih terbuka: A-25, A-26, A-27, A-28.

Isu baru: O-12 migrasi data prototipe, O-13 legalitas tanda tangan digital, O-14 email & penyimpanan berkas, O-15 penyedia OTP.

## 7. Rekomendasi untuk Part 2

- Gambar BPMN **hanya** untuk alur yang asumsinya sudah *Setuju*; alur lain ditunda, bukan ditebak.
- Setiap diagram `.drawio` didampingi ringkasan teks per lane + tabel transisi (agar terbaca agen AI dan bisa di-diff).
- Urutan alur: (1) REQ→PCK→SJ→bukti terima→DSC, (2) GRN→QC→PUT→RTV, (3) ISU, (4) RET & pemilahan, (5) TRF antar gudang/proyek, (6) CNV, (7) AST, (8) OPN, (9) approval generik, (10) langganan.
- Tambahkan kolom **BR yang diuji** pada setiap gateway BPMN agar Part 4 bisa menurunkan kasus uji langsung.

## 8. Yang tidak diubah (sengaja)

- Keputusan D-01–D-27 tidak diubah, hanya diberi kolom tambahan.
- Nilai default asumsi v0.2 (trial 14 hari, tenggang 7 hari, 5 MB, 24 jam) dipertahankan.
- Isi riset produk sejenis tidak diubah selain tautan dan tabel adopsi.
