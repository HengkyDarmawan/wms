# Laporan Validasi Asumsi A-25–A-49

**Versi:** 1.3
**Tanggal:** 23 September 2026
**Status:** selesai; A-50 menunggu validasi pemilik produk; v1.1–1.3 menambah §8 diskusi lanjutan (D-28, D-29, A-51–A-66)
**Dokumen terkait:** [README](README.md) · [Keputusan & Asumsi](wms/04-keputusan-dan-asumsi.md) · [Laporan audit v0.3](00-laporan-audit-dokumentasi.md)

## 1. Ringkasan

| Hasil | Jumlah | ID |
|---|---|---|
| Setuju | 24 | A-25, A-26, A-27, A-28, A-29, A-30, A-31, A-32, A-33, A-34, A-35, A-36, A-37, A-38, A-39, A-41, A-42, A-43, A-44, A-45, A-46, A-47, A-48, A-49 |
| Ubah | 1 | [A-40](wms/04-keputusan-dan-asumsi.md#a-40) |
| Hapus | 0 | — |
| Baru (*Perlu validasi*) | 1 | [A-50](wms/04-keputusan-dan-asumsi.md#a-50) |
| Diganti | 1 | [A-16](wms/04-keputusan-dan-asumsi.md#a-16) → [A-42](wms/04-keputusan-dan-asumsi.md#a-42) |
| Aturan baru dari pemilik produk | 1 | [BR-GEN-11](wms/05-aturan-bisnis.md#br-gen) |

Validasi dilakukan per tema (A: model stok & reservasi → B: alur dokumen → C: proyek/opname/approval/akses → D: platform/SSO/PWA), tiap asumsi dengan pilihan Setuju / Ubah / Hapus dan penjelasan dampak bila ditolak. Semua nilai default yang dipakai draf Part 2 dan Part 3 kini berlaku setara keputusan; Part 2 dan Part 3 dibuat ulang menjadi v0.2.

## 2. Hasil per asumsi

| ID | Tema | Hasil | Aturan / bagian yang kini berlaku |
|---|---|---|---|
| A-29 | A | Setuju | BR-STK-02, 08, 13, 14; BR-AST-01–02; KS `stock_status` |
| A-30 | A | Setuju | BR-STK-03–05; BR-REQ-05, 09; P-02 v0.3 |
| A-31 | A | Setuju | BR-REQ-04; relasi REQ : PCK 1 : n |
| A-36 | A | Setuju | BR-STK-09–11; matriks kombinasi 05 §15 |
| A-37 | A | Setuju | BR-STK-06–07; BR-GEN-08 |
| A-38 | A | Setuju | BR-REQ-06; KS `line_ownership` |
| A-32 | B | Setuju | KS 2.9 (ISU); BR-PRJ-08; kejadian `material_consumed` |
| A-33 | B | Setuju | 05 §1; BR-RET-01; status *Dibatalkan* pada SJ/PCK/PUT/PRQ/WST |
| A-34 | B | Setuju | BR-GRN-01–04; KS 2.5, 2.16 (RTV) |
| A-35 | B | Setuju | BR-SJ-02, 06, 07; KS 2.3–2.4 (DSC) |
| A-39 | B | Setuju | BR-REQ-01–03; item `provisional` |
| A-41 | B | Setuju | BR-SJ-05; penyedia OTP tetap di O-15 |
| A-43 | B | Setuju | BR-GEN-06; AD-13 |
| A-47 | B | Setuju | BR-REQ-08; BR-SJ-03; KS 2.15 |
| A-25 | B | Setuju | BR-SJ-04; BR-STK-14; BR-PRJ-03, 05; matriks kejadian 05 §14 |
| A-26 | B | Setuju | BR-RET-03, 05 |
| A-40 | C | **Ubah** | Lihat §3 |
| A-42 | C | Setuju | BR-OPN-01–04; menggantikan A-16 |
| A-45 | C | Setuju | BR-APR-01–10; BR-REQ-07 |
| A-46 | C | Setuju | BR-GEN-09; BR-OPN-08 |
| A-27 | C | Setuju | BR-SUB-04 |
| A-28 | D | Setuju | Blueprint §13; O-02 |
| A-44 | D | Setuju | BR-SUB-02–03 |
| A-48 | D | Setuju | BR-SUB-05; BR-PRJ-07 |
| A-49 | D | Setuju | BR-SJ-08; Blueprint §1, §18 |

## 3. A-40 diubah dan A-50 baru: pemindahan material antar titik dalam proyek

**Konteks dari pemilik produk.** Pada proyek konstruksi pipa, material tidak berada di satu jalur; ia dipindahkan mengikuti galian. Dibutuhkan status/riwayat bahwa barang "dipindahkan ke tempat lain untuk melanjutkan proyek" **di dalam** proyek yang sama.

**Fakta dokumen yang sudah mendukung.** ERD `warehouses.project_id` dengan relasi proyek → gudang 1 : n (satu proyek boleh punya beberapa Gudang Site); guard `transfer.create` di KS 2.7 sudah sah untuk gudang asal ≠ tujuan meskipun proyek sama; pola `self_delivered` sudah ada pada RET.

**Keputusan.**
- **A-40 (Ubah):** satu proyek boleh punya **banyak Gudang Site** (satu per titik/segmen lokasi). Guard penutupan "saldo Gudang Site = 0" dan checklist penutupan berlaku untuk **semua** Gudang Site proyek (BR-PRJ-02, BR-PRJ-04, Blueprint §6.2, §6.9).
- **A-50 (baru, *Perlu validasi*):** pemindahan dalam proyek = `TRF` antar Gudang Site proyek yang sama dengan **jalur ringan**: tanpa approval bila tidak ada aturan (A-08), SJ boleh **diantar sendiri** (`self_delivered` + nama pembawa, pengecualian BR-SJ-07), GRN tujuan = konfirmasi PIC titik tanpa QC, PCK tetap dibuat. Riwayat lokasi per barang = kartu stok + sub-tampilan *Di Gudang Site* per titik + tab Riwayat proyek. **Tidak ada kode dokumen atau status baru.**

**Alternatif yang ditolak:** titik = zona/bin dalam satu Gudang Site dengan dokumen pemindahan internal baru (butuh kode dokumen, mesin status, tabel, dan alur baru).

**Dampak yang sudah diterapkan:** BR-RET-02, BR-SJ-07, 05 §1 (relasi TRF); KS 2.3 dan 2.7 (guard); Blueprint §6.2, §6.9, §7, §18; glosarium (*Gudang Site*, *Transfer*, *Diantar Sendiri*); ERD `shipments.self_delivered`, `carried_by_name`; alur 5 BPMN gateway *Dalam proyek & diantar sendiri?*.

## 4. Aturan UI dari pemilik produk (BR-GEN-11)

Saat meninjau rencana, pemilik produk menetapkan: **setiap field wajib ditandai `*`** di form, dan pada setiap aksi **tolak/batal** kolom **Keterangan** (teks bebas) **tidak wajib**; **Alasan** dari master Alasan tetap wajib (dikonfirmasi 23 Sep 2026). Dicatat sebagai [BR-GEN-11](wms/05-aturan-bisnis.md#br-gen) (bukan asumsi, karena sudah diputuskan langsung), rujukan silang di BR-GEN-02, catatan di KS §1, Blueprint §6.3a, glosarium (*Keterangan* = `notes`), dan template spesifikasi modul (kolom *Wajib* pada tabel field).

## 5. Berkas yang berubah

| Berkas | Versi | Perubahan utama |
|---|---|---|
| `wms/04-keputusan-dan-asumsi.md` | 0.3 → 0.4 | Kolom Validasi 25 asumsi; A-16 diganti A-42; §2.4 A-50; matriks §4 |
| `wms/05-aturan-bisnis.md` | 0.3 → 0.4 | BR-GEN-11 baru; BR-GEN-02, BR-SJ-07, BR-RET-02, BR-PRJ-02, BR-PRJ-04; §1 relasi TRF |
| `wms/06-katalog-status-dan-enum.md` | 0.3 → 0.4 | §1 label & catatan alasan/keterangan; guard KS 2.3, 2.7 |
| `wms/01-blueprint.md` | 0.3 → 0.4 | §6.2, §6.3a, §6.9, §7 (TRF), §18, §19, §20 (jumlah baris tetap 443) |
| `wms/03-glosarium.md` | 0.3 → 0.4 | Gudang Site, Alasan, *Keterangan*, Transfer, *Diantar Sendiri* |
| `wms/_template-spesifikasi-modul.md` | template 0.2 | §6 catatan BR-GEN-11; kolom *Wajib* pada tabel field |
| `diagram/_generate.py` → `wms/07*`, `bpmn-05` | 0.1 → 0.2 | Alur 5: gateway & langkah *self_delivered*; kepala menyebut validasi; label "Asumsi yang dipakai" |
| `diagram/_generate_erd.py` → `wms/08a–c`, `erd-outbound` | 0.1 → 0.2 | `shipments.self_delivered`, `carried_by_name`; catatan `warehouses.project_id` |
| `wms/08-arsitektur.md` | 0.1 → 0.2 | Kepala status dan §12 langkah 1 (validasi selesai, A-50 tertunda) |
| `00-laporan-audit-dokumentasi.md` | tetap | Anchor ke 04 §2.3 mengikuti judul baru |
| `README.md` | 0.3 → 0.4 | Status, struktur, tabel part, jalur baca, changelog |
| `diagram/_verify.py` | baru | Skrip verifikasi dokumentasi |

Tidak ada keputusan D-xx yang diedit; A-40 tidak mengubah D-15 (Gudang Site tetap terikat proyek).

## 6. Audit ringan setelah perubahan

**Skrip** `py -3 docs/diagram/_verify.py` (setelah laporan ini ditulis):

| Pemeriksaan | Hasil |
|---|---|
| Berkas Markdown diperiksa | 22 |
| Link relatif diperiksa | 762 |
| Link rusak | 0 |
| ID terdefinisi | 234 (D 27 · A 50 · O 15 · P 12 · NFR 16 · BR 99 · AD 15) |
| ID dirujuk tak terdefinisi | 0 |
| ID ganda | 0 |
| Berkas > 450 baris | 0 (terbesar: Blueprint 443) |

Satu link rusak ditemukan dan diperbaiki selama proses: anchor lama `#23-baru-dari-audit-v03--menunggu-validasi` di laporan audit v0.3, karena judul §2.3 di 04 berubah menjadi "disetujui 23 Sep 2026".

**Pemeriksaan manual:**
- 16 kode dokumen (REQ, PCK, SJ, DSC, GRN, PUT, RTV, TRF, RET, ISU, CNV, AST, ADJ, OPN, WST, PRQ) masing-masing ada tepat satu kali di Blueprint §7, glosarium §5, dan KS §2, serta muncul di 07* dan 08*. Tidak ada kontradiksi baru.
- 16 kejadian stok di `akuntansi/01` dan `purchasing/01` semuanya ada di matriks 05 §14, dan setiap kejadian punya satu pemicu. Catatan lama (bukan temuan baru): `asset_lost_or_damaged` dipicu oleh dua aksi (tandai hilang / inspeksi grade C–D) yang sengaja disatukan karena tidak menggerakkan ledger.
- Generator berjalan tanpa error; keluaran yang berubah hanya `bpmn-05`, `erd-outbound`, dan kepala 07*/08*.

## 7. Masih terbuka

| ID | Hal | Penanggung jawab | Target |
|---|---|---|---|
| [A-50](wms/04-keputusan-dan-asumsi.md#a-50) | Jalur ringan transfer dalam proyek (cara kirim *diantar sendiri*) | Pemilik produk | Sebelum modul Transfer (Part 4) |
| [O-12](wms/04-keputusan-dan-asumsi.md#o-12) | Migrasi data prototipe | Pemilik produk | Sebelum Part 4 |
| [O-13](wms/04-keputusan-dan-asumsi.md#o-13) | Legalitas tanda tangan digital | Pemilik produk | Sebelum Part 4 |
| [O-15](wms/04-keputusan-dan-asumsi.md#o-15) | Penyedia OTP bukti terima | Pemilik produk | Sebelum Part 4 |
| [O-05](wms/04-keputusan-dan-asumsi.md#o-05), [O-14](wms/04-keputusan-dan-asumsi.md#o-14) | Domain/SSL, email & penyimpanan berkas | Pemilik produk / Tim teknis | Part 3 |
| [O-02](wms/04-keputusan-dan-asumsi.md#o-02) | Pendaftaran client SSO NXTG | Tim SSO | Sebelum Fase 3 |
| [O-03](wms/04-keputusan-dan-asumsi.md#o-03), [O-04](wms/04-keputusan-dan-asumsi.md#o-04), [O-10](wms/04-keputusan-dan-asumsi.md#o-10) | WhatsApp & RFID | Pemilik produk | Sebelum Fase 2 |
| [O-07](wms/04-keputusan-dan-asumsi.md#o-07), [O-08](wms/04-keputusan-dan-asumsi.md#o-08), [O-09](wms/04-keputusan-dan-asumsi.md#o-09), [O-11](wms/04-keputusan-dan-asumsi.md#o-11) | Brand, harga, label, privasi | Pemilik produk | Part 4–5 |
| Keterangan di [00-audit](00-audit/README.md) | Belum diisi pemilik produk | Pemilik produk | — |

## 8. Tambahan 23 Sep 2026: diskusi lanjutan pemilik produk

Setelah validasi, pemilik produk mengajukan lima rangkaian pertanyaan (purchasing, permintaan klien, pengiriman, saran perbaikan, audit). Semua jawaban disetujui langsung, sehingga dicatat sebagai keputusan/asumsi **Setuju**, bukan *Perlu validasi*.

| ID | Tema | Isi singkat | Aturan / bagian |
|---|---|---|---|
| [D-28](wms/04-keputusan-dan-asumsi.md#d-28) | Purchasing | Modul Purchasing memakai mesin approval WMS; approval nilai uang hanya di PO Purchasing | BR-APR-07, Blueprint §8.1, §15, 08-arsitektur §4 |
| [A-51](wms/04-keputusan-dan-asumsi.md#a-51) | Purchasing | Catatan pemesanan per vendor/toko online di bawah PRQ; satu PRQ → banyak vendor; baris boleh dipecah | KS 2.15, BR-GRN-01, 05 §1, ERD `purchase_request_orders` |
| [A-52](wms/04-keputusan-dan-asumsi.md#a-52) | Purchasing | Jenis vendor (perusahaan/toko/toko online/perorangan) + vendor tetap per item tanpa harga | Blueprint §6.3a, ERD `item_vendors`, enum `vendor_type` |
| [A-53](wms/04-keputusan-dan-asumsi.md#a-53) | Purchasing | Vendor sementara dibuat saat memesan, dilengkapi Admin | enum `vendor_status` |
| BR-REQ-11 | Purchasing | Draf PRQ harian dari titik pesan ulang, ditinjau Kepala Gudang | KS 2.15 `draft` |
| [A-54](wms/04-keputusan-dan-asumsi.md#a-54) | Permintaan klien | Klien boleh menambah baris (tidak mengurangi); setelah disetujui → REQ Tambahan | BR-REQ-12, KS 2.1 |
| [A-55](wms/04-keputusan-dan-asumsi.md#a-55) | Permintaan klien | Penggantian item diberitahukan; klien boleh menolak dalam 1 hari | BR-REQ-13 |
| [A-56](wms/04-keputusan-dan-asumsi.md#a-56) | Pengiriman | Pecah baris antar gudang; tiap gudang kirim langsung (hemat ongkir) | BR-REQ-04 |
| [A-57](wms/04-keputusan-dan-asumsi.md#a-57) | Pengiriman | Cara kirim eksplisit: kendaraan sendiri / ekspedisi / diantar sendiri; ongkir di Akuntansi | BR-SJ-07, KS 2.3, akuntansi/01 |
| BR-SJ-09 | Pengiriman | Satu SJ memuat beberapa REQ ke tujuan sama; saran gabung; status per REQ di portal | 05 §1 |
| [A-58](wms/04-keputusan-dan-asumsi.md#a-58) | Stok | Tutup periode stok (tanggal kunci) | BR-STK-15 |
| [A-59](wms/04-keputusan-dan-asumsi.md#a-59) | Stok | Reservasi menggantung: laporan & peringatan 7 hari | BR-STK-16 |
| [A-60](wms/04-keputusan-dan-asumsi.md#a-60) | Permintaan klien | SLA tinjau staf 1 hari kerja; tanggal janji per baris di portal | BR-REQ-14 |
| [A-61](wms/04-keputusan-dan-asumsi.md#a-61) | Permintaan klien | Permintaan pembatalan baris oleh klien setelah disetujui, dikonfirmasi staf | BR-REQ-15 |
| BR-OPN-09, BR-OPN-10 | Audit | Pemisahan tugas opname; pemeriksaan mendadak tanpa pembekuan | KS 2.13, enum `count_type` |
| [A-62](wms/04-keputusan-dan-asumsi.md#a-62) | Proyek [F2] | Rencana kebutuhan material (BoQ kuantitas) per proyek; stub di F1 | BR-PRJ-09 |
| Luar lingkup | — | Transfer stok antar company satu grup; biaya ongkir | Blueprint §3 |
| [A-63](wms/04-keputusan-dan-asumsi.md#a-63) | Pengiriman ke klien | Konfirmasi atau keberatan dalam 3 hari; otomatis bila klien mengisi bukti terima sendiri | BR-REQ-10, KS 2.4 |
| [A-64](wms/04-keputusan-dan-asumsi.md#a-64) | Pengiriman ke klien | Bukti terima per baris baik/rusak/kurang & per unit; DSC jenis kurang/rusak, disposisi kirim pengganti, keputusan klien; laporan posisi barang rusak | BR-SJ-05, BR-SJ-06, BR-SJ-10, KS 2.3–2.4 |
| [A-65](wms/04-keputusan-dan-asumsi.md#a-65) | Pengiriman ke klien | Barang rusak: Dalam Perjalanan berkondisi Rusak, dibawa balik driver → bin Retur; ekspedisi → RET + klaim; tidak pernah jadi stok klien/site | BR-SJ-10, Blueprint §6.6 |
| [A-66](wms/04-keputusan-dan-asumsi.md#a-66) | Aset disewakan | Meter jam/km, umur harapan & sisa umur %, skor kondisi & riwayat; penyusutan di Akuntansi dengan data WMS | BR-AST-08, akuntansi/01 §4.2 |
| [D-29](wms/04-keputusan-dan-asumsi.md#d-29) | Peta rilis | WMS inti → Purchasing inti (1b) → WhatsApp (2a) → PWA offline (2b) → SSO & Purchasing lengkap (3); login lokal dulu; WA didaftarkan awal Fase 2a | Blueprint §18, purchasing/01 §4, [00-checklist-persiapan](00-checklist-persiapan.md) |

**Berkas berubah (v0.5–v0.6):** 04 (0.5), 05 (0.5), 06 (0.5), 03 (0.5), 01 (0.5, 447 baris), purchasing/01 (0.2), akuntansi/01, 08-arsitektur (0.3), generator Part 2/3 → 07*/08* v0.3 (ERD 110 tabel), README (0.5). Skrip verifikasi dijalankan ulang setelah perubahan; hasilnya di bawah.

| Pemeriksaan (v1.2) | Hasil |
|---|---|
| Berkas Markdown | 22 |
| Link relatif diperiksa | 981 |
| Link rusak | 0 |
| ID terdefinisi | 264 (D 28 · A 66 · O 15 · P 12 · NFR 16 · BR 112 · AD 15) |
| ID tak terdefinisi / ganda | 0 / 0 |
| Berkas > 450 baris | 0 (Blueprint 447) |

## 9. Langkah berikutnya

Part 4 (spesifikasi modul) dimulai atas permintaan pemilik produk, dengan urutan di [08-arsitektur §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4) dan template [`_template-spesifikasi-modul.md`](wms/_template-spesifikasi-modul.md) v0.2 (kolom *Wajib* dan aturan BR-GEN-11 sudah tersedia).
