# Laporan Malam 30 Sep → 1 Okt 2026

**Versi:** 1.0
**Tanggal:** 1 Oktober 2026
**Status:** laporan pagi untuk pemilik produk setelah kerja malam tanpa pendamping (Prompt Malam). Lanjutan [Laporan Progres](00-laporan-progres-2026-09-24.md) (berkas itu sudah mendekati 450 baris).
**Dokumen terkait:** [README](README.md) · [Asumsi lanjutan §2.47–§2.54](wms/04b-asumsi-lanjutan.md) · [Tata letak barang](wms/12a-tata-letak-barang.md) · [Pola pilihan §6.5](wms/16-shared-laporan-berkas.md)

> **Penting — belum di-push.** Pengaman izin menolak `git push` sejak Bagian 2. Semua commit di bawah
> (mulai `d3aab5a`) **hanya ada di laptop ini**, di cabang `main`. Jalankan `git push origin main` sebelum
> pindah mesin (lihat §5).

## 1. Hasil per langkah

| Langkah / bagian | Status | Commit | Uji penuh |
|---|---|---|---|
| 0 · Badge jabatan di pilihan Atasan langsung ([A-358](wms/04b-asumsi-lanjutan.md#a-358)) | selesai (di-push) | `73026ab` | 844/844 |
| 0 · Kemasan item sebagai kalimat, T-10 ([A-355](wms/04b-asumsi-lanjutan.md#a-355)–A-357; sesi lain) | selesai (di-push) | `6ce113e` | 844/844 |
| 0 · `tenants:migrate` DB demo | selesai (tanpa fresh; migrasi 000490–000520 terpasang) | — | — |
| 1 · Tata letak Bagian 2 — gabung/pisah & hapus bin, lebar bin, area lantai (A-359–A-364) | selesai | `d3aab5a` | 854/854 |
| 1 · Bagian 3 — tempat simpan barang, *Khusus barang ini*, mode Tata letak, impor, cetak denah (A-365–A-372) | selesai | `c5a6bfd` | 867/867 |
| 1 · Bagian 4 — put-away pindai, *Menunggu dimasukkan*, halaman Isi Bin, QR bin berisi tautan (A-373–A-379) | selesai | `22cd39c` | 877/877 |
| 1 · Bagian 5 — Setup awal ①–⑤, saldo awal tanpa kode bin, kolom Lokasi (A-380–A-382) | selesai | `1350495` | 881/881 |
| 2 · Inti `<x-pilih>` (kelompok, badge, aria, modal, cari ke server) + P2 form pengguna (A-383–A-387) | selesai | `fd77c3b` | 896/896 |
| 2 · Pengaturan & pengguna (A-388) | selesai | `f8dfa93` | 896/896 |
| 2 · Master data (A-389, A-390) | selesai | `ba5cfe6` | 898/898 |
| 2 · Barang masuk — GRN, Transfer, Retur proyek, RTV (A-391) | selesai | `9887b29` | 903/903 |
| 2 · Barang keluar — REQ, SJ, Petakan, portal (A-392) | selesai | `a59ed77` | 907/907 |
| 2 · Pembelian — PRQ, PO, harga beli (A-393) | selesai | `0a05350` | 909/909 |
| 2 · Opname & penyesuaian (A-394) | selesai | `e3e2e35` | 911/911 |
| 2 · Di proyek — ISU, CNV, WST, Aset, Serah terima (A-395) | selesai | `a7bf2f2` | 915/915 |
| 2 · Laporan — penyaring laporan & stok (A-396) | selesai | `df167c6` | 918/918 |
| 2 · Portal klien — uji keamanan cari ke server (TC-ACC-52) | selesai (tanpa ubah kode) | `a3b7ad3` | 919/919 |
| 2 · Denah/gudang (A-397) | selesai | `d4ed1f0` | 921/921 |
| 2 · Template & Platform (A-398) | selesai | `9a3d45d` | 922/922 |

Tidak ada bagian yang gagal atau dikembalikan. Pilihan yang bisa dicari: dari 256 `<select>` hasil audit, **95 diganti**
(125 pemakaian `<x-pilih>`); **161 sisanya sengaja tetap** select biasa (enum, status, alasan, daftar ≤ ±8, dan pilihan
Alpine pendek). Belum diganti karena butuh varian Alpine: bin di detail Put-away, Pilah retur, Picking; batang di Konversi.

Pemeriksaan di browser (Chrome headless, data DEMO dibaca saja, tanpa menyimpan): form pengguna (badge, kelompok atasan,
cari ke server), Denah mode Tata letak, Cetak denah, Menunggu dimasukkan, Isi Bin, Setup awal, Saldo stok, form GRN, REQ,
SJ, PRQ, PO, penyesuaian, konversi — terang/gelap/lebar HP 390 px, **tanpa galat JavaScript**. Dua polesan hasil cek ikut
diperbaiki (teks nilai terpilih saat mengetik — A-390; lebar kotak di tabel baris pada HP). Kamera HP sungguhan belum dicoba.

## 2. Uji penuh terakhir

`php artisan test` (satu proses) pada `9a3d45d`: **922/922 lulus** (11.026 asersi, ±7,4 menit). `npm run build` sukses; `py -3 docs/diagram/_verify.py` HASIL: OK.

## 3. Perlu keputusan pemilik

Semua berstatus *Perlu validasi* di [04b](wms/04b-asumsi-lanjutan.md); opsi yang dipilih malam ini sudah jalan. Cukup jawab nomor yang ingin diubah.

**Kemasan & atasan**
- [A-355](wms/04b-asumsi-lanjutan.md#a-355) (rincian) — pesan galat & contoh "terima 5 DUS → stok +60 BOX" memakai kemasan terbesar.
- [A-357](wms/04b-asumsi-lanjutan.md#a-357) — *Kemasan lain…* di GRN/REQ/RET memakai kalimat yang sama dan bisa diingat untuk item.

**Struktur rak (Bagian 2)**
- A-359 — gabung wajib alasan; samping = satu tingkat berurutan, atas = nomor petak sama (bin utama paling bawah); ditolak bila ada tugas picking/put-away terbuka.
- A-360 — data lama "bin ikut terpakai" menjadi *gabung sementara, samping*; pisah otomatis saat bin utama kosong hanya untuk gabung sementara.
- A-361 — bila satu petak gabungan tanpa kapasitas, seluruh gabungan dianggap tanpa batas.
- A-362 — bin sistem, area lantai, bin beku, dan bin dalam gabungan tidak bisa dihapus; bin yang labelnya pernah dicetak tetap boleh dihapus (cetak label bin tidak tercatat).
- A-363 — lebar bin digambar sebanding panjang rak; petak tanpa lebar berbagi sisa rata (min. 0,1 m).
- A-364 — kapasitas area lantai bawaan kosong = tanpa batas (dulu 1); berat & volume disimpan tetapi belum ditegakkan.
- Catatan (keputusan #10, sudah disetujui): kapasitas kini dihitung dari seluruh isi bin, jadi bin campuran bisa ditolak lebih cepat.

**Tempat simpan (Bagian 3)**
- A-365 — maks. 50 tempat per barang per gudang; kartu tampil untuk pemegang `bin.view`.
- A-366 — satu tempat Khusus = satu barang; tumpang-tindih sebagian dibiarkan; penyesuaian diperiksa saat diajukan saja.
- A-367 — *Buka tempat khusus*: satu alasan per layar; impor saldo awal tidak bisa membuka tempat khusus.
- A-368 — bin yang hanya menjadi tempat simpan tetap boleh dihapus; tempat simpannya ikut dilepas.
- A-369 — mode Tata letak: klik rak = seluruh rak, klik petak = bin; di HP lewat versi daftar; cari maks. 50 barang.
- A-370 — cetak denah berupa halaman HTML A4 mendatar (bukan PDF), 7 tips statis.
- A-371 — impor tata letak tanpa langkah pratinjau terpisah (galat per baris, semua-atau-tidak).
- A-372 — saran put-away dari tempat simpan langsung dipakai.

**Put-away pindai & lokasi (Bagian 4–5)**
- A-373 — kode pendek yang diketik ("r01 l1 01") diterima bila cocok satu bin; kembar ditolak.
- A-374 — Isi Bin menampilkan tanggal *masuk terakhir*, maks. 200 label.
- A-375 — PUT dibatalkan setelah sebagian ditaruh: yang sudah ditaruh tetap, dibuat ulang untuk sisanya.
- A-376 — layar HP menaruh hanya lewat pindai/ketik kode bin (tanpa tombol "taruh tanpa pindai"); detail PUT tetap punya *Taruh* per baris.
- A-377 — tanda *penuh* dihitung saat tampil (tanpa kolom baru).
- A-378 — alasan di pilah retur hanya wajib bila saran berasal dari tempat simpan; label kemasan dipindah utuh per label.
- A-379 — label bin berjudul kode pendek; ukuran huruf ikut Desain Label.
- A-380 — Setup awal: langkah proyek, pengguna, approval dipindah setelah saldo awal; tautan Denah memakai gudang non-Site pertama.
- A-381 — impor saldo awal tanpa kode bin **tidak** jatuh ke aturan lama A-84: tanpa tempat simpan atau penuh → baris ditolak.
- A-382 — kolom Lokasi mencakup semua jenis bin (termasuk Penerimaan/Retur).

**Pilihan yang bisa dicari (Langkah 2)**
- A-383 — `<x-pilih>` untuk master kecil–sedang; cari ke server untuk item, bin, vendor, pengguna, proyek; enum/alasan/≤ 8 pilihan tetap select biasa.
- A-384 — cari ke server mulai 2 huruf, ±30 hasil, 60 pencarian/menit per orang; akun Klien tidak pernah mendapat daftar pengguna internal, vendor, atau bin.
- A-385 — unit kosong → memilih jabatan mengisi unitnya; pasangan lama yang tak cocok tetap bisa disimpan selama tidak diubah.
- A-386 — kelompok atasan: *Unit ini* → satu kelompok *Unit induk* (terdekat) → *Unit lain*.
- A-387 — lingkaran atasan hanya diperiksa saat atasan/jabatan diubah (data lama yang melingkar tidak memblokir simpan lain).
- A-388 — proyek di baris *Peran lain* tetap semua proyek aktif.
- A-389 — PIC proyek dari luar daftar kini ditolak (dulu id apa pun diterima).
- A-390 — teks nilai terpilih disembunyikan saat mengetik; sisa ketikan dihapus saat kotak ditinggal.
- A-391, A-392 — vendor/item/proyek dari luar daftar ditolak langsung di isian GRN, TRF, RET, REQ, SJ, Petakan, portal (kecuali nilai yang sudah tersimpan).
- A-393 — vendor saran riwayat di PO tampil paling atas dengan badge *disarankan*; opsi vendor = nama + badge jenis + kode.
- A-394 — mengganti gudang di penyesuaian manual mengosongkan bin semua baris.
- A-395 — mengganti gudang di WST mengosongkan proyek yang tak ada di daftar baru; pilihan batang di Konversi tetap select biasa.
- A-396 — layar laporan tidak pernah memberi pilihan ke akun Klien (ditutup total).
- A-397 — proyek Gudang Site & kepala gudang dari luar daftar ditolak; mode Denah menulis gudang ke URL (`?gudang=`).
- A-398 — form Platform tetap select biasa (bukan Livewire, daftar pendek).

## 4. Cara mencoba besok pagi

Masuk ke `http://demo.wms.test:8000` sebagai `admin@demo.wms.test` / `Demo#2026!` (company DEMO). Langkah 5–7 mengubah
tata letak saja (tanpa stok); boleh diurungkan.

1. **Kemasan Paku** — *Master data › Item* → **Paku** → *Ubah item* → tab **Kemasan**. Satuan dasar Paku = DUS dan
   kemasan lamanya kini terbaca sebagai kalimat **"1 BOX berisi 12 DUS"**. Bila yang dimaksud sebaliknya (1 DUS berisi
   12 BOX): ganti Satuan dasar ke **BOX** (belum terkunci karena Paku belum punya stok) lalu tulis baris "1 DUS berisi
   12 BOX"; coba tambah "1 SET berisi 2 DUS" — rincian "= 24 BOX (2 × 12)" tampil saat mengetik. Simpan.
2. **Atasan ber-badge** — *Pengaturan › Pengguna* → **Rina Admin** → *Ubah* → *4. Pengaturan lanjutan*: pilih Unit, lihat
   Jabatan hanya berisi jabatan unit itu; buka **Atasan langsung (bila beda dari jabatan)** → "kepala gudang" tampil dengan
   badge **Kepala Gudang** · Gudang, berkelompok; ketik "ke" untuk mencari. (Tidak perlu disimpan.)
3. **Denah: gabung bin** — *Gudang & stok › Daftar gudang* → **CKG-001** → **Denah** → **Atur denah** → klik rak **B001** →
   bagian **Petak (bin)**: ketuk dua petak kosong bersebelahan → isi Bin utama, Arah, Sifat, Alasan → **Gabung** →
   **Simpan perubahan**. Petak tergambar satu blok. Coba juga **Pisah** dan **Lebar bin (m)**.
4. **Denah: area lantai** — masih di *Atur denah*: **Tambah → Tambah area lantai** (atau pilih area yang ada) → isi
   **Kapasitas area lantai** (kosong = tanpa batas) → **Simpan perubahan**. Tombol **Daftar** menampilkan versi HP.
5. **Tempat simpan Paku** — di Denah klik **Tata letak barang** → panel *Barang belum punya tempat*: centang **Paku**
   (boleh centang *Khusus barang ini*) → **Taruh di…** → klik rak **B001** → **Simpan perubahan**. Lalu *Master data ›
   Item › Paku*: kartu **Tempat simpan** menampilkan B001.
6. **Cetak denah** — di Denah klik **Cetak denah**: nama barang per rak (Paku di B001) + tips tata letak; cetak A4 mendatar.
7. **Label & isi bin** — *Laporan & cetak › Cetak label* (bin) → pindai QR label dengan kamera HP → halaman **Isi bin**
   berkode pendek ("B001 · L1 · 01"). Atau buka *Gudang & stok › Bin* lalu salah satu kode.
8. **Pilihan yang bisa dicari** — *Barang masuk › Penerimaan barang › Buat*: ketik 2 huruf di **Vendor** dan **Item**;
   *Barang keluar › Permintaan material › Buat*: cari **Proyek** dan **Item** baris; *Pembelian › Purchase Order › PO baru*:
   cari vendor. Tidak perlu menyimpan.
9. **Setup awal & Lokasi** — *Pengaturan › Setup awal*: urutan ① gudang → ② rak → ③ item → ④ tata letak barang →
   ⑤ saldo awal. *Gudang & stok › Saldo stok*: kolom **Lokasi** (terisi setelah ada stok).
10. **Tema & HP** — ulangi langkah 2 dan 8 dengan tema gelap (ikon bulan) dan dari HP.

## 5. Perintah sebelum mencoba (di laptop ini)

Tidak ada perintah yang menghapus data. Jangan menjalankan `migrate:fresh`, `tenants:migrate-fresh`, atau seeder ke DEMO.

```
git push origin main              # kirim semua commit malam ini (d3aab5a … laporan ini) ke GitHub
php artisan migrate               # pusat — "Nothing to migrate" bila sudah
php artisan tenants:migrate       # DEMO — menambah kolom/tabel 000490–000520 (sudah dijalankan malam ini)
npm run build                     # aset JS/CSS terbaru
php artisan serve --host=127.0.0.1 --port=8000   # bila server belum jalan
```

Lalu buka browser dan tekan **Ctrl+F5** (muat ulang tanpa cache). Di mesin kantor: `git pull` lalu tiga perintah
terakhir yang sama.
