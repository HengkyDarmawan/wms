# Analisis Fitur WMS Proyek — untuk Penentuan Harga & Proposal Penawaran

**Versi:** 1.0
**Tanggal:** 13 Oktober 2026
**Tujuan:** ringkasan lengkap *apa saja yang sudah ada* di WMS, agar bisa dipakai (1) menilai nilai produk & menentukan harga, (2) menyusun proposal penawaran ke calon klien.
**Sumber:** `docs/wms/01-blueprint.md`, spesifikasi modul `10`–`31`, `docs/purchasing/02`, `docs/00-akun-uji.md`, kode `app/`, `routes/`, menu sidebar. Angka kode dihitung dari repo per tanggal di atas.
**Bukan dokumen desain:** dokumen ini turunan. Bila berbeda dengan `docs/wms/*`, dokumen `docs/wms/*` yang benar.

---

## 1. Ringkasan satu halaman

**WMS Proyek** = aplikasi gudang berbasis web (SaaS multi-company) untuk perusahaan yang **menyediakan material, mesin, dan alat untuk proyek konstruksi/infrastruktur**. Bukan WMS gudang retail biasa: seluruh stok, pengiriman, pemakaian, retur, dan waste **terikat ke proyek**.

| Pertanyaan calon klien | Jawaban singkat |
|---|---|
| Untuk siapa? | Kontraktor, supplier material proyek, penyewa alat berat/scaffolding, perusahaan pipa/besi/kelistrikan dengan gudang pusat + gudang di site proyek |
| Masalah yang diselesaikan | Stok tidak akurat, permintaan material lewat chat/kertas, material "hilang" di proyek, sisa potongan/waste tak tercatat, aset sewa tidak kembali, opname pakai Excel, approval lambat |
| Pembeda utama | (1) **Konversi material berbasis ukuran** (pipa 6 m → 2,5 m, sisa jadi offcut/waste, ada silsilah), (2) **jual-putus + aset dipinjamkan** dalam satu sistem, (3) **gudang mengikuti proyek** + pemakaian material per proyek, (4) **approval mengikuti struktur organisasi**, (5) **portal klien** |
| Model bisnis | Langganan bulanan flat per company (tanpa batas user/gudang/proyek) — keputusan D-02 |
| Teknologi | Laravel 13, PHP 8.3, MySQL 8, Livewire + Alpine + Bootstrap 5; PWA (bisa dipasang di HP, scan kamera) |
| Status | **Fase 1 selesai & berjalan** + Purchasing inti (1b) + WhatsApp (2a, diuji tanpa akun Meta). 770+ uji otomatis hijau (28 Sep), ±950 kasus uji di repo |

### Skala produk (dihitung dari repo)

| Ukuran | Nilai |
|---|---|
| Baris kode PHP (aplikasi) | ±99.700 |
| Baris tampilan Blade | ±25.100 |
| Kelas PHP | 923 (130 layar Livewire, 185 Action/aturan bisnis, 120 model) |
| Migrasi database | 63 (pusat + tenant) |
| Rute/halaman | ±350 (tenant) + halaman pusat/admin |
| Laporan siap pakai | **43** (ekspor Excel + PDF) |
| Jenis dokumen transaksi | 16 (REQ, PCK, SJ, DSC, GRN, PUT, RTV, TRF, RET, ISU, CNV, AST, ADJ, OPN, WST, PRQ) + PO |
| Kasus uji otomatis | ±950 (196 berkas uji) + skrip E2E browser |
| Dokumentasi desain | ±11.000 baris (proses BPMN, ERD 114 tabel, 100+ aturan bisnis `BR-xx`) |

---

## 2. Peran pengguna

| Peran | Fungsi | Kanal |
|---|---|---|
| **Super Admin** (pemilik platform) | Buat company, atur paket & trial, verifikasi bayar, tangguhkan, pantau | Web `/admin` |
| **Admin Company** | Setup, user, role, organisasi, aturan approval, pengaturan | Web |
| **Manajemen** | Dashboard, laporan semua gudang, approver tingkat atas | Web, WhatsApp |
| **Kepala Gudang** | Setuju permintaan/transfer/penyesuaian, selesaikan selisih | Web, PWA, WhatsApp |
| **Staf Gudang** | Terima, QC, put-away, picking, konversi, pemakaian, hitung stok | PWA (utama), Web |
| **Pemohon Internal** | Engineer/PIC proyek: ajukan permintaan, konfirmasi terima, retur | Web, PWA |
| **Klien** (pemilik proyek) | Portal: ajukan permintaan (termasuk barang non-katalog), lacak, konfirmasi/keberatan terima, retur | Portal web |
| **Penindak Lanjut PR** (purchasing) | Catatan pemesanan, PO, harga beli vendor | Web |
| **Auditor Internal** | Opname & rekonsiliasi, read-only mutasi | Web, PWA |
| Driver | **Tanpa akun** (A-311): data di surat jalan, bukti terima oleh penerima | — |

Hak akses = **role × cakupan (gudang/proyek)** per penugasan. Contoh: satu orang bisa Kepala Gudang di Cakung dan Staf di Bekasi. Role bisa dibuat/diubah Admin Company.

---

## 3. Daftar fitur lengkap per modul

Tanda: ✅ selesai & berjalan · 🟡 stub/sebagian · ⏳ belum (fase berikut).

### 3.1 Platform & langganan (untuk Anda sebagai pemilik SaaS)

- ✅ Multi-company: **database terpisah per company**, subdomain per company (`abc.domain.id`), provisioning otomatis (DB + migrasi + data awal + undangan admin).
- ✅ **Mode satu company** (`WMS_SINGLE_COMPANY`) untuk instalasi khusus satu klien / demo.
- ✅ Paket & trial (default 14 hari, bisa diubah per company), kuota penyimpanan & kuota WhatsApp per paket.
- ✅ Tagihan otomatis → klien unggah bukti transfer → Super Admin verifikasi → masa aktif diperpanjang.
- ✅ Siklus langganan terjadwal: Trial → Aktif → Jatuh Tempo (tenggang 7 hari) → Ditangguhkan (hanya-baca 30 hari) → Diakhiri (data 90 hari bisa diekspor).
- ✅ Feature flag per company (WhatsApp, offline, RFID).
- ✅ **Akses dukungan**: Super Admin hanya bisa masuk data company bila Admin Company memberi izin sementara (tercatat).
- ✅ Landing page produk (Blade + Alpine), kartu paket "Hubungi kami" (tanpa harga tampil).
- ✅ Perintah `companies:create-sample` untuk company contoh siap login di server baru; `public/build` ikut git (pasang di cPanel tanpa Node).
- ⏳ Payment gateway (F3), SSO "Masuk dengan NXTG" (F3).

### 3.2 Akses, pengguna & organisasi

- ✅ Login lokal, undangan user (tautan sekali pakai), lupa/reset password, **2FA opsional** (+ kode pemulihan), kunci akun setelah gagal berulang, audit login.
- ✅ Role × cakupan gudang/proyek, role bawaan 10 + role custom, daftar izin `<modul>.<aksi>`.
- ✅ Form pengguna "pilih peran dulu" dengan penjelasan; **Tim site** (penempatan per proyek, berperiode, pengingat H-7).
- ✅ Struktur organisasi: unit, jabatan bertingkat, peta atasan (dipakai approval); akun & jabatan dasar otomatis saat company baru.
- ✅ **Masuk sebagai** (impersonasi, tercatat) untuk dukungan/uji.
- ✅ Perangkat terdaftar per user, tanda tangan digital di profil.
- ✅ Audit log + timeline per dokumen.

### 3.3 Master data

- ✅ **Klien** + PIC klien + akun portal dibuat dari PIC; halaman detail klien.
- ✅ **Proyek**: kode, klien, alamat/titik peta, PIC, beberapa Gudang Site per proyek, status Aktif/Ditutup/Dibatalkan/Diarsipkan; **checklist penutupan proyek** (tidak ada dokumen terbuka, aset kembali, saldo site nol); pindahkan sisa ke proyek lain.
- ✅ **Vendor** (perusahaan/toko/toko online/perorangan), saran vendor dari riwayat pembelian.
- ✅ **Item**: jenis barang (barang biasa / berkedaluwarsa / bernomor seri / per potong), kategori, foto, spesifikasi, **kemasan bertingkat** ("1 dus = 12 box"), satuan dinamis (panjang, berat, volume, luas, jumlah) dengan konversi, stok minimum/titik pesan ulang, ukuran per satuan, wajib QC.
- ✅ Kategori item bertingkat; Satuan (UoM) dinamis; data referensi (alasan, ekspedisi, kendaraan, dll.).
- ✅ **Impor Excel** dengan template, validasi baris, semua-atau-tidak: item, proyek (+klien), vendor, saldo awal, gudang, struktur gudang, tempat simpan.
- ✅ Pilihan dropdown yang **bisa dicari** (cari ke server) di semua form.

### 3.4 Gudang & lokasi

- ✅ Tipe gudang (Utama/Cabang/Site, bisa ditambah), hierarki pohon, penanggung jawab.
- ✅ Hierarki **Zona → Rak → Tingkat → Bin** dengan kode otomatis (mis. `CKG-A-R03-L2-B05`), cetak label barcode/QR.
- ✅ Jenis bin: Penyimpanan, Penerimaan, Loading, Karantina QC, Retur, Waste + bin virtual (Dalam Perjalanan, On-site Proyek).
- ✅ Kapasitas bin (jumlah/berat/volume), rak area lantai, **gabung/pisah bin**, bin beku saat opname.
- ✅ **Denah gudang 2D**: ukuran gedung, posisi zona/rak/objek (pintu, pilar, dock), putar 90°, deteksi tumpukan, lihat isi rak, cetak denah, atur langsung di denah.
- ✅ **Tata letak barang (Tempat Simpan)**: tentukan per barang bin/rak/area, *Khusus Barang Ini*, saran bin otomatis, **popup "pilih tempat di denah"**.
- ✅ **Pindah bin** antar bin satu gudang tanpa dokumen; halaman **Isi Bin** via QR bin.
- ✅ Wizard **Setup awal** yang menjelaskan tiap langkah ("dianggap selesai bila…") + **tombol "Tunjukkan caranya"** (pemandu di layar).

### 3.5 Stok (jantung sistem)

- ✅ **Kartu stok append-only** — stok *hanya* berubah lewat ledger, tidak bisa diedit/dihapus (anti-manipulasi, dasar audit).
- ✅ Saldo per gudang/zona/bin/lot/serial/potongan × kondisi (Tersedia/Karantina/Rusak); stok tersedia = saldo − reservasi.
- ✅ **Reservasi dua tahap** (lunak saat approval, keras saat picking); laporan reservasi menggantung.
- ✅ Lot/batch, serial number, tanggal kedaluwarsa, **per potong (ukuran)**.
- ✅ **Strategi pengambilan**: FIFO, FEFO, manual, "sisa potongan dulu"; lot kedaluwarsa dilewati otomatis.
- ✅ Stok negatif **diblokir**; penguncian baris saldo (aman untuk pengguna bersamaan).
- ✅ **Kunci periode** stok; rekonsiliasi saldo harian otomatis (`stock:reconcile`).
- ✅ Kejadian stok (outbox) siap dikonsumsi modul Akuntansi.
- ✅ **Label kemasan** (induk/isi), wajib dipindai saat barang keluar; telusur label; batal label oleh Kepala Gudang.

### 3.6 Barang keluar: Permintaan → Picking → Pengiriman

- ✅ **Permintaan Material (REQ)** internal & via **portal klien**; baris katalog atau **non-katalog** (item sementara); tanggal dibutuhkan; No. PO klien.
- ✅ Tinjau staf (khusus klien), approval berlapis, backorder wajib punya sumber (transfer/PRQ), baris dipecah ke beberapa gudang sumber, tanggal janji per baris, REQ tambahan, pengganti barang (klien bisa tolak), pembatalan oleh klien.
- ✅ **Picking** (PCK): saran bin/lot, alokasi keras, short pick beralasan; **picking otomatis** (saklar).
- ✅ **Surat Jalan (SJ)**: kendaraan sendiri/ekspedisi/diantar; gabung beberapa REQ; SJ tanpa PCK; SJ jemput retur; cetak PDF dengan QR.
- ✅ **Bukti terima**: per baris baik/rusak/kurang, foto, tautan bertoken + **OTP** (WhatsApp), akun portal klien; klien **konfirmasi/ajukan keberatan** (3 hari, otomatis dikonfirmasi bila lewat).
- ✅ **Selisih Pengiriman (DSC)** otomatis: kembali/disesuaikan/klaim/kirim pengganti.
- ✅ Dokumen terkait & linimasa antar dokumen ("SJ001 dari REQ001").

### 3.7 Barang masuk: Penerimaan, QC, Put-away, Retur vendor

- ✅ **GRN**: dari vendor (merujuk catatan pemesanan/PO) atau dari transfer; per baris **Baik / Rusak / Kurang**; bonus vendor; cross-dock ke Loading Area.
- ✅ **QC** per baris: Lolos / Karantina / Ditolak (saklar company).
- ✅ **Put-away** dengan saran bin, **pindai per baris**, layar *Menunggu Dimasukkan*, "Pindai di HP".
- ✅ **Retur ke Vendor (RTV)** dari karantina atau langsung dari barang rusak.
- ✅ Lot otomatis dari GRN, label serial, laporan **barang bermasalah per vendor**.

### 3.8 Transfer & Retur dari proyek

- ✅ **Transfer (TRF)** antar gudang, **antar proyek**, **antar titik dalam proyek** (jalur ringan); aset On-site antar proyek.
- ✅ **Retur dari Proyek (RET)** dari klien/site → SJ balik → GRN retur → **pemilahan** (layak/rusak/offcut/waste); jual-putus boleh diretur.

### 3.9 Di proyek: Pemakaian, Konversi, Waste, Aset

- ✅ **Pemakaian material (ISU)** di Gudang Site, pindai barang, pembalik dengan approval, cetak Bukti Pemakaian.
- ✅ **Konversi (CNV)**: potong, rakit, bongkar, ganti kemasan; **potong banyak batang** dalam satu dokumen; neraca ukuran wajib seimbang; kerf; sisa ≥ minimum → **offcut** kembali ke stok, sisa kecil → **waste**; **silsilah dua arah**.
- ✅ **Berita Acara Waste (WST)** dengan bukti foto: dibuang / dijual scrap / dipakai ulang.
- ✅ **Aset dipinjamkan**: wajib serial; siklus Tersedia → Dipinjam → Dikembalikan → Diperiksa → Maintenance/Rusak/Hilang; **serah terima (AST)** otomatis dari SJ/RET; **pemeriksaan** grade A–D + skor 0–100 % + foto; hari pakai, meter pemakaian, **sisa umur %** dengan peringatan; **jejak lokasi aset**; peringatan lewat jatuh tempo harian; kehilangan → ADJ.

### 3.10 Opname & penyesuaian

- ✅ **Sesi stock opname**: bulanan/tahunan/ad-hoc/**pemeriksaan mendadak**; cakupan gudang/zona/bin; pembekuan lokasi.
- ✅ **Hitung buta**, toleransi berjenjang (relatif + absolut), **hitung ulang oleh orang berbeda**, rekonsiliasi, approval tingkat sesi, akar masalah.
- ✅ Pemisahan tugas (penghitung ≠ penyetuju), halaman "Hitungan saya" (HP), draf hitung tersimpan di perangkat.
- ✅ **Penyesuaian stok (ADJ)** manual selalu approval + pembalik; **saldo awal** masa setup langsung tercatat.
- ⏳ Cycle count ABC, auditor eksternal (F2).

### 3.11 Pembelian

- ✅ **Purchase Request (PRQ)**: dari backorder REQ / titik pesan ulang (draf harian otomatis) / manual; approval opsional menurut jenis vendor; **catatan pemesanan** per vendor/toko online.
- ✅ **Purchase Order (PO)** per vendor dari PRQ, harga beli, flag PPN, **approval berdasarkan nilai**, PO melebihi sisa PRQ dengan alasan, PO → GRN, cetak PO.
- ✅ **Harga beli vendor** + riwayat, peringatan kenaikan harga, pembanding termurah/terakhir/rata-rata, riwayat harga per item.
- ⏳ Evaluasi vendor, perbandingan penawaran, three-way match (F3).
- ⚠️ Harga **hanya** di domain Purchasing; layar/cetak/kejadian WMS lain **tanpa harga** (D-07).

### 3.12 Approval engine (bersama semua dokumen)

- ✅ Aturan per jenis dokumen, **berlapis**, approver: user/jabatan/role/atasan 1–2 tingkat/Kepala Gudang/PIC proyek.
- ✅ Cara putus: berurutan / salah satu / semua; kondisi: gudang, proyek, kategori, kepemilikan, jumlah, klien, jenis vendor, nilai PO.
- ✅ **Eskalasi** otomatis bila lewat batas waktu, **delegasi** berperiode, **simulasi aturan**, **peta approval**, aturan dasar sekali klik, SoD (pembuat ≠ penyetuju), inbox "tugas saya".
- ✅ Hari kerja & kalender libur per company.
- ✅ **Approval via WhatsApp** bertombol Setujui/Tolak (token sekali pakai) — dibangun, diuji dengan driver `log`/HTTP palsu.

### 3.13 Notifikasi

- ✅ Lonceng in-app + halaman notifikasi + preferensi per kejadian; email (tugas approval, tagihan, aset jatuh tempo); pengingat harian (SLA tinjau, reservasi menggantung, aset, sisa umur).
- ✅ **WhatsApp Cloud API** (satu nomor platform): notifikasi langsung/ringkasan harian, approval bertombol, verifikasi nomor, OTP bukti terima, log & kuota per company.
  - ⚠️ Butuh akun Meta/WhatsApp Business + persetujuan template (pekerjaan pemilik produk, O-03).

### 3.14 Laporan & dashboard

- ✅ **43 laporan** dengan filter periode, ekspor **Excel & PDF**. Kelompok utama:
  - Stok: kartu stok, saldo stok, mutasi periode, stok dalam perjalanan, karantina menurut umur, reservasi menggantung, titik pesan ulang, barang sementara.
  - Proyek: **material per proyek** (diminta/terkirim/terpakai/diretur/on-site/waste), daftar proyek, retur per proyek, rekap pengiriman per klien.
  - Konversi & waste, aset dipinjamkan, barang rusak (posisi, bin retur).
  - Permintaan terbuka, tanpa sumber, antri tinjau, pengganti menunggu, pengiriman, kinerja pengiriman, transfer/RTV terbuka, put-away tertunda.
  - Opname: akurasi stok, tren akurasi, selisih terbesar, akar masalah, penyesuaian per alasan, short pick.
  - Approval: waktu keputusan, eskalasi, tugas terbuka, dokumen menunggu.
  - Pembelian: penerimaan per vendor, barang bermasalah per vendor.
  - Keamanan: log login, peran & cakupan pengguna.
- ✅ Beranda dinamis: kartu "pekerjaan menunggu" sesuai izin & cakupan.

### 3.15 Cetak, label & berkas

- ✅ Layout dokumen per company (logo, kop, warna, footer, tanda tangan).
- ✅ Cetak PDF: Surat Jalan, Bukti Terima, Picklist, Bukti Pemakaian, BA Serah Terima Aset, BA Waste, Laporan Opname, BA Penyesuaian, BA Selisih, Surat Retur Vendor, PO; **segel QR yang bisa diverifikasi**; riwayat cetak & tanda "cetak ulang".
- ✅ **Label**: Code128 + QR untuk item, lot, serial, potongan, bin, aset, dokumen; **ukuran label & desain label bisa ditata** per company.
- ✅ Unggah berkas/foto dengan **kompresi otomatis** (simpan ≤ 5 MB), penyimpanan S3-compatible.

### 3.16 PWA / mobile

- ✅ Bisa dipasang di HP (installable), **scan kamera** barcode/QR (semua browser, termasuk iPhone), halaman offline, draf bukti terima & hitungan opname tersimpan di perangkat.
- ⏳ **Offline penuh + sinkron** (F2b).

---

## 4. Yang TIDAK ada (penting untuk proposal — jangan dijanjikan)

| Hal | Status / alternatif |
|---|---|
| Nilai persediaan, harga modal/jual, penagihan sewa, jurnal | Di luar WMS — modul **Akuntansi** terpisah (WMS menyiapkan kejadian stok) |
| Pembayaran/payment gateway otomatis | F3; sekarang transfer manual + verifikasi |
| Offline penuh (tanpa sinyal sama sekali) | F2b; sekarang hanya draf lokal |
| RFID | F2 (stub) |
| Maintenance terjadwal aset | F2 |
| Resep konversi berulang | F2 |
| BoQ/rencana kebutuhan material per proyek (banding rencana vs aktual) | F2 (stub) |
| REST API publik / integrasi ERP | F3 |
| SSO NXTG | F3 |
| Editor template dokumen penuh | F2 (sekarang layout induk + template bawaan) |
| Auditor eksternal (akun tamu), cycle count ABC | F2 |
| Penjadwalan proyek, RAB, SDM | Di luar produk |
| Transfer stok antar company | Di luar produk (DB terpisah) |
| Aplikasi native Android/iOS | Tidak ada — PWA |
| Bahasa Inggris | Siap i18n, belum diterjemahkan (UI Indonesia) |

### Catatan kejujuran status (untuk due diligence klien)

- Fase 1 + Purchasing inti + WhatsApp **sudah dibangun & lulus uji otomatis**; belum tentu sudah diuji di produksi nyata dengan data klien.
- **±35 asumsi desain masih "Perlu validasi"** oleh pemilik produk (lihat `docs/00-tinjauan-asumsi-2026-09-25.md`); perilaku bisa berubah.
- Nama produk/brand (O-07), harga paket (O-08), naskah ketentuan layanan & kebijakan privasi (O-11), legalitas tanda tangan digital (O-13) **belum final**.
- WhatsApp belum diuji dengan nomor Meta sungguhan.
- Beberapa pekerjaan kerja belum di-commit (lihat `git status`) — pastikan commit & tag rilis sebelum demo ke klien.

---

## 5. Nilai jual (kalimat siap pakai di proposal)

1. **"Stok yang bisa dipercaya"** — kartu stok tidak bisa diedit; stok negatif diblokir; opname hitung buta + hitung ulang; rekonsiliasi harian otomatis.
2. **"Setiap material tahu ke proyek mana perginya"** — permintaan, pengiriman, pemakaian, retur, konversi, waste semua terikat proyek; laporan Material per Proyek.
3. **"Pipa 6 m dipotong 2,5 m? Sisanya tercatat."** — konversi, offcut, waste, silsilah.
4. **"Alat berat & scaffolding tidak hilang"** — siklus aset, pemeriksaan kondisi + foto, peringatan jatuh tempo, sisa umur.
5. **"Klien Anda bisa lihat sendiri"** — portal klien: ajukan, lacak, konfirmasi terima, keberatan.
6. **"Approval sesuai struktur organisasi Anda"** — berlapis, eskalasi otomatis, delegasi, via WhatsApp.
7. **"Aman & bisa diaudit"** — database terpisah per company, timeline + audit log, 2FA, SoD, akses dukungan berizin.
8. **"Siap dipakai HP di lapangan"** — PWA, scan kamera, tanpa instal dari store.
9. **"Mulai cepat"** — wizard setup, impor Excel, pemandu di layar, aturan approval dasar otomatis.
10. **"Tanpa batas user, gudang, proyek"** — harga flat per company.

---

## 6. Bahan menentukan harga

> Angka di bagian ini **usulan/ilustrasi**, bukan keputusan. Harga paket resmi masih O-08 (belum diputuskan) dan landing sengaja tanpa harga (A-268). Sesuaikan dengan riset pasar terbaru dan struktur biaya Anda.

### 6.1 Dua model penjualan

| Model | Cocok untuk | Komponen |
|---|---|---|
| **A. SaaS (di server Anda)** — model utama produk | Banyak klien kecil–menengah | Biaya setup sekali + langganan bulanan flat per company |
| **B. Instalasi khusus satu company** (`WMS_SINGLE_COMPANY`, di server klien/cPanel) | Klien besar/privat, minta data di server sendiri | Lisensi/implementasi sekali + biaya pemeliharaan tahunan |

### 6.2 Faktor biaya Anda yang perlu masuk hitungan

| Biaya | Catatan |
|---|---|
| Server/hosting + backup harian per DB (NFR-05, retensi ≥ 30 hari) | Bertambah per company karena DB terpisah |
| Penyimpanan berkas (S3-compatible) | Kuota per paket (`storage_quota_mb`) |
| **WhatsApp Cloud API** | Dibebankan Meta per pesan (template utility ditagih; balasan 24 jam ditagih setelah 1.000 gratis/bulan) — pakai **kuota per paket** (`wa_quota`) atau teruskan biaya (O-04) |
| Email transaksional | Kecil |
| Dukungan & onboarding | Terbesar di awal: setup gudang/rak/bin, impor Excel, pelatihan |
| Pemeliharaan & perbaikan | Migrasi ke semua tenant, pembaruan Laravel/PHP |

### 6.3 Struktur paket yang disarankan (bisa diubah)

Karena **semua fitur sama di semua paket** (keputusan produk) dan tidak ada batas user/gudang/proyek, pembeda paket yang masuk akal secara teknis:

| Pembeda | Starter | Business | Enterprise |
|---|---|---|---|
| Fitur inti Fase 1 | ✔ | ✔ | ✔ |
| Purchasing (PO & harga beli) | opsional | ✔ | ✔ |
| WhatsApp (kuota pesan/bulan) | — / kecil | sedang | besar |
| Kuota penyimpanan berkas | kecil | sedang | besar |
| Dukungan | email | email + chat | prioritas + SLA |
| Onboarding | video/tutorial | 1 sesi remote | on-site + impor data |
| Instalasi khusus (model B) | — | — | ✔ (harga terpisah) |

Alternatif: tetap satu paket flat + **add-on** (WhatsApp, Purchasing, penyimpanan ekstra, onboarding).

### 6.4 Cara menghitung (kerangka, isi angkanya sendiri)

```
Harga bulanan minimum = (biaya infrastruktur per company + biaya WhatsApp rata-rata
                         + alokasi dukungan per company) ÷ (1 − margin target)

Biaya setup (sekali)  = jam onboarding × tarif jam
                        + jam impor/pembersihan data × tarif jam
                        + pelatihan (hari × tarif hari)
```

Pembanding nilai (sebagai jangkar, bukan patokan harga):

- **Nilai pengganti pengembangan**: ±100 ribu baris PHP + 25 ribu baris tampilan + ±11 ribu baris dokumentasi desain. Pembangunan serupa dari nol oleh tim kontraktor lazimnya berbulan-bulan dengan beberapa orang — dasar untuk harga **lisensi/instalasi khusus** (model B).
- **Nilai bagi klien**: kurangnya selisih stok, material hilang/tidak tertagih, waktu admin koordinasi, aset tak kembali, waktu opname. Minta klien menaksir **kerugian per bulan akibat stok/material tidak terlacak** — harga langganan idealnya sebagian kecil dari angka itu.
- Riset sejenis ada di `docs/wms/02-riset-wms-sejenis.md` (produk pembanding & pasar Indonesia).

### 6.5 Diskon & promosi yang lazim

Trial 14–30 hari tanpa kartu; diskon tahunan (bayar 12 bulan, bonus 1–2 bulan); onboarding gratis untuk kontrak ≥ 12 bulan; paket pilot 1 proyek 3 bulan.

---

## 7. Kerangka proposal penawaran

Gunakan urutan berikut; bagian dalam kurung = isi dari dokumen ini.

1. **Halaman judul** — nama produk (O-07 belum final), nama klien, tanggal, masa berlaku penawaran.
2. **Ringkasan eksekutif** — masalah klien → solusi → hasil yang dijanjikan (§1, §5).
3. **Pemahaman kebutuhan klien** — hasil diskusi: jumlah gudang, proyek berjalan, jenis barang (pipa/besi/alat), proses sekarang, pain point.
4. **Solusi & fitur** — alur utama bergambar: *Permintaan → Approval → Picking → Surat Jalan → Terima → Pemakaian → Retur/Konversi/Waste* (§3.6–3.9; diagram: `docs/00-audit/alur-proses.html`, `alur-per-peran.html`).
5. **Fitur per peran** — tabel peran (§2) dipetakan ke orang di perusahaan klien.
6. **Pembeda** — konversi/offcut, aset, portal klien, approval, denah gudang (§5).
7. **Cakupan implementasi** — apa yang termasuk: setup company, konfigurasi gudang/rak/bin, impor item & saldo awal, aturan approval, pelatihan, go-live, masa pendampingan.
8. **Di luar cakupan** — dari §4 (akuntansi, API, offline penuh, dsb.). Tulis eksplisit.
9. **Rencana & jadwal** — contoh: Minggu 1 setup & impor data · 2 pelatihan per peran · 3 pilot satu gudang/proyek · 4 go-live & pendampingan.
10. **Investasi** — tabel harga: setup sekali, langganan bulanan, add-on (WhatsApp, Purchasing), onboarding; syarat pembayaran; PPN.
11. **Keamanan & kepatuhan** — DB terpisah per company, backup harian, 2FA, audit log, akses dukungan berizin, UU PDP (NFR-11).
12. **Dukungan & SLA** — jam layanan, kanal, waktu respons, pembaruan.
13. **Asumsi & ketergantungan klien** — data master dari klien, PIC klien, akun WhatsApp Business (bila pakai), perangkat HP/scanner.
14. **Syarat & ketentuan** — masa berlangganan, trial, penangguhan (tenggang 7 hari → hanya-baca 30 hari → diakhiri, data 90 hari bisa diekspor), kepemilikan data, penghentian.
15. **Lampiran** — daftar 43 laporan, daftar dokumen transaksi, cuplikan layar (ambil dari company DEMO), akun demo untuk uji coba.

### Demo ke calon klien

- Company contoh: `php83 artisan companies:create-sample` atau seeder DEMO (akun di `docs/00-akun-uji.md`).
- Skenario demo 15 menit yang kuat: klien (portal) ajukan REQ → Kepala Gudang approve → staf picking (scan) → SJ cetak → bukti terima → staf potong pipa (konversi, sisa offcut) → laporan **Material per Proyek**.
- Hati-hati: `WMS_DEMO_LOGIN` bawaan mati; nyalakan hanya di server demo.

---

## 8. Daftar periksa sebelum menawarkan

- [ ] Putuskan nama produk & brand (O-07) dan harga paket (O-08).
- [ ] Putuskan model: SaaS saja, atau juga instalasi khusus.
- [ ] Naskah ketentuan layanan & kebijakan privasi final (O-11).
- [ ] Daftar WhatsApp Business bila menjual fitur WhatsApp (O-03).
- [ ] Commit & rilis versi stabil; jalankan `php83 artisan test` dan `npm run build`.
- [ ] Siapkan server demo + data contoh; cuplikan layar untuk proposal.
- [ ] Validasi sisa asumsi *Perlu validasi* yang memengaruhi janji ke klien.
- [ ] Tentukan kebijakan dukungan, SLA, dan backup yang akan dijanjikan.

---

## 9. Peta rujukan

| Butuh detail | Buka |
|---|---|
| Gambaran produk lengkap | `docs/wms/01-blueprint.md` |
| Istilah resmi UI | `docs/wms/03-glosarium.md` |
| Semua status dokumen | `docs/wms/06-katalog-status-dan-enum.md` |
| Aturan bisnis | `docs/wms/05-aturan-bisnis.md` |
| Per modul | `docs/wms/10`–`31`, `docs/purchasing/02` |
| Akun demo | `docs/00-akun-uji.md` |
| Cara menjalankan | `docs/00-setup-lokal.md`, `docs/00-tutorial-pemula.md`, `TUTORIAL-SERVER.txt` |
| Alur proses bergambar | `docs/00-audit/alur-proses.html`, `alur-per-peran.html` |
| Riset produk sejenis | `docs/wms/02-riset-wms-sejenis.md` |
