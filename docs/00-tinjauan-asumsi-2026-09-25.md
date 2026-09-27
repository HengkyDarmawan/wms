# Tinjauan asumsi *Perlu validasi* — 25 September 2026

**Versi:** 1.30
**Tanggal:** 28 September 2026
**Status:** daftar kerja untuk pemilik produk — dibuat otomatis dari [04-keputusan-dan-asumsi](wms/04-keputusan-dan-asumsi.md) v0.26; v1.2: ditambah A-206–A-225 dari sesi kantor 25 Sep 2026 (v0.27) (butir Penutup, [prompt serah terima](prompts/00-lanjutkan-di-rumah.md) §2 butir 6); v1.8: ditambah A-233–A-237 (notifikasi §8, 04 v0.34); v1.9: A-238 (lampiran generik, 04 v0.35); v1.10: A-239–A-240 (04 v0.36); v1.11: A-241–A-242 (04 v0.37); v1.12: A-243–A-245 (04 v0.38); v1.13: keputusan pemilik produk 26 Sep 2026 untuk 31 asumsi ⚠ + A-111, A-116 (04 v0.39) ; v1.14: putaran 1 tinjauan tanpa ⚠ — A-84–A-95 kecuali A-87 (A-92 diubah, 04 v0.41), ditambah A-257–A-258 dari [04b](wms/04b-asumsi-lanjutan.md) ; v1.15: putaran 2 — A-87, A-96–A-107 (A-99 diubah → A-259, 04 v0.42) ; v1.16: putaran 3 — A-108–A-115, A-120–A-124 (A-120, A-121 diubah → A-261, A-262, 04 v0.43) ; v1.17: putaran 4 — A-117–A-119, A-125–A-126, A-150–A-156 (A-125, A-126 diubah → A-264, A-263, 04 v0.44) ; v1.18: putaran 5 — A-157–A-168 Setuju (04 v0.45) ; v1.19: putaran 6 — A-169–A-189 (12) Setuju (04 v0.46); v1.20: putaran 7 — A-190–A-211 (12): 11 Setuju (A-193 diperluas A-266), A-211 diubah → A-265 (04 v0.47); v1.21: putaran 8 — A-214 (dilengkapi A-267), A-215, A-217, A-218, A-221, A-223–A-227 Setuju; A-220 dan A-222 menunggu penjelasan (04 v0.48); v1.22: putaran 9 — sisa 13 diputus: A-220, A-234, A-237–A-244, A-257, A-258 Setuju; A-222 diubah (A-268); A-233, A-235 dilengkapi (A-269, A-270) (04 v0.49) — **daftar kerja selesai**; yang masih *Perlu validasi* hanya A-260 (sesi paralel, di luar daftar ini); v1.23: A-271–A-273 (sisa kecil Fase 1, 27 Sep 2026) — **3 asumsi baru menunggu keputusan**; v1.24: A-274–A-280 (WhatsApp Fase 2a) — **10 asumsi baru menunggu keputusan**; v1.25: A-281 (tampilan denah) — **11 menunggu**; v1.26: A-282 (form item ringkas) — **12 menunggu**; v1.27: A-283–A-286 (tiga jenis barang & saklar fitur) — **16 menunggu**; v1.28: A-287–A-295 (penerimaan Baik/Rusak/Kurang & kemasan) — **25 menunggu**; v1.29: A-296–A-303 (label kemasan & pemindaian wajib) — **33 menunggu**; v1.30: A-304–A-310 (saran vendor dari riwayat, riwayat harga beli, nonaktif vendor) — **40 menunggu**
**Dokumen terkait:** [README](README.md) · [Laporan progres](00-laporan-progres-2026-09-24.md)

Setiap baris satu asumsi yang sudah dipakai kode tetapi belum disetujui. Isi kolom **Keputusan** dengan `Setuju`, `Ubah: …`, atau `Hapus`, lalu pindahkan hasilnya ke kolom *Validasi* di dokumen 04 (bukan di sini). Rincian lengkap tiap asumsi ada di anchor-nya.

Jumlah: **192 asumsi** dalam 24 kelompok (sejak v1.14 termasuk A-257–A-258, sejak v1.23 A-271–A-273, sejak v1.24 A-274–A-280, sejak v1.25 A-281, sejak v1.26 A-282, sejak v1.27 A-283–A-286, sejak v1.28 A-287–A-295, sejak v1.29 A-296–A-303, sejak v1.30 A-304–A-310 di [04b](wms/04b-asumsi-lanjutan.md)). Tanda ⚠ = asumsi dari sesi 25 Sep 2026 (A-170–A-205) yang paling berdampak bila ditolak karena menyentuh stok, tagihan langganan, nilai uang, atau hak akses — sebaiknya ditinjau lebih dulu (sejak v1.2 juga A-206–A-225).

## 2.5 Baru dari pencocokan dokumen dengan kode — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-72](wms/04-keputusan-dan-asumsi.md#a-72) | Stok awal demo lewat seeder, tanpa dokumen | Setuju |
| [A-73](wms/04-keputusan-dan-asumsi.md#a-73) | Satu kelas aksi boleh memegang beberapa transisi dari dokumen yang sama | Setuju |
| [A-74](wms/04-keputusan-dan-asumsi.md#a-74) | Kolom konvensi belum dipasang di tabel tenant | Setuju |
| [A-75](wms/04-keputusan-dan-asumsi.md#a-75) | Skema `audit_logs` mengikuti spatie/activitylog | Setuju |
| [A-76](wms/04-keputusan-dan-asumsi.md#a-76) | Dev di mesin kantor memakai XAMPP + MariaDB 10.4.27 | Setuju |

## 2.6 Baru dari pembangunan modul lanjutan — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-77](wms/04-keputusan-dan-asumsi.md#a-77) | REQ `completed` saat semua baris diterima baik atau ditutup, tanpa menunggu konfirmasi pemohon | Setuju |
| [A-78](wms/04-keputusan-dan-asumsi.md#a-78) | Efek QC pada stok | Setuju |
| [A-79](wms/04-keputusan-dan-asumsi.md#a-79) | QC wajib = saklar company `qc` menyala DAN `items.requires_qc` | Setuju |
| [A-80](wms/04-keputusan-dan-asumsi.md#a-80) | Permission QC dan approval RTV tanpa modul approval | Setuju |
| [A-81](wms/04-keputusan-dan-asumsi.md#a-81) | GRN transfer menerbitkan `stock_transferred` dengan pergerakan | Setuju |
| [A-82](wms/04-keputusan-dan-asumsi.md#a-82) | GRN transfer menuntut bukti terima lebih dulu | Setuju |
| [A-83](wms/04-keputusan-dan-asumsi.md#a-83) | Cross-dock hanya saran di Fase 1 | Setuju |
| [A-84](wms/04-keputusan-dan-asumsi.md#a-84) | Aturan saran bin put-away | Setuju |
| [A-85](wms/04-keputusan-dan-asumsi.md#a-85) | Stok Tersedia hanya dihitung dari bin penyimpanan (`storage`) | Setuju |
| [A-86](wms/04-keputusan-dan-asumsi.md#a-86) | Hak memutus approval = tugas dari aturan + permission approve Katalog per dokumen | Setuju |
| [A-87](wms/04-keputusan-dan-asumsi.md#a-87) | Pencocokan aturan: | Setuju |
| [A-88](wms/04-keputusan-dan-asumsi.md#a-88) | Resolusi approver saat diajukan: | Setuju |
| [A-89](wms/04-keputusan-dan-asumsi.md#a-89) | Delegasi: | Setuju |
| [A-90](wms/04-keputusan-dan-asumsi.md#a-90) | Eskalasi: | Setuju |
| [A-91](wms/04-keputusan-dan-asumsi.md#a-91) | Notifikasi approval masih stub | Setuju (sudah dijalankan modul notifikasi, A-189) |
| [A-92](wms/04-keputusan-dan-asumsi.md#a-92) | Mesin approval di `app/Domain/Approval`, bukan paket `packages/approval` | Ubah: tetap permanen di `app/Domain/Approval`, rencana pindah ke paket dibatalkan (A-208) |
| [A-93](wms/04-keputusan-dan-asumsi.md#a-93) | RTV tanpa aturan langsung disetujui | Setuju |
| [A-94](wms/04-keputusan-dan-asumsi.md#a-94) | Kolom & penyimpanan di luar ERD: | Setuju |
| [A-95](wms/04-keputusan-dan-asumsi.md#a-95) | Permission opname & penyesuaian di luar Katalog | Setuju |
| [A-96](wms/04-keputusan-dan-asumsi.md#a-96) | Approval sesi opname selalu minimal satu lapis | Setuju |
| [A-97](wms/04-keputusan-dan-asumsi.md#a-97) | Hasil opname ditolak → sesi tetap `reconciling` | Setuju |
| [A-98](wms/04-keputusan-dan-asumsi.md#a-98) | DSC `adjusted` tetap memposting sendiri, tanpa dokumen ADJ | Setuju |
| [A-99](wms/04-keputusan-dan-asumsi.md#a-99) | Rincian klasifikasi & hitung ulang | Ubah: selisih besar ikut dihitung ulang, akar masalah bila tetap besar (A-259) |
| [A-100](wms/04-keputusan-dan-asumsi.md#a-100) | Cakupan & snapshot opname | Setuju |
| [A-101](wms/04-keputusan-dan-asumsi.md#a-101) | Penutupan sesi | Setuju |
| [A-102](wms/04-keputusan-dan-asumsi.md#a-102) | Baris ADJ manual & ADJ pembalik | Setuju |
| [A-103](wms/04-keputusan-dan-asumsi.md#a-103) | Batas hitung buta | Setuju |
| [A-104](wms/04-keputusan-dan-asumsi.md#a-104) | Kolom di luar ERD modul Count/Adjustment: | Setuju |
| [A-105](wms/04-keputusan-dan-asumsi.md#a-105) | Aturan approval demo ADJ & OPN | Setuju |
| [A-106](wms/04-keputusan-dan-asumsi.md#a-106) | TRF dari backorder REQ | Setuju |
| [A-107](wms/04-keputusan-dan-asumsi.md#a-107) | Siklus TRF | Setuju |
| [A-108](wms/04-keputusan-dan-asumsi.md#a-108) | Reservasi ke REQ penunggu saat put-away | Setuju |
| [A-109](wms/04-keputusan-dan-asumsi.md#a-109) | Permission & cakupan TRF/RET | Setuju |
| [A-110](wms/04-keputusan-dan-asumsi.md#a-110) | Asal baris RET | Setuju |
| [A-111](wms/04-keputusan-dan-asumsi.md#a-111) | SJ balik hanya untuk stok Gudang Site | Ubah: SJ jemput tanpa PCK (A-247, A-248) |
| [A-112](wms/04-keputusan-dan-asumsi.md#a-112) | GRN retur | Setuju |
| [A-113](wms/04-keputusan-dan-asumsi.md#a-113) | Pemilahan retur | Setuju |
| [A-114](wms/04-keputusan-dan-asumsi.md#a-114) | DSC "kembali ke gudang" tidak lewat GRN retur | Setuju |
| [A-115](wms/04-keputusan-dan-asumsi.md#a-115) | Kolom di luar ERD modul Transfer/Retur: | Setuju |
| [A-116](wms/04-keputusan-dan-asumsi.md#a-116) | Aset di modul Retur/Transfer tanpa modul Aset | Ubah: transfer aset On-site antar proyek (A-249) |

## 2.7 Baru dari modul Template dokumen & label — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-120](wms/04-keputusan-dan-asumsi.md#a-120) | Ukuran label sementara, sampai O-09 diputuskan | Ubah: ukuran label jadi master per company + desain label yang bisa ditata (A-261) |
| [A-121](wms/04-keputusan-dan-asumsi.md#a-121) | Isi barcode dan QR | Ubah: pilihan barcode saja / QR saja / keduanya per desain (A-262); isi kode tetap |
| [A-122](wms/04-keputusan-dan-asumsi.md#a-122) | Template F1 = Blade bawaan tetap | Setuju |
| [A-123](wms/04-keputusan-dan-asumsi.md#a-123) | Kolom di luar ERD modul Template: | Setuju |
| [A-124](wms/04-keputusan-dan-asumsi.md#a-124) | Permission modul `template`: | Setuju |
| [A-125](wms/04-keputusan-dan-asumsi.md#a-125) | Blok tanda tangan cetak | Ubah: tanda tangan digambar di profil + segel QR bertanda waktu yang bisa diverifikasi publik (A-264) |
| [A-126](wms/04-keputusan-dan-asumsi.md#a-126) | Cetak di status apa pun, tanpa log cetak | Ubah: setiap cetak dicatat & tampil sebagai riwayat; cetakan ke-2 dst. SJ/Bukti Terima/PO bertanda CETAK ULANG (A-263) |

## 2.8 Baru dari modul Issue (pemakaian material di site) — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-117](wms/04-keputusan-dan-asumsi.md#a-117) | Sumber dan bentuk baris ISU | Setuju |
| [A-118](wms/04-keputusan-dan-asumsi.md#a-118) | Kolom di luar ERD modul Issue: | Setuju |
| [A-119](wms/04-keputusan-dan-asumsi.md#a-119) | Permission & cakupan ISU | Setuju |
| [A-150](wms/04-keputusan-dan-asumsi.md#a-150) | ISU pembalik tanpa status baru | Setuju |
| [A-151](wms/04-keputusan-dan-asumsi.md#a-151) | Laporan Material per Proyek dibaca dari kartu stok | Setuju |
| [A-152](wms/04-keputusan-dan-asumsi.md#a-152) | Cetak Bukti Pemakaian Material | Setuju |

## 2.9 Baru dari modul Konversi & Waste — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-153](wms/04-keputusan-dan-asumsi.md#a-153) | CNV tanpa status baru; approval opsional | Setuju |
| [A-154](wms/04-keputusan-dan-asumsi.md#a-154) | Input dan baris CNV | Setuju |
| [A-155](wms/04-keputusan-dan-asumsi.md#a-155) | Kolom di luar ERD modul Konversi & Waste: | Setuju |
| [A-156](wms/04-keputusan-dan-asumsi.md#a-156) | Neraca ukuran per jenis konversi | Setuju |
| [A-157](wms/04-keputusan-dan-asumsi.md#a-157) | CNV pembalik | Setuju |
| [A-158](wms/04-keputusan-dan-asumsi.md#a-158) | Permission & cakupan | Setuju |
| [A-159](wms/04-keputusan-dan-asumsi.md#a-159) | Baris dan alur WST | Setuju |
| [A-160](wms/04-keputusan-dan-asumsi.md#a-160) | Bukti tutup WST dan cetak | Setuju |
| [A-161](wms/04-keputusan-dan-asumsi.md#a-161) | Kolom konversi & waste di laporan Material per Proyek | Setuju |
| [A-162](wms/04-keputusan-dan-asumsi.md#a-162) | Mesin dev rumah juga MariaDB | Setuju |

## 2.10 Baru dari modul Aset dipinjamkan — 24 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-163](wms/04-keputusan-dan-asumsi.md#a-163) | AST mengikuti SJ dan RET, bukan dokumen tersendiri | Setuju |
| [A-164](wms/04-keputusan-dan-asumsi.md#a-164) | State aset disinkron dari lokasi & kondisi | Setuju |
| [A-165](wms/04-keputusan-dan-asumsi.md#a-165) | Kolom di luar ERD modul Aset: | Setuju |
| [A-166](wms/04-keputusan-dan-asumsi.md#a-166) | Guard pemeriksaan | Setuju |
| [A-167](wms/04-keputusan-dan-asumsi.md#a-167) | Aset hilang | Setuju |
| [A-168](wms/04-keputusan-dan-asumsi.md#a-168) | Permission & cakupan Aset | Setuju |
| [A-169](wms/04-keputusan-dan-asumsi.md#a-169) | Jatuh tempo & sisa umur tanpa notifikasi | Setuju |

## 2.11 Baru dari modul Purchase Request — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-170](wms/04-keputusan-dan-asumsi.md#a-170) | Permission & cakupan PRQ | Setuju |
| [A-171](wms/04-keputusan-dan-asumsi.md#a-171) ⚠ | PRQ backorder REQ | Setuju |
| [A-172](wms/04-keputusan-dan-asumsi.md#a-172) | Kolom di luar ERD modul PRQ: | Setuju |
| [A-173](wms/04-keputusan-dan-asumsi.md#a-173) | Jenis vendor untuk aturan approval PRQ | Setuju |
| [A-174](wms/04-keputusan-dan-asumsi.md#a-174) ⚠ | Sambungan GRN ↔ catatan pemesanan | Setuju |
| [A-175](wms/04-keputusan-dan-asumsi.md#a-175) | Job titik pesan ulang | Setuju |

## 2.12 Baru dari modul Platform penuh — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-176](wms/04-keputusan-dan-asumsi.md#a-176) | Pembuatan company | Setuju |
| [A-177](wms/04-keputusan-dan-asumsi.md#a-177) ⚠ | Tagihan & siklus harian | Setuju |
| [A-178](wms/04-keputusan-dan-asumsi.md#a-178) ⚠ | Bukti bayar | Setuju |
| [A-179](wms/04-keputusan-dan-asumsi.md#a-179) ⚠ | Status company & data diakhiri | Setuju |
| [A-180](wms/04-keputusan-dan-asumsi.md#a-180) ⚠ | Masuk lewat akses dukungan | Setuju |
| [A-181](wms/04-keputusan-dan-asumsi.md#a-181) | Layar saat diakhiri | Setuju |
| [A-182](wms/04-keputusan-dan-asumsi.md#a-182) | Login Super Admin | Setuju |
| [A-183](wms/04-keputusan-dan-asumsi.md#a-183) | Flag fitur Fase 1 | Setuju |
| [A-184](wms/04-keputusan-dan-asumsi.md#a-184) | Kolom & tabel di luar ERD modul Platform: | Setuju |

## 2.13 Baru dari Pendukung Fase 1 — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-185](wms/04-keputusan-dan-asumsi.md#a-185) ⚠ | Urutan strategi pengambilan | Setuju |
| [A-186](wms/04-keputusan-dan-asumsi.md#a-186) | Beranda = antrean pekerjaan | Setuju |
| [A-187](wms/04-keputusan-dan-asumsi.md#a-187) ⚠ | Checklist penutupan proyek (v0.26: REQ menunggu keputusan & SJ disiapkan ikut menghalangi) | Setuju |
| [A-188](wms/04-keputusan-dan-asumsi.md#a-188) ⚠ | Keberatan terima | Setuju |
| [A-189](wms/04-keputusan-dan-asumsi.md#a-189) | Notifikasi Fase 1 | Setuju |
| [A-190](wms/04-keputusan-dan-asumsi.md#a-190) | Laporan inti & PDF | Setuju |
| [A-191](wms/04-keputusan-dan-asumsi.md#a-191) | Wizard setup | Setuju |
| [A-192](wms/04-keputusan-dan-asumsi.md#a-192) | Impor Excel Fase 1 = item dan proyek (+klien) | Setuju |
| [A-193](wms/04-keputusan-dan-asumsi.md#a-193) | PWA Fase 1 | Setuju; kamera juga jalan di browser HP tanpa memasang aplikasi, termasuk iPhone ([A-266](wms/04b-asumsi-lanjutan.md#a-266)) |
| [A-194](wms/04-keputusan-dan-asumsi.md#a-194) ⚠ | Kondisi asal dicatat di kartu stok | Setuju |

## 2.14 Baru dari tinjauan kode Pendukung & Platform — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-195](wms/04-keputusan-dan-asumsi.md#a-195) ⚠ | Bayar terlambat memulai periode baru dari hari verifikasi | Setuju |
| [A-196](wms/04-keputusan-dan-asumsi.md#a-196) ⚠ | Mode hanya-baca tetap bisa cari/filter/pindah halaman | Setuju |
| [A-197](wms/04-keputusan-dan-asumsi.md#a-197) | Tanggapan terima satu per SJ, keberatan hanya baris REQ sendiri | Setuju |
| [A-198](wms/04-keputusan-dan-asumsi.md#a-198) ⚠ | Keberatan "masih dibutuhkan" membuka lagi baris REQ; REQ selesai → REQ baru | Setuju |
| [A-199](wms/04-keputusan-dan-asumsi.md#a-199) | Tautan akses dukungan sekali pakai; email notifikasi setelah commit | Setuju |
| [A-200](wms/04-keputusan-dan-asumsi.md#a-200) ⚠ | 2FA Super Admin opsional (bukan wajib) | Setuju |
| [A-201](wms/04-keputusan-dan-asumsi.md#a-201) | Pindai untuk pencarian item/saldo/aset dan bin tujuan put-away | Setuju |
| [A-202](wms/04-keputusan-dan-asumsi.md#a-202) | Pengingat tagihan langganan ke company (lonceng + email) | Setuju |
| [A-203](wms/04-keputusan-dan-asumsi.md#a-203) ⚠ | Alur pindai picking: bin lalu item langsung mencatat baris | Setuju |
| [A-204](wms/04-keputusan-dan-asumsi.md#a-204) ⚠ | Sisa baris REQ (kurang ambil, reship, keberatan) dipetik ulang lewat PCK baru | Setuju |
| [A-205](wms/04-keputusan-dan-asumsi.md#a-205) | Pengetatan 2FA: kode tidak bisa dipakai ulang, kode salah ikut kunci akun | Setuju |

## 2.15 Baru dari sesi kantor — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-206](wms/04-keputusan-dan-asumsi.md#a-206) | Pindai di form REQ & ISU | Setuju |
| [A-207](wms/04-keputusan-dan-asumsi.md#a-207) ⚠ | Impor vendor & saldo awal (saldo awal = ADJ per gudang, tetap approval) | Setuju |

## 2.16 Baru dari Purchasing inti Fase 1b — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-208](wms/04-keputusan-dan-asumsi.md#a-208) ⚠ | Purchasing di aplikasi yang sama; harga hanya di domain Purchasing; tanpa flag fitur | Setuju |
| [A-209](wms/04-keputusan-dan-asumsi.md#a-209) | Status PO memakai status umum Katalog §1 | Setuju |
| [A-210](wms/04-keputusan-dan-asumsi.md#a-210) ⚠ | PO hanya dari baris PRQ; satu vendor aktif × satu gudang | Ubah: PO boleh melebihi PRQ dengan alasan wajib (A-246) |
| [A-211](wms/04-keputusan-dan-asumsi.md#a-211) | Harga beli Rupiah per satuan dasar, tanpa pajak/diskon/ongkir | Ubah: centang "Harga sudah termasuk PPN" per PO, bawaan tercentang ([A-265](wms/04b-asumsi-lanjutan.md#a-265)) |
| [A-212](wms/04-keputusan-dan-asumsi.md#a-212) ⚠ | Approval PO berbasis nilai (`order_value_min`) | Setuju |
| [A-213](wms/04-keputusan-dan-asumsi.md#a-213) ⚠ | Kejadian `po_created`/`po_updated`/`po_cancelled` dalam proses | Setuju |
| [A-214](wms/04-keputusan-dan-asumsi.md#a-214) | Terima barang PO lewat GRN; kelebihan terima ditolak | Setuju; bonus vendor (beli 2 gratis 1) dicatat sebagai baris GRN bertanda bonus ([A-267](wms/04b-asumsi-lanjutan.md#a-267)) |
| [A-215](wms/04-keputusan-dan-asumsi.md#a-215) | Batal & tutup sisa PO; PRQ ber-PO terbuka tidak bisa dibatalkan | Setuju |
| [A-216](wms/04-keputusan-dan-asumsi.md#a-216) ⚠ | Permission & role Purchasing tanpa role baru; Kepala Gudang tidak melihat harga | Setuju |
| [A-217](wms/04-keputusan-dan-asumsi.md#a-217) | Cetak PO bernilai uang | Setuju |
| [A-218](wms/04-keputusan-dan-asumsi.md#a-218) | Data demo Purchasing (harga, aturan ke-8) | Setuju |

## 2.17 Baru dari landing page (Part 5) — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-220](wms/04-keputusan-dan-asumsi.md#a-220) | Ajakan landing = Minta demo lewat email | Setuju (email saja) |
| [A-221](wms/04-keputusan-dan-asumsi.md#a-221) | Masuk ke company dari landing (redirect subdomain, tanpa cek keberadaan) | Setuju |
| [A-222](wms/04-keputusan-dan-asumsi.md#a-222) | Kartu paket di landing; harga 0 = Hubungi kami | Ubah: kartu paket tanpa harga, semua "Hubungi kami" ([A-268](wms/04b-asumsi-lanjutan.md#a-268)) |
| [A-223](wms/04-keputusan-dan-asumsi.md#a-223) | Isi & gambar landing (sasaran, bukan klaim; ilustrasi SVG sendiri) | Setuju |
| [A-224](wms/04-keputusan-dan-asumsi.md#a-224) | Bundel landing terpisah tanpa PWA | Setuju |
| [A-225](wms/04-keputusan-dan-asumsi.md#a-225) | Tanpa analitik & tautan kebijakan di Fase 1 | Setuju |

## 2.18 Baru dari alur aktivitas per peran — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-226](wms/04-keputusan-dan-asumsi.md#a-226) | Pemetaan lane alur → peran (bacaan alur per peran) | Setuju |

## 2.19 Baru dari navigasi & hub Proyek — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-227](wms/04-keputusan-dan-asumsi.md#a-227) | Struktur menu: 2 butir atas + 11 grup lipat + cari menu | Setuju |
| [A-228](wms/04-keputusan-dan-asumsi.md#a-228) ⚠ | Hub proyek: kartu ringkas, tombol aksi, 9 tab, tutup proyek dari hub | Setuju |
| [A-229](wms/04-keputusan-dan-asumsi.md#a-229) ⚠ | Form konversi per jenis; Potong satu batang, kerf & sisa otomatis; susut kemasan → waste | Setuju; potong banyak batang dibangun (A-253) |
| [A-230](wms/04-keputusan-dan-asumsi.md#a-230) ⚠ | Layar Pengaturan company: ambang hari/persen, saklar fitur (peringatan bila dipakai item), zona waktu | Setuju |
| [A-231](wms/04-keputusan-dan-asumsi.md#a-231) ⚠ | Halaman penerima bertoken (OTP → form → ringkasan, 410), foto & tanda tangan bukti terima | Setuju |
| [A-232](wms/04-keputusan-dan-asumsi.md#a-232) ⚠ | 11 laporan §9 Stock/Request/Shipment + 4 template cetak TRF/RET/PRQ/GRN di kerangka bersama | Setuju |

## 2.20 Baru dari Sisa Fase 1 di rumah — 25 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-233](wms/04-keputusan-dan-asumsi.md#a-233) | Penerima notifikasi §8 lewat izin (item.create, warehouse.update, reservation.release, request.review/confirm_cancel) | Setuju; approver Role/Jabatan bisa dibatasi divisi pemohon per lapis ([A-269](wms/04b-asumsi-lanjutan.md#a-269)) |
| [A-234](wms/04-keputusan-dan-asumsi.md#a-234) | Kejadian REQ ke pemohon: diputus (kecuali pengaju approval), tanggal janji, hasil pembatalan baris | Setuju |
| [A-235](wms/04-keputusan-dan-asumsi.md#a-235) | Pengingat harian: SLA hari kalender, reservasi per dokumen, sisa umur per serial, aset jatuh tempo + PIC + email | Setuju; SLA hari kerja + kalender libur nasional otomatis ([A-270](wms/04b-asumsi-lanjutan.md#a-270)) |
| [A-236](wms/04-keputusan-dan-asumsi.md#a-236) ⚠ | Semua job harian (eskalasi, pengingat, konfirmasi otomatis, draf PRQ) berhenti selama company ditangguhkan | Setuju |
| [A-237](wms/04-keputusan-dan-asumsi.md#a-237) | Belum dibangun: outbox gagal (tanpa penerbit, F3), bukti bayar → Super Admin, pengingat tagihan WA [F2] | Setuju |
| [A-238](wms/04-keputusan-dan-asumsi.md#a-238) | Tabel lampiran generik: foto pemakaian ISU (≤ 10), foto serah terima keluar AST, arsip PDF opname saat ditutup | Setuju |
| [A-239](wms/04-keputusan-dan-asumsi.md#a-239) | Penuaan tenggat penggantian item tiap jam (`requests:expire-substitutions`) | Setuju |
| [A-240](wms/04-keputusan-dan-asumsi.md#a-240) ⚠ | Override bin beku oleh `bin.manage` pada PCK pending; angka sesi opname digeser −X, bin ⚑ (bukan buka ulang penugasan) | Setuju |
| [A-241](wms/04-keputusan-dan-asumsi.md#a-241) | 16 laporan §9 modul 19–22: izin approval `approval_rule.view`, umur dari pergerakan masuk terakhir, bawaan periode & batas baris | Setuju |
| [A-242](wms/04-keputusan-dan-asumsi.md#a-242) ⚠ | Short pick PCK TRF → TRF backorder pengganti dari gudang lain (bukan asal yang kurang); tanpa gudang cukup → manual | Setuju |
| [A-243](wms/04-keputusan-dan-asumsi.md#a-243) | Rekonsiliasi saldo harian 02:00 hanya melapor ke Admin Company, tanpa perbaikan otomatis | Setuju |
| [A-244](wms/04-keputusan-dan-asumsi.md#a-244) | Bukti terima per unit = satu kondisi utuh per baris SJ berserial/berpotongan (tanpa pindai) | Setuju |
| [A-245](wms/04-keputusan-dan-asumsi.md#a-245) ⚠ | Kelebihan terima GRN transfer/retur → `qty_excess` + ADJ `over_receipt` lewat approval (vendor/PO tetap ditolak) | Setuju |

## 2.22 Kompresi foto otomatis — 26 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-257](wms/04b-asumsi-lanjutan.md#a-257) | Angka kompresi foto: mentah ≤ 20 MB, tersimpan ≤ 5 MB, 1920 px, JPEG 80; PNG transparan/tanda tangan/logo tetap PNG | Setuju |

## 2.23 Impor struktur gudang dari Excel — 26 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-258](wms/04b-asumsi-lanjutan.md#a-258) | Impor zona–rak–level–bin untuk gudang yang sudah ada; semua-atau-tidak, kode ganda ditolak, izin `bin.manage` | Setuju |

## 2.31 Sisa kecil Fase 1 — 27 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-271](wms/04b-asumsi-lanjutan.md#a-271) | Zona, rak (+ level L1…Ln + bin per level), level, dan bin ditambah langsung dari denah; nama zona bisa diubah; kode tetap terkunci | |
| [A-272](wms/04b-asumsi-lanjutan.md#a-272) | Impor gudang dari Excel: kode wajib, tipe dari kode/nama, induk boleh dari baris sebelumnya, kepala via email; bin bawaan ikut; izin `warehouse.create` | |
| [A-273](wms/04b-asumsi-lanjutan.md#a-273) ⚠ | OTP bukti terima otomatis: kanal WhatsApp/SMS platform (driver none/log/http generik), saklar company; OTP tidak tampil ke driver bila terkirim; kirim ulang maks 4, jeda 60 detik; merek penyedia tetap keputusan pemilik | |

## 2.32 WhatsApp Fase 2a — 27 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-274](wms/04b-asumsi-lanjutan.md#a-274) ⚠ | Cloud API langsung (bukan BSP), driver none/log/cloud, 4 template (notifikasi, approval, ringkasan, kode), webhook pusat bertanda tangan | |
| [A-275](wms/04b-asumsi-lanjutan.md#a-275) | Nomor WhatsApp user wajib diverifikasi kode; ganti nomor = verifikasi ulang; isian nomor pindah ke kartu WhatsApp di profil | |
| [A-276](wms/04b-asumsi-lanjutan.md#a-276) | Admin Company pilih kejadian Mati/Langsung/Ringkasan (bawaan mati); preferensi user bawaan menyala; tombol URL lewat pengalih pusat `/buka/…` | |
| [A-277](wms/04b-asumsi-lanjutan.md#a-277) ⚠ | Approval bertombol per lapis *Web & WhatsApp*; Setujui langsung memutus; **Tolak dibalas tautan web** (alasan wajib); token maks 72 jam | |
| [A-278](wms/04b-asumsi-lanjutan.md#a-278) ⚠ | Kuota = template per bulan dari paket; balasan tidak dihitung; habis → WA berhenti + Admin Company diberi tahu sekali per bulan | |
| [A-279](wms/04b-asumsi-lanjutan.md#a-279) | OTP bukti terima lewat template kode WhatsApp bila WA aktif; tautan tetap dibagikan staf | |
| [A-280](wms/04b-asumsi-lanjutan.md#a-280) | Ringkasan harian 07.00: satu pesan per user berisi jumlah + 3 judul | |

## 2.33 Tampilan denah mudah dibaca — 28 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-281](wms/04b-asumsi-lanjutan.md#a-281) | Rak di denah = kotak besar berisi petak bin per level (L1 paling bawah), label level di luar, warna per bin; gambar diperbesar bila petak tidak muat, ukuran fisik tetap | |

## 2.34 Form item lebih ringkas — 28 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-282](wms/04b-asumsi-lanjutan.md#a-282) | Bagian opsional form item jadi tiga tab berdampingan; teks bantuan Mode pelacakan; Sifat baris hanya untuk item Keduanya; pesan validasi Bahasa Indonesia; Beli/Pinjam di REQ ikut item | |

## 2.35 Tiga jenis barang & saklar fitur — 28 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-283](wms/04b-asumsi-lanjutan.md#a-283) | Form item: satu pilihan *Jenis barang* (Barang biasa / Barang berkedaluwarsa / Alat bernomor seri) menggantikan isian teknis; isian potong & tab Vendor tetap dihapus dari form (data tetap); kombinasi lain = *Jenis khusus* read-only; jenis terkunci setelah ada pergerakan stok; impor kolom `jenis_barang` | |
| [A-284](wms/04b-asumsi-lanjutan.md#a-284) | Saklar fitur kini menyaring: mati = pilihan & layar khususnya disembunyikan, data baru ditolak, data lama tetap jalan; company baru: per potong & QC mati, lainnya menyala; seed ulang tidak menimpa pilihan company | |
| [A-285](wms/04b-asumsi-lanjutan.md#a-285) | *Bisa dipotong* hanya syarat mode Potong; Ganti kemasan/Rakit/Bongkar menerima barang habis pakai mana pun (bukan aset/serial) | |
| [A-286](wms/04b-asumsi-lanjutan.md#a-286) | Beli/Pinjam baris REQ dari jenis barang, berupa teks; pilihan hanya item lama Keduanya; aksi simpan menghitung ulang | |

## 2.36 Penerimaan vendor Baik/Rusak/Kurang & kemasan — 28 Sep 2026

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-287](wms/04b-asumsi-lanjutan.md#a-287) | GRN vendor mencatat Dikirim vendor/Baik/Rusak/Kurang tanpa QC; Rusak ke Karantina berkondisi Rusak; hanya Baik mengurangi pesanan | |
| [A-288](wms/04b-asumsi-lanjutan.md#a-288) | `qty_received` = Baik; kolom rusak/kurang/vendor; unit serial/potongan rusak; baris seluruhnya rusak tanpa QC/put-away | |
| [A-289](wms/04b-asumsi-lanjutan.md#a-289) | Batas sisa pesanan = baik + rusak (termasuk draf lain); jumlah vendor boleh melebihi | |
| [A-290](wms/04b-asumsi-lanjutan.md#a-290) | RTV langsung dari barang rusak saat GRN; jatah terpisah dari hasil QC lama | |
| [A-291](wms/04b-asumsi-lanjutan.md#a-291) | Satuan kemasan di baris GRN/Permintaan/Retur, disimpan `uom_id`/`qty_input`; stok tetap satuan dasar; DUS & PACK | |
| [A-292](wms/04b-asumsi-lanjutan.md#a-292) | Semua pembuat dokumen boleh menyimpan kemasan baru; hanya menambah, tercatat di riwayat item | |
| [A-293](wms/04b-asumsi-lanjutan.md#a-293) | Uraian "9 DUS 8 BOX" di saldo, kartu stok, denah, cetakan | |
| [A-294](wms/04b-asumsi-lanjutan.md#a-294) | Tab *Kemasan* di form item; perbaikan kemasan nonaktif hidup lagi | |
| [A-295](wms/04b-asumsi-lanjutan.md#a-295) | Laporan karantina ikut barang Rusak; penerimaan per vendor + Rusak & Kurang; cetak GRN berkolom kondisi | |

## 2.37 Label kemasan induk/isi & penelusuran vendor — 28 Sep 2026

Status enum Di gudang/Keluar/Batal, pemindaian wajib saat keluar, format kode, dan lot otomatis sudah dipilih pemilik produk; yang ditinjau di sini rinciannya.

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-296](wms/04b-asumsi-lanjutan.md#a-296) | Label induk per dus saat GRN selesai (jumlah dus & isi bawaan dari kemasan), `KODE-0001` urut per item; label isi `KODE-0001-0001` bila diminta; hanya bagian Baik; tidak mengubah stok | |
| [A-297](wms/04b-asumsi-lanjutan.md#a-297) | Lot Barang berkedaluwarsa = nomor GRN + baris; isian staf jadi Batch vendor opsional | |
| [A-298](wms/04b-asumsi-lanjutan.md#a-298) | Label serial baru (nomor seri, vendor, GRN) | |
| [A-299](wms/04b-asumsi-lanjutan.md#a-299) | Wajib pindai label di PCK & ISU; wajib = min(jumlah keluar, isi label, saldo); ADJ/opname/CNV/WST/RTV & RET diantar sendiri belum memindai | |
| [A-300](wms/04b-asumsi-lanjutan.md#a-300) | Label utuh kembali Di gudang di GRN transfer/site/retur & ISU pembalik; label sebagian tetap di asal | |
| [A-301](wms/04b-asumsi-lanjutan.md#a-301) | Batal label oleh Kepala Gudang dengan alasan; pilah retur rusak/waste membatalkan label | |
| [A-302](wms/04b-asumsi-lanjutan.md#a-302) | Izin tanpa izin baru; dialog Selesaikan & kartu Label kemasan di GRN; halaman Telusuri label | |
| [A-303](wms/04b-asumsi-lanjutan.md#a-303) | Laporan Barang bermasalah per vendor (rusak, kurang, ditolak QC, rusak kirim, retur rusak; % bermasalah) | |

## 2.38 Saran vendor dari riwayat & riwayat harga beli — 28 Sep 2026

Arah (jenis vendor di PO, termurah tanpa angka di PRQ, vendor tetap berhenti dipakai, nonaktif dengan dampak) sudah dipilih pemilik produk; yang ditinjau rinciannya.

| ID | Asumsi (ringkas) | Keputusan |
|---|---|---|
| [A-304](wms/04b-asumsi-lanjutan.md#a-304) | Saran vendor terakhir & termurah 6 bln dari PO/catatan pemesanan, vendor aktif; bebas diganti, alasan opsional; termurah di PRQ hanya nama & hanya `po.view` | |
| [A-305](wms/04b-asumsi-lanjutan.md#a-305) | Vendor tetap item tidak ditulis/ditampilkan/dipakai lagi (data lama disimpan); daftar vendor menampilkan pesanan 12 bln | |
| [A-306](wms/04b-asumsi-lanjutan.md#a-306) | Form PO menandai harga > 10 % (pengaturan) di atas harga PO terakhir | |
| [A-307](wms/04b-asumsi-lanjutan.md#a-307) | Harga dibandingkan apa adanya (tanpa tarif PPN), tanda PPN ditampilkan; rata-rata tertimbang jumlah | |
| [A-308](wms/04b-asumsi-lanjutan.md#a-308) | Kondisi jenis vendor hanya di PO; aturan demo PRQ toko online → PO toko online | |
| [A-309](wms/04b-asumsi-lanjutan.md#a-309) | Halaman & laporan Riwayat harga beli (3/6/12 bln, grafik, ekspor) untuk `po.view` | |
| [A-310](wms/04b-asumsi-lanjutan.md#a-310) | Dialog nonaktif vendor menampilkan PO/catatan pemesanan terbuka; dokumen tetap berjalan | |

