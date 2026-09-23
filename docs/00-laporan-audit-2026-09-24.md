# Laporan Audit & Kerja Semalam — 24 September 2026

**Versi:** 1.1
**Tanggal:** 24 September 2026
**Status:** selesai; seluruh asumsi baru sudah disetujui pemilik produk (§7)
**Dokumen terkait:** [README](README.md) · [Keputusan & Asumsi](wms/04-keputusan-dan-asumsi.md) · [Laporan validasi 23 Sep](00-laporan-validasi-2026-09-23.md) · [Modul Warehouse](wms/12-warehouse.md) · [Modul Stock](wms/13-stock.md) · [Modul Request](wms/14-request.md)

Audit menyeluruh atas dokumentasi, kode, dan infrastruktur, lalu seluruh temuannya diperbaiki dan empat modul berikutnya (Warehouse, Stock, Request, dan Picking/Shipment) dibangun. Urutannya mengikuti Part 4 dari awal: modul Access dan Master dilewati sebagai pembangunan, tetapi tetap diperiksa dan dirapikan.

---

## 1. Ringkasan

| Hal | Sebelum | Sesudah |
|---|---|---|
| Uji | 134 uji / 735 asersi | **318 uji / 1.796 asersi**, semua hijau |
| Modul selesai Fase 1 | Access, Master | Access, Master, **Warehouse**, **Stock**, **Request**, **Picking/Shipment** |
| Cacat kritis terbuka | 4 | 0 |
| Berkas kode | 150 di `app/` | **302 di `app/`**, 40 berkas uji |
| Laporan berjalan | 0 dari 7 | **7 dari 7**, semuanya bisa diekspor ke Excel |
| Asumsi menunggu validasi | A-50 | **0** — A-50, A-67, A-68 divalidasi; A-69, A-70, A-71 lahir dan langsung disetujui |

---

## 2. Empat cacat kritis yang ditemukan dan ditutup

### 2.1 Aksi Livewire tidak pernah melewati middleware tenant

Livewire mendaftarkan endpoint pembaruannya sendiri hanya dengan grup `web`, sedangkan seluruh middleware proyek dipasang pada route tenant. Akibatnya **setiap tombol Simpan di seluruh aplikasi** berjalan tanpa inisialisasi tenant, tanpa gerbang langganan ([BR-SUB-02](wms/05-aturan-bisnis.md#br-sub), [BR-SUB-03](wms/05-aturan-bisnis.md#br-sub)), dan tanpa pemisahan area internal dan portal klien ([BR-PRJ-07](wms/05-aturan-bisnis.md#br-prj)).

Tidak tertangkap uji karena seluruh uji Livewire memakai penguji bawaan yang melewati lapisan HTTP.

**Perbaikan:** endpoint didaftarkan ulang dengan susunan middleware yang sama seperti route tenant, dan pendaftarannya dilakukan sebelum Livewire sempat membuat route bawaannya, sehingga tidak ada jalur pintas yang tertinggal. Pemisah area didaftarkan sebagai middleware persisten. Enam uji baru menembak endpoint HTTP sungguhan.

### 2.2 Form pengguna bisa dipakai menaikkan hak sendiri

Metode simpan pada form pengguna tidak memanggil otorisasi sama sekali, dan pengenal pengguna bukan properti terkunci sehingga bisa ditimpa dari browser. Pemegang izin ubah pengguna dapat menambahkan role Admin Company untuk dirinya sendiri; izin penugasan role tidak pernah diperiksa.

**Perbaikan:** pengenal dikunci, otorisasi diulang di dalam metode simpan, dan izin penugasan role diperiksa begitu daftar penugasan berubah.

### 2.3 Form role punya lubang yang sama

Pemegang izin membuat role dapat mengganti pengenal role dari browser lalu menimpa permission role mana pun tanpa punya izin mengubah.

**Perbaikan:** pengenal dikunci dan otorisasi diulang di dalam metode simpan.

### 2.4 Seeder produksi membuat data demo

Perintah seed bawaan membuat company DEMO beserta database tenantnya tanpa syarat, menimpa password Super Admin dengan nilai yang tertanam di kode, dan mengembalikan langganan apa pun menjadi trial 14 hari.

**Perbaikan:** seeder dipecah. Seeder acuan hanya membuat paket dan Super Admin, menolak berjalan di luar lokal tanpa password dari `.env`, dan tidak pernah menimpa password yang sudah dipakai. Company demo pindah ke seeder tersendiri yang menolak berjalan di produksi. `ProductionSeeder` yang sudah dijanjikan dokumen akhirnya dibuat.

---

## 3. Temuan lain yang diperbaiki

### 3.1 Keamanan dan kebenaran

| Temuan | Perbaikan |
|---|---|
| Detail pengguna membocorkan data pengguna lain lewat pengenal yang bisa diubah | Pengenal dikunci; ditambah uji |
| Enam tempat memanggil konversi enum atas masukan pengguna sehingga memicu galat 500 | Satu pembantu `EnumInput` menolak nilai tak dikenal sebagai pelanggaran aturan biasa, plus aturan validasi enum di layar |
| Nama role kembar memicu galat integritas 500 | Menjadi pesan validasi di form dan pelanggaran aturan di aksi |
| Konversi satuan item dihapus fisik, melanggar P-03 | Kolom `is_active` baru; baris yang dilepas dari form dinonaktifkan |
| Menyimpan kategori satuan baru bisa membajak satuan milik kategori lain dan merusak seluruh konversi | Ditolak dengan pesan jelas |
| Cookie sesi tanpa penanda aman | Kunci lingkungan ditambahkan beserta catatan wajib `true` di produksi |
| Batas percobaan dua langkah meleset satu (6 kali, bukan 5) | Diperbaiki |
| Tautan undangan memakai domain pusat sehingga menghasilkan 404 | Dibangun dari host company |

### 3.2 Cakupan (BR-ACC-05)

Trait pembatas cakupan sudah ada sejak modul Access tetapi **tidak dipakai satu model pun**, padahal [AD-06](wms/08-arsitektur.md) menetapkan setiap query daftar memakainya. Lebih buruk lagi, arti "cakupan kosong" berbeda di tiga tempat: trait menganggapnya tidak melihat apa pun, sedangkan layar dan policy menganggapnya melihat semua.

**Perbaikan:** satu definisi tunggal. Daftar pengenal tidak pernah kosong; `null` berarti tidak dibatasi pada dimensi itu, baik karena cakupan menyeluruh maupun karena user memang tidak dibatasi di dimensi tersebut. Modul Warehouse menjadi pemakai pertama trait itu.

### 3.3 Kode mati yang dihidupkan

| Sebelumnya mati | Sekarang |
|---|---|
| Seluruh jalur verifikasi dua langkah: tidak ada satu baris pun yang pernah menulis rahasianya | Pengaturan di profil dengan kode QR, konfirmasi, delapan kode pemulihan sekali pakai, dan pematian yang menuntut password |
| Akun Super Admin dibuat seeder tetapi tidak ada route masuk | Halaman masuk di domain pusat beserta daftar company |
| Tujuh laporan hanya berupa tabel di dokumen | Satu layar laporan untuk semuanya, dengan penyaring dan ekspor Excel |
| Kolom tanda tangan dan foto item ada tetapi tidak bisa diisi | Unggah ke disk privat per company, disajikan lewat route berotorisasi |

### 3.4 Antarmuka

Bundel JavaScript produksi membawa **72 tautan mati berbahasa Inggris** dari template NexaDash, lengkap dengan halaman seperti "eCommerce Dashboard" dan "Kanban" yang tidak ada di aplikasi ini. Daftar itu diganti menu sungguhan yang dikirim server dan disaring menurut izin user. Teks pencarian cepat juga diterjemahkan.

Menu "Master data" tidak muncul untuk pemegang izin kategori item karena izin itu terlewat dari penjaga menu. Diperbaiki.

`robots.txt` mengizinkan seluruh perayap; back-office tenant sekarang menolak diindeks.

### 3.5 Pengujian dan konfigurasi

| Temuan | Perbaikan |
|---|---|
| Kunci aplikasi tidak dipatok, sehingga uji gagal pada klon bersih | Dipatok di konfigurasi uji |
| Pembersihan transaksi uji bisa terlewat bila sebuah uji mengakhiri tenancy | Pembatalan transaksi tidak lagi bergantung pada tenancy aktif |
| Folder uji unit terdaftar tetapi kosong | Delapan uji unit pertama untuk aturan murni |
| Jumlah permission diassert sebagai angka mati sehingga modul baru memecahkan uji yang tak berhubungan | Dihitung per modul |
| Kedaluwarsa tautan reset password ditulis dua kali dengan nilai berbeda | Satu sumber |
| Zona waktu aplikasi tidak pernah membaca kunci lingkungannya | Diperbaiki |
| Indeks hilang pada kolom yang rutin dicari dan difilter | Ditambahkan |

---

## 4. Modul Warehouse (modul ketiga)

Dibangun utuh mengikuti pola dua modul sebelumnya: spesifikasi, aturan, kode, layar, seeder, uji, lalu catatan implementasi.

- **Spesifikasi** [wms/12-warehouse.md](wms/12-warehouse.md) v0.2.
- **Aturan baru** [BR-WH-01 s.d. BR-WH-07](wms/05-aturan-bisnis.md#br-wh), karena sebelumnya aturan gudang tersebar di tujuh area lain dan tidak satu pun menyebut pembuatan bin virtual.
- **Enam tabel** persis dari model data: tipe gudang, gudang, zona, rak, level, bin.
- **Delapan aksi domain**, tiga policy, empat layar, dan menu "Gudang".
- **Gudang demo** `CKG`, `BKS`, `KRW1`, `KRW2` sesuai [akun uji](00-akun-uji.md) §2. `DemoSeeder` berhenti memakai pengenal gudang sementara.
- **28 uji** TC-WH-01 s.d. TC-WH-20.

Penyimpangan implementasi dicatat di [§13 spesifikasinya](wms/12-warehouse.md).

---

## 5. Modul Stock (modul keempat)

Modul terbesar dan paling sensitif: seluruh modul dokumen sesudahnya menulis stok hanya lewat modul ini.

- **Spesifikasi** [wms/13-stock.md](wms/13-stock.md) v0.2.
- **Aturan baru** [BR-LED-01 s.d. BR-LED-06](wms/05-aturan-bisnis.md#br-led) — aturan buku besar yang sebelumnya tersirat di P-01 dan AD-04 tetapi tidak pernah dituliskan.
- **Lima tabel**: kartu stok, saldo, reservasi, outbox kejadian, urutan nomor dokumen.
- **`StockLedger` satu-satunya pintu tulis saldo.** Arah ditentukan pasangan bin, jumlah selalu positif, baris dikunci `FOR UPDATE` dengan urutan tetap agar dua transfer berlawanan arah tidak saling mengunci.
- **P-01 dijaga dua lapis**: guard di model *dan* trigger `BEFORE UPDATE`/`BEFORE DELETE` di database. Jaminan sekuat ini tidak boleh bergantung pada satu lapisan yang bisa dilewati query builder atau seeder.
- **Lima layar** dan menu "Stok"; lima permission baru. Tidak ada `stock.post` — memposting stok adalah akibat dokumen.
- **46 uji** TC-STK-01 s.d. TC-STK-33.

**[BR-GEN-04](wms/05-aturan-bisnis.md#br-gen) akhirnya ditegakkan.** `StockGuard` menolak menonaktifkan gudang, bin, atau item yang masih bersaldo atau punya reservasi aktif — sisa pekerjaan yang tercatat di modul Warehouse.

Penyimpangan implementasi dicatat di [§13 spesifikasinya](wms/13-stock.md).

---

## 6. Modul Request (modul kelima)

Modul pertama yang melibatkan klien sebagai **pihak kedua**, bukan sekadar pembaca.

- **Spesifikasi** [wms/14-request.md](wms/14-request.md) v0.2.
- **Tidak ada aturan baru:** BR-REQ-01 s.d. BR-REQ-15 sudah lengkap sejak Part 2 dan kini ditegakkan di kode.
- **Dua tabel**, sebelas aksi domain, lima layar termasuk dua halaman portal klien, empat belas permission.
- **REQ tidak menyentuh kartu stok.** Satu-satunya sentuhannya ke gudang adalah reservasi lunak saat disetujui, dan pelepasannya saat dibatalkan, ditutup dengan sisa, penggantian ditolak, atau pembatalan baris dikonfirmasi.
- **Tiga interaksi klien yang punya tenggat:** tambahan setelah persetujuan melahirkan REQ Tambahan bernomor sendiri; diam sampai tenggat penggantian dianggap setuju; pembatalan baris dua langkah — klien meminta, staf mengonfirmasi.
- **36 uji** TC-REQ-01 s.d. TC-REQ-26.

Penyimpangan implementasi dicatat di [§13 spesifikasinya](wms/14-request.md).

---

## 7. Modul Picking & Shipment (modul keenam)

Modul tempat barang benar-benar bergerak: empat dokumen dalam satu rantai — PCK, SJ, bukti terima, dan DSC.

- **Spesifikasi** [wms/15-picking-shipment.md](wms/15-picking-shipment.md) v0.2.
- **Tidak ada aturan baru:** BR-SJ-01 s.d. BR-SJ-10 sudah lengkap sejak Part 2 dan kini ditegakkan di kode.
- **Sepuluh tabel**, tujuh aksi domain, enam layar, dua belas permission.
- **Buku besar stok diperluas dua kali** — perubahan kondisi di bin yang sama (dituntut BR-SJ-10, akan dipakai QC) dan kejadian tanpa pergerakan (dituntut BR-SJ-04). Keduanya tetap lewat satu pintu; tidak ada jalur tulis baru.
- **Yang paling penting dari modul ini:** kurang dan rusak tidak hilang dari pembukuan. Keduanya tetap tercatat milik gudang asal di bin *Dalam Perjalanan* sampai DSC diselesaikan.
- **44 uji** TC-PCK, TC-SJ, dan TC-DSC.

Penyimpangan implementasi dicatat di [§13 spesifikasinya](wms/15-picking-shipment.md).

---

## 8. Paket yang dipasang

Keempatnya sudah diputuskan [AD-08](wms/08-arsitektur.md) dan [AD-09](wms/08-arsitektur.md) serta diverifikasi versinya pada 23 September; malam ini hanya dijalankan.

| Paket | Versi | Dipakai untuk |
|---|---|---|
| `maatwebsite/excel` | 4.0.3 | Ekspor tujuh laporan |
| `bacon/bacon-qr-code` | 3.1.1 | Kode QR pengaturan dua langkah |
| `barryvdh/laravel-dompdf` | 3.1.2 | Template dokumen (belum dipakai) |
| `picqer/php-barcode-generator` | 3.3.0 | Label bin (menunggu [O-09](wms/04-keputusan-dan-asumsi.md#o-09)) |

---

## 9. Yang menunggu keputusan Anda

| ID | Isi | Kenapa saya ambil sendiri |
|---|---|---|
| [A-67](wms/04-keputusan-dan-asumsi.md#a-67) | Kolom `bins.count_flag` — penanda bin perlu dihitung | Istilahnya sudah ada di Glosarium dan dipakai BR-SJ-02, tetapi kolomnya tidak pernah ada di model data. Tanpa kolom itu aturannya tidak bisa diwujudkan |
| [A-68](wms/04-keputusan-dan-asumsi.md#a-68) | Berkas disimpan di disk lokal per company dulu | Anda memilih ini semalam; penyedia S3 produksi masih menunggu [O-14](wms/04-keputusan-dan-asumsi.md#o-14) |
| — | Satu bin On-site per **proyek**, bukan per Gudang Site | Blueprint §6.3 menulis "satu per proyek", sedangkan [A-40](wms/04-keputusan-dan-asumsi.md#a-40) memperbolehkan satu proyek punya beberapa Gudang Site. Saya ikut bunyi Blueprint |
| [A-71](wms/04-keputusan-dan-asumsi.md#a-71) | Tombol Lepas reservasi manual sebagai katup darurat | BR-STK-05 dan BR-STK-16 menulis pelepasan hanya lewat aksi dokumen, sedangkan modul dokumennya belum lengkap di Fase 1. Tanpa tombol ini reservasi menggantung mengunci stok selamanya |
| — | Kode master disimpan huruf besar, termasuk kode kategori satuan dan tipe gudang | BR-MST-01 menuntut huruf besar, Katalog Status menulisnya huruf kecil. Pencocokan dibuat mengabaikan besar-kecil huruf |

[A-50](wms/04-keputusan-dan-asumsi.md#a-50) masih menunggu dari sebelumnya; tidak memblokir apa pun yang dikerjakan malam ini.

---

## 10. Yang belum dikerjakan

Bukan karena terlewat, melainkan karena menunggu keputusan atau modul lain.

1. **Label bin barcode dan QR** — paketnya sudah terpasang, ukuran kertas dan jenis printer menunggu [O-09](wms/04-keputusan-dan-asumsi.md#o-09).
2. **Impor Excel master, gudang, dan bin** — menunggu [O-12](wms/04-keputusan-dan-asumsi.md#o-12) tentang migrasi data prototipe.
3. **Penutupan Gudang Site otomatis** saat proyek ditutup ([BR-PRJ-04](wms/05-aturan-bisnis.md#br-prj)) — penjagaan saldonya sudah ada di `StockGuard`, pemicunya menyusul di modul `project`.
4. **Pengiriman kejadian stok lewat HTTP** ke Akuntansi dan Purchasing — Fase 3; Fase 1 berhenti di tabel outbox.
5. **Notifikasi** untuk kejadian yang tercatat di §8 setiap spesifikasi modul — menunggu modul notifikasi Fase 2.
6. **Rencana kebutuhan material** `[F2]` — tabelnya stub, layarnya belum.
7. **Aturan approval demo** yang dijanjikan [akun uji](00-akun-uji.md) §5 — menunggu modul Approval. Sampai modul itu ada, REQ `pending_approval` disetujui langsung oleh pemegang `request.approve`, yaitu Admin Company.
8. **Pembuatan TRF dan PRQ otomatis** dari baris REQ bersumber transfer atau pembelian ([BR-REQ-05](wms/05-aturan-bisnis.md#br-req)) — menunggu modul `transfer` dan `purchase_request`.
9. **Konfirmasi dan keberatan penerimaan** ([BR-REQ-10](wms/05-aturan-bisnis.md#br-req)) — kolomnya sudah ada di bukti terima, layarnya menyusul bersama portal pemohon.
10. **Halaman penerima bertoken dan pengiriman OTP** ([O-06](wms/04-keputusan-dan-asumsi.md#o-06)) — token, OTP, dan pembatasan percobaannya sudah berfungsi; halaman publik dan kanal WhatsApp/SMS menyusul.
11. **Bukti terima per unit** untuk item berserial dan per potong — tabelnya ada, pengisiannya menunggu pemindaian di PWA.
12. **Butir checklist pemilik produk** di [00-checklist-persiapan](00-checklist-persiapan.md) §1 dan §2 — semuanya keputusan Anda, bukan pekerjaan teknis.

---

## 11. Verifikasi

```
py -3 docs/diagram/_verify.py     37 berkas, 1466 link, 0 rusak, 294 ID, 0 tak terdefinisi, 0 ganda, 0 > 450 baris
py -3 docs/diagram/_generate_erd.py   112 tabel, 08a–08c dan seluruh .drawio dibuat ulang
php83 artisan tenants:migrate     migrasi 000031, 000040, 000050, 000060, dan 000070 terpasang
php83 artisan tenants:seed        4 gudang, 45 bin, 14 user, 85 permission, master lengkap
php83 artisan test                318 uji / 1.796 asersi hijau
npm run build                     berhasil
```

Tidak ada yang di-commit ke git, sesuai aturan kerja yang berlaku.
