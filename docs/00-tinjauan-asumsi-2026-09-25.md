# Tinjauan asumsi *Perlu validasi* — 25 September 2026

**Versi:** 1.1
**Tanggal:** 25 September 2026
**Status:** daftar kerja untuk pemilik produk — dibuat otomatis dari [04-keputusan-dan-asumsi](wms/04-keputusan-dan-asumsi.md) v0.26 (butir Penutup, [prompt serah terima](prompts/00-lanjutkan-di-rumah.md) §2 butir 6)
**Dokumen terkait:** [README](README.md) · [Laporan progres](00-laporan-progres-2026-09-24.md)

Setiap baris satu asumsi yang sudah dipakai kode tetapi belum disetujui. Isi kolom **Keputusan** dengan `Setuju`, `Ubah: …`, atau `Hapus`, lalu pindahkan hasilnya ke kolom *Validasi* di dokumen 04 (bukan di sini). Rincian lengkap tiap asumsi ada di anchor-nya.

Jumlah: **111 asumsi** dalam 10 kelompok. Tanda ⚠ = asumsi dari sesi 25 Sep 2026 (A-170–A-205) yang paling berdampak bila ditolak karena menyentuh stok, tagihan langganan, atau hak akses — sebaiknya ditinjau lebih dulu.

## 2.5 Baru dari pencocokan dokumen dengan kode — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-72](wms/04-keputusan-dan-asumsi.md#a-72) | Stok awal demo lewat seeder, tanpa dokumen | |
| [A-73](wms/04-keputusan-dan-asumsi.md#a-73) | Satu kelas aksi boleh memegang beberapa transisi dari dokumen yang sama | |
| [A-74](wms/04-keputusan-dan-asumsi.md#a-74) | Kolom konvensi belum dipasang di tabel tenant | |
| [A-75](wms/04-keputusan-dan-asumsi.md#a-75) | Skema `audit_logs` mengikuti spatie/activitylog | |
| [A-76](wms/04-keputusan-dan-asumsi.md#a-76) | Dev di mesin kantor memakai XAMPP + MariaDB 10.4.27 | |

## 2.6 Baru dari pembangunan modul lanjutan — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-77](wms/04-keputusan-dan-asumsi.md#a-77) | REQ `completed` saat semua baris diterima baik atau ditutup, tanpa menunggu konfirmasi pemohon | |
| [A-78](wms/04-keputusan-dan-asumsi.md#a-78) | Efek QC pada stok | |
| [A-79](wms/04-keputusan-dan-asumsi.md#a-79) | QC wajib = saklar company `qc` menyala DAN `items.requires_qc` | |
| [A-80](wms/04-keputusan-dan-asumsi.md#a-80) | Permission QC dan approval RTV tanpa modul approval | |
| [A-81](wms/04-keputusan-dan-asumsi.md#a-81) | GRN transfer menerbitkan `stock_transferred` dengan pergerakan | |
| [A-82](wms/04-keputusan-dan-asumsi.md#a-82) | GRN transfer menuntut bukti terima lebih dulu | |
| [A-83](wms/04-keputusan-dan-asumsi.md#a-83) | Cross-dock hanya saran di Fase 1 | |
| [A-84](wms/04-keputusan-dan-asumsi.md#a-84) | Aturan saran bin put-away | |
| [A-85](wms/04-keputusan-dan-asumsi.md#a-85) | Stok Tersedia hanya dihitung dari bin penyimpanan (`storage`) | |
| [A-86](wms/04-keputusan-dan-asumsi.md#a-86) | Hak memutus approval = tugas dari aturan + permission approve Katalog per dokumen | |
| [A-87](wms/04-keputusan-dan-asumsi.md#a-87) | Pencocokan aturan: | |
| [A-88](wms/04-keputusan-dan-asumsi.md#a-88) | Resolusi approver saat diajukan: | |
| [A-89](wms/04-keputusan-dan-asumsi.md#a-89) | Delegasi: | |
| [A-90](wms/04-keputusan-dan-asumsi.md#a-90) | Eskalasi: | |
| [A-91](wms/04-keputusan-dan-asumsi.md#a-91) | Notifikasi approval masih stub | |
| [A-92](wms/04-keputusan-dan-asumsi.md#a-92) | Mesin approval di `app/Domain/Approval`, bukan paket `packages/approval` | |
| [A-93](wms/04-keputusan-dan-asumsi.md#a-93) | RTV tanpa aturan langsung disetujui | |
| [A-94](wms/04-keputusan-dan-asumsi.md#a-94) | Kolom & penyimpanan di luar ERD: | |
| [A-95](wms/04-keputusan-dan-asumsi.md#a-95) | Permission opname & penyesuaian di luar Katalog | |
| [A-96](wms/04-keputusan-dan-asumsi.md#a-96) | Approval sesi opname selalu minimal satu lapis | |
| [A-97](wms/04-keputusan-dan-asumsi.md#a-97) | Hasil opname ditolak → sesi tetap `reconciling` | |
| [A-98](wms/04-keputusan-dan-asumsi.md#a-98) | DSC `adjusted` tetap memposting sendiri, tanpa dokumen ADJ | |
| [A-99](wms/04-keputusan-dan-asumsi.md#a-99) | Rincian klasifikasi & hitung ulang | |
| [A-100](wms/04-keputusan-dan-asumsi.md#a-100) | Cakupan & snapshot opname | |
| [A-101](wms/04-keputusan-dan-asumsi.md#a-101) | Penutupan sesi | |
| [A-102](wms/04-keputusan-dan-asumsi.md#a-102) | Baris ADJ manual & ADJ pembalik | |
| [A-103](wms/04-keputusan-dan-asumsi.md#a-103) | Batas hitung buta | |
| [A-104](wms/04-keputusan-dan-asumsi.md#a-104) | Kolom di luar ERD modul Count/Adjustment: | |
| [A-105](wms/04-keputusan-dan-asumsi.md#a-105) | Aturan approval demo ADJ & OPN | |
| [A-106](wms/04-keputusan-dan-asumsi.md#a-106) | TRF dari backorder REQ | |
| [A-107](wms/04-keputusan-dan-asumsi.md#a-107) | Siklus TRF | |
| [A-108](wms/04-keputusan-dan-asumsi.md#a-108) | Reservasi ke REQ penunggu saat put-away | |
| [A-109](wms/04-keputusan-dan-asumsi.md#a-109) | Permission & cakupan TRF/RET | |
| [A-110](wms/04-keputusan-dan-asumsi.md#a-110) | Asal baris RET | |
| [A-111](wms/04-keputusan-dan-asumsi.md#a-111) | SJ balik hanya untuk stok Gudang Site | |
| [A-112](wms/04-keputusan-dan-asumsi.md#a-112) | GRN retur | |
| [A-113](wms/04-keputusan-dan-asumsi.md#a-113) | Pemilahan retur | |
| [A-114](wms/04-keputusan-dan-asumsi.md#a-114) | DSC "kembali ke gudang" tidak lewat GRN retur | |
| [A-115](wms/04-keputusan-dan-asumsi.md#a-115) | Kolom di luar ERD modul Transfer/Retur: | |
| [A-116](wms/04-keputusan-dan-asumsi.md#a-116) | Aset di modul Retur/Transfer tanpa modul Aset | |

## 2.7 Baru dari modul Template dokumen & label — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-120](wms/04-keputusan-dan-asumsi.md#a-120) | Ukuran label sementara, sampai O-09 diputuskan | |
| [A-121](wms/04-keputusan-dan-asumsi.md#a-121) | Isi barcode dan QR | |
| [A-122](wms/04-keputusan-dan-asumsi.md#a-122) | Template F1 = Blade bawaan tetap | |
| [A-123](wms/04-keputusan-dan-asumsi.md#a-123) | Kolom di luar ERD modul Template: | |
| [A-124](wms/04-keputusan-dan-asumsi.md#a-124) | Permission modul `template`: | |
| [A-125](wms/04-keputusan-dan-asumsi.md#a-125) | Blok tanda tangan cetak | |
| [A-126](wms/04-keputusan-dan-asumsi.md#a-126) | Cetak di status apa pun, tanpa log cetak | |

## 2.8 Baru dari modul Issue (pemakaian material di site) — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-117](wms/04-keputusan-dan-asumsi.md#a-117) | Sumber dan bentuk baris ISU | |
| [A-118](wms/04-keputusan-dan-asumsi.md#a-118) | Kolom di luar ERD modul Issue: | |
| [A-119](wms/04-keputusan-dan-asumsi.md#a-119) | Permission & cakupan ISU | |
| [A-150](wms/04-keputusan-dan-asumsi.md#a-150) | ISU pembalik tanpa status baru | |
| [A-151](wms/04-keputusan-dan-asumsi.md#a-151) | Laporan Material per Proyek dibaca dari kartu stok | |
| [A-152](wms/04-keputusan-dan-asumsi.md#a-152) | Cetak Bukti Pemakaian Material | |

## 2.9 Baru dari modul Konversi & Waste — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-153](wms/04-keputusan-dan-asumsi.md#a-153) | CNV tanpa status baru; approval opsional | |
| [A-154](wms/04-keputusan-dan-asumsi.md#a-154) | Input dan baris CNV | |
| [A-155](wms/04-keputusan-dan-asumsi.md#a-155) | Kolom di luar ERD modul Konversi & Waste: | |
| [A-156](wms/04-keputusan-dan-asumsi.md#a-156) | Neraca ukuran per jenis konversi | |
| [A-157](wms/04-keputusan-dan-asumsi.md#a-157) | CNV pembalik | |
| [A-158](wms/04-keputusan-dan-asumsi.md#a-158) | Permission & cakupan | |
| [A-159](wms/04-keputusan-dan-asumsi.md#a-159) | Baris dan alur WST | |
| [A-160](wms/04-keputusan-dan-asumsi.md#a-160) | Bukti tutup WST dan cetak | |
| [A-161](wms/04-keputusan-dan-asumsi.md#a-161) | Kolom konversi & waste di laporan Material per Proyek | |
| [A-162](wms/04-keputusan-dan-asumsi.md#a-162) | Mesin dev rumah juga MariaDB | |

## 2.10 Baru dari modul Aset dipinjamkan — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-163](wms/04-keputusan-dan-asumsi.md#a-163) | AST mengikuti SJ dan RET, bukan dokumen tersendiri | |
| [A-164](wms/04-keputusan-dan-asumsi.md#a-164) | State aset disinkron dari lokasi & kondisi | |
| [A-165](wms/04-keputusan-dan-asumsi.md#a-165) | Kolom di luar ERD modul Aset: | |
| [A-166](wms/04-keputusan-dan-asumsi.md#a-166) | Guard pemeriksaan | |
| [A-167](wms/04-keputusan-dan-asumsi.md#a-167) | Aset hilang | |
| [A-168](wms/04-keputusan-dan-asumsi.md#a-168) | Permission & cakupan Aset | |
| [A-169](wms/04-keputusan-dan-asumsi.md#a-169) | Jatuh tempo & sisa umur tanpa notifikasi | |

## 2.11 Baru dari modul Purchase Request — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-170](wms/04-keputusan-dan-asumsi.md#a-170) | Permission & cakupan PRQ | |
| [A-171](wms/04-keputusan-dan-asumsi.md#a-171) ⚠ | PRQ backorder REQ | |
| [A-172](wms/04-keputusan-dan-asumsi.md#a-172) | Kolom di luar ERD modul PRQ: | |
| [A-173](wms/04-keputusan-dan-asumsi.md#a-173) | Jenis vendor untuk aturan approval PRQ | |
| [A-174](wms/04-keputusan-dan-asumsi.md#a-174) ⚠ | Sambungan GRN ↔ catatan pemesanan | |
| [A-175](wms/04-keputusan-dan-asumsi.md#a-175) | Job titik pesan ulang | |

## 2.12 Baru dari modul Platform penuh — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-176](wms/04-keputusan-dan-asumsi.md#a-176) | Pembuatan company | |
| [A-177](wms/04-keputusan-dan-asumsi.md#a-177) ⚠ | Tagihan & siklus harian | |
| [A-178](wms/04-keputusan-dan-asumsi.md#a-178) ⚠ | Bukti bayar | |
| [A-179](wms/04-keputusan-dan-asumsi.md#a-179) ⚠ | Status company & data diakhiri | |
| [A-180](wms/04-keputusan-dan-asumsi.md#a-180) ⚠ | Masuk lewat akses dukungan | |
| [A-181](wms/04-keputusan-dan-asumsi.md#a-181) | Layar saat diakhiri | |
| [A-182](wms/04-keputusan-dan-asumsi.md#a-182) | Login Super Admin | |
| [A-183](wms/04-keputusan-dan-asumsi.md#a-183) | Flag fitur Fase 1 | |
| [A-184](wms/04-keputusan-dan-asumsi.md#a-184) | Kolom & tabel di luar ERD modul Platform: | |

## 2.13 Baru dari Pendukung Fase 1 — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-185](wms/04-keputusan-dan-asumsi.md#a-185) ⚠ | Urutan strategi pengambilan | |
| [A-186](wms/04-keputusan-dan-asumsi.md#a-186) | Beranda = antrean pekerjaan | |
| [A-187](wms/04-keputusan-dan-asumsi.md#a-187) ⚠ | Checklist penutupan proyek (v0.26: REQ menunggu keputusan & SJ disiapkan ikut menghalangi) | |
| [A-188](wms/04-keputusan-dan-asumsi.md#a-188) ⚠ | Keberatan terima | |
| [A-189](wms/04-keputusan-dan-asumsi.md#a-189) | Notifikasi Fase 1 | |
| [A-190](wms/04-keputusan-dan-asumsi.md#a-190) | Laporan inti & PDF | |
| [A-191](wms/04-keputusan-dan-asumsi.md#a-191) | Wizard setup | |
| [A-192](wms/04-keputusan-dan-asumsi.md#a-192) | Impor Excel Fase 1 = item dan proyek (+klien) | |
| [A-193](wms/04-keputusan-dan-asumsi.md#a-193) | PWA Fase 1 | |
| [A-194](wms/04-keputusan-dan-asumsi.md#a-194) ⚠ | Kondisi asal dicatat di kartu stok | |

## 2.14 Baru dari tinjauan kode Pendukung & Platform — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-195](wms/04-keputusan-dan-asumsi.md#a-195) ⚠ | Bayar terlambat memulai periode baru dari hari verifikasi | |
| [A-196](wms/04-keputusan-dan-asumsi.md#a-196) ⚠ | Mode hanya-baca tetap bisa cari/filter/pindah halaman | |
| [A-197](wms/04-keputusan-dan-asumsi.md#a-197) | Tanggapan terima satu per SJ, keberatan hanya baris REQ sendiri | |
| [A-198](wms/04-keputusan-dan-asumsi.md#a-198) ⚠ | Keberatan "masih dibutuhkan" membuka lagi baris REQ; REQ selesai → REQ baru | |
| [A-199](wms/04-keputusan-dan-asumsi.md#a-199) | Tautan akses dukungan sekali pakai; email notifikasi setelah commit | |
| [A-200](wms/04-keputusan-dan-asumsi.md#a-200) ⚠ | 2FA Super Admin opsional (bukan wajib) | |
| [A-201](wms/04-keputusan-dan-asumsi.md#a-201) | Pindai untuk pencarian item/saldo/aset dan bin tujuan put-away | |
| [A-202](wms/04-keputusan-dan-asumsi.md#a-202) | Pengingat tagihan langganan ke company (lonceng + email) | |
| [A-203](wms/04-keputusan-dan-asumsi.md#a-203) ⚠ | Alur pindai picking: bin lalu item langsung mencatat baris | |
| [A-204](wms/04-keputusan-dan-asumsi.md#a-204) ⚠ | Sisa baris REQ (kurang ambil, reship, keberatan) dipetik ulang lewat PCK baru | |
| [A-205](wms/04-keputusan-dan-asumsi.md#a-205) | Pengetatan 2FA: kode tidak bisa dipakai ulang, kode salah ikut kunci akun | |
