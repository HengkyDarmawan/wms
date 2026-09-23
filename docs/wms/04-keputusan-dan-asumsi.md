# Keputusan, Asumsi & Isu Terbuka

**Versi:** 0.3
**Tanggal:** 23 September 2026
**Status:** D-01–D-27 berlaku; A-01–A-24 disetujui; A-25–A-49 **menunggu validasi**; A-16 diusulkan berubah
**Dokumen terkait:** [Blueprint](01-blueprint.md) · [Aturan Bisnis](05-aturan-bisnis.md) · [Laporan audit](../00-laporan-audit-dokumentasi.md)

Cara validasi: isi kolom **Validasi** dengan `Setuju`, `Ubah: …`, atau `Hapus`. Asumsi yang disetujui **tetap memakai nomor A-xx** dan berlaku setara keputusan. Keputusan tidak diedit; bila berubah, dibuat keputusan baru dan kolom *Diganti oleh* diisi.

Setiap ID punya anchor (`#d-01`, `#a-25`, `#o-11`) agar bisa ditautkan dari dokumen lain.

---

## 1. Log keputusan

| ID | Keputusan | Tanggal | Alasan | Konsekuensi | Diganti oleh |
|---|---|---|---|---|---|
| <a id="d-01"></a>D-01 | WMS **dibangun baru dari nol**; prototipe `warehouse.sipembantu.com` hanya referensi | Sep 2026 | Prototipe menyimpan stok di satu kolom, tanpa ledger, banyak bug struktural | Migrasi data prototipe perlu keputusan sendiri ([O-12](#o-12)) | — |
| <a id="d-02"></a>D-02 | **SaaS multi-company**, **langganan bulanan flat per company** | Sep 2026 | Model bisnis pemilik produk | Tidak ada batasan user/gudang per paket; batas dipakai untuk kuota WA & penyimpanan | — |
| <a id="d-03"></a>D-03 | **Database terpisah per company** + satu database pusat | Sep 2026 | Isolasi data & kemudahan backup/restore per klien | Migrasi harus dijalankan ke semua tenant; paket tenancy perlu verifikasi ([O-01](#o-01)) | — |
| <a id="d-04"></a>D-04 | Stack **Laravel 13**, **PHP 8.3** (kompatibel 8.4), **MySQL** | Sep 2026 | Keahlian tim & server yang ada | Paket pihak ketiga harus kompatibel Laravel 13 | — |
| <a id="d-05"></a>D-05 | UI back-office **NexaDash**; **Blade + Livewire + Alpine.js** | Sep 2026 | Template sudah dimiliki; tanpa SPA terpisah | Komponen Livewire per layar; aset di-bundle lokal | — |
| <a id="d-06"></a>D-06 | Landing page bergaya **indonesia.travel**; gambar sementara berlisensi bebas | Sep 2026 | Preferensi pemilik produk | Brand & harga paket ditentukan di Part 5 ([O-07](#o-07), [O-08](#o-08)) | — |
| <a id="d-07"></a>D-07 | WMS **hanya mengelola kuantitas**; nilai uang di modul **Akuntansi** | Sep 2026 | Pemisahan tanggung jawab; WMS bisa dijual tanpa akuntansi | Kondisi approval tidak boleh memakai nilai uang (BR-APR-07); kejadian stok tanpa harga | — |
| <a id="d-08"></a>D-08 | **Purchasing** modul terpisah; WMS menerbitkan Purchase Request | Sep 2026 | Sama dengan D-07 | Fase 1 memakai tindak lanjut PR manual ([A-47](#a-47)) | — |
| <a id="d-09"></a>D-09 | **Rak & bin wajib** di setiap gudang | Sep 2026 | Lokasi adalah entitas (P-06) | Gudang sederhana memakai bin default ([A-02](#a-02)) | — |
| <a id="d-10"></a>D-10 | **Semua konversi material wajib terdokumentasi di menu Proyek** | Sep 2026 | Pelacakan material per proyek adalah pembeda utama | Konversi non-proyek memakai Proyek Internal ([A-06](#a-06)) | — |
| <a id="d-11"></a>D-11 | Konversi dicatat **berdasarkan ukuran**; sisa = offcut/waste; **satuan dinamis** | Sep 2026 | Kasus pipa/scaffolding | Item per potong punya aturan UoM khusus ([A-36](#a-36)) | — |
| <a id="d-12"></a>D-12 | Model kepemilikan: **jual putus**, **dipinjamkan**, **keduanya** | Sep 2026 | Kebutuhan bisnis | Aturan per baris untuk *keduanya* ([A-38](#a-38)) | — |
| <a id="d-13"></a>D-13 | **Semua metode stok tersedia** dan dipilih lewat pengaturan | Sep 2026 | "Semua metode, tinggal pilih" | Matriks kombinasi yang sah (BR-STK-11) | — |
| <a id="d-14"></a>D-14 | **Barcode, QR, RFID** didukung; company memilih | Sep 2026 | Kebutuhan bisnis | RFID di Fase 2 ([O-10](#o-10)) | — |
| <a id="d-15"></a>D-15 | **Hierarki gudang dinamis**; gudang site terikat proyek | Sep 2026 | Gudang mengikuti proyek | Penutupan proyek menonaktifkan Gudang Site ([A-40](#a-40)) | — |
| <a id="d-16"></a>D-16 | **Struktur organisasi dikelola di WMS**; approval mengikutinya | Sep 2026 | Tidak ada HRIS terhubung | Approver "atasan langsung" bergantung data ini | — |
| <a id="d-17"></a>D-17 | **Approval berlapis** yang bisa diatur sendiri | Sep 2026 | Kebutuhan bisnis | Edge case diatur BR-APR | — |
| <a id="d-18"></a>D-18 | **Approval dipisah dari tugas** | Sep 2026 | 5 "approval" di diagram prototipe ternyata tugas | P-05 | — |
| <a id="d-19"></a>D-19 | **Notifikasi & approval via WhatsApp** tanpa login ulang | Sep 2026 | Approver jarang buka web | Fase 2; biaya Meta ([O-03](#o-03), [O-04](#o-04)) | — |
| <a id="d-20"></a>D-20 | **Pemohon dua jenis:** internal company dan klien (portal) | Sep 2026 | Kebutuhan bisnis | Permintaan klien ditinjau staf ([A-07](#a-07)) | — |
| <a id="d-21"></a>D-21 | Klien bisa meminta **barang non-katalog**; kekurangan diteruskan ke Purchasing | Sep 2026 | Kebutuhan bisnis | Pemetaan non-katalog ke item ([A-39](#a-39)) | — |
| <a id="d-22"></a>D-22 | **Auditor internal dan eksternal**; auditor login sendiri; riwayat audit tersimpan | Sep 2026 | Kebutuhan audit | Eksternal di Fase 2 | — |
| <a id="d-23"></a>D-23 | Stock opname **bulanan & tahunan**, per gudang/zona, **dashboard konsolidasi** | Sep 2026 | Kebutuhan audit | Cycle count ABC Fase 2 | — |
| <a id="d-24"></a>D-24 | **PWA** dengan mode offline dan sinkron otomatis | Sep 2026 | Sinyal lemah di site | Offline penuh Fase 2; Fase 1 draf lokal ([A-49](#a-49)) | — |
| <a id="d-25"></a>D-25 | **Template dokumen** bisa diatur company | Sep 2026 | Kebutuhan bisnis | Editor penuh Fase 2 | — |
| <a id="d-26"></a>D-26 | **Login lokal dulu**, SSO NXTG menyusul; SSO hanya identitas | Sep 2026 | SSO NXTG belum siap multi-tenant | Callback pusat ([A-28](#a-28)), pemilih company ([A-48](#a-48)) | — |
| <a id="d-27"></a>D-27 | Pembayaran langganan **transfer manual** dulu; payment gateway menyusul | Sep 2026 | Skala awal kecil | Verifikasi manual oleh Super Admin | — |

## 2. Asumsi

### 2.1 Disetujui (v0.2)

| ID | Asumsi | Validasi |
|---|---|---|
| <a id="a-01"></a>A-01 | Company diakses lewat **subdomain** sendiri, mis. `abc.wms-domain.id` | Setuju (23 Sep 2026) |
| <a id="a-02"></a>A-02 | Gudang site sederhana boleh memakai **satu zona & satu bin default** | Setuju (23 Sep 2026) |
| <a id="a-03"></a>A-03 | Satu company boleh punya **lebih dari satu gudang utama** | Setuju (23 Sep 2026) |
| <a id="a-04"></a>A-04 | Satu user bisa punya **lebih dari satu role** dan akses ke **banyak gudang/proyek** — *diperjelas oleh [A-46](#a-46): cakupan melekat pada penugasan role* | Setuju (23 Sep 2026) |
| <a id="a-05"></a>A-05 | Satu akun user hanya milik **satu company** (email sama boleh dipakai terpisah di company lain) | Setuju (23 Sep 2026) |
| <a id="a-06"></a>A-06 | Konversi untuk persiapan stok umum dicatat di **Proyek Internal** | Setuju (23 Sep 2026) |
| <a id="a-07"></a>A-07 | Permintaan dari klien selalu **ditinjau staf company** dulu sebelum approval | Setuju (23 Sep 2026) |
| <a id="a-08"></a>A-08 | Jenis dokumen **tanpa aturan approval** langsung disetujui | Setuju (23 Sep 2026) |
| <a id="a-09"></a>A-09 | **Penyesuaian stok manual** selalu butuh minimal satu lapis approval; penyesuaian hasil opname disetujui di tingkat sesi | Setuju (23 Sep 2026) |
| <a id="a-10"></a>A-10 | Strategi pengambilan bawaan: **FIFO** | Setuju (23 Sep 2026) |
| <a id="a-11"></a>A-11 | Trial bawaan **14 hari**, bisa diubah Super Admin per company | Setuju (23 Sep 2026) |
| <a id="a-12"></a>A-12 | Lewat jatuh tempo: tenggang **7 hari** → **ditangguhkan (hanya-baca) 30 hari** → diakhiri; data disimpan **90 hari** lagi dan bisa diekspor | Setuju (23 Sep 2026) |
| <a id="a-13"></a>A-13 | Backup harian per database, retensi **30 hari** | Setuju (23 Sep 2026) |
| <a id="a-14"></a>A-14 | MySQL **8.0 atau lebih baru** | Setuju (23 Sep 2026) |
| <a id="a-15"></a>A-15 | Bahasa awal **Bahasa Indonesia**; Inggris menyusul | Setuju (23 Sep 2026) |
| <a id="a-16"></a>A-16 | Toleransi opname bawaan: **≤ 1%** otomatis, **> 1–5%** hitung ulang, **> 5%** approval | Setuju (23 Sep 2026) — **usulan ubah** menjadi ambang relatif **dan** absolut, lihat [A-42](#a-42) |
| <a id="a-17"></a>A-17 | Format nomor bawaan `{KODE}/{GUDANG}/{TAHUN}/{BULAN}/{URUT}`, reset tiap bulan | Setuju (23 Sep 2026) — segmen `{GUDANG}` untuk dokumen tanpa gudang tunggal diatur [A-43](#a-43) |
| <a id="a-18"></a>A-18 | Batas eskalasi approval bawaan **24 jam** (kalender) | Setuju (23 Sep 2026) |
| <a id="a-19"></a>A-19 | **Panjang minimum offcut wajib** untuk item yang bisa dipotong | Setuju (23 Sep 2026) |
| <a id="a-20"></a>A-20 | **Driver adalah user company.** Bila ekspedisi pihak ketiga, bukti terima diisi penerima di site — mekanismenya di [A-41](#a-41) | Setuju (23 Sep 2026) |
| <a id="a-21"></a>A-21 | Klien melihat **stok on-site proyeknya** (termasuk Gudang Site proyek itu, aset di proyek, dan riwayat terkirim), **tidak** melihat gudang company lain — *diperjelas v0.3* | Setuju (23 Sep 2026) |
| <a id="a-22"></a>A-22 | Satu Permintaan Material hanya untuk **satu proyek** | Setuju (23 Sep 2026) |
| <a id="a-23"></a>A-23 | Foto/lampiran dikompres otomatis, maksimal **5 MB per file** | Setuju (23 Sep 2026) |
| <a id="a-24"></a>A-24 | WhatsApp dikirim dari **satu nomor platform** dengan nama company di isi pesan | Setuju (23 Sep 2026) |

### 2.2 Menunggu validasi (v0.2 → v0.3)

| ID | Asumsi | Sumber | Validasi |
|---|---|---|---|
| <a id="a-25"></a>A-25 | **Efek pengiriman ke stok:** ke Gudang Site = transfer (tetap stok company); jual-putus ke klien keluar dari stok saat bukti terima; aset tetap stok company di bin *On-site Proyek* | Blueprint 6.6, BR-SJ-04 | **Perlu validasi** |
| <a id="a-26"></a>A-26 | Barang jual-putus yang sudah diterima klien **boleh diretur** lewat dokumen Retur dengan approval; kejadian ke Akuntansi ditandai retur penjualan | Blueprint 7, BR-RET-03 | **Perlu validasi** |
| <a id="a-27"></a>A-27 | Super Admin **tidak bisa membuka data operasional company** tanpa akses dukungan sementara dari Admin Company | Blueprint 14, BR-SUB-04 | **Perlu validasi** |
| <a id="a-28"></a>A-28 | Login SSO NXTG memakai **satu alamat callback pusat**, lalu diarahkan ke subdomain company | Blueprint 13 | **Perlu validasi** |

### 2.3 Baru dari audit v0.3 — menunggu validasi

Dikelompokkan per tema. Kolom *Default bila tidak dijawab* adalah nilai yang dipakai dokumen sekarang.

**Tema A — Model stok & reservasi**

| ID | Asumsi | Default bila tidak dijawab | Validasi |
|---|---|---|---|
| <a id="a-29"></a>A-29 | **Model stok tunggal:** lokasi = bin (termasuk bin virtual *Dalam Perjalanan* milik gudang asal dan *On-site Proyek* per proyek, hanya aset); kondisi = Tersedia/Karantina/Rusak; *Dicadangkan* adalah tabel reservasi, bukan status. Aset wajib serial; peminjaman ke orang lewat Proyek Internal; hilang/dihapuskan keluar ledger lewat ADJ | Dipakai (BR-STK-02, BR-AST-01) | **Perlu validasi** |
| <a id="a-30"></a>A-30 | **Reservasi dua tahap:** approval REQ/TRF = reservasi lunak (item × gudang); picking = alokasi keras (bin/lot/serial/potongan). Setiap baris yang disetujui **wajib punya sumber** (stok, transfer, atau PR); menggantikan P-02 v0.2. Reservasi dilepas saat tolak/batal/tutup-dengan-sisa/proyek ditutup | Dipakai (BR-STK-03–05, BR-REQ-05) | **Perlu validasi** |
| <a id="a-31"></a>A-31 | **Gudang sumber** per baris REQ ditetapkan sebelum approval (staf untuk REQ klien); satu REQ boleh dipenuhi dari lebih dari satu gudang | Dipakai (BR-REQ-04) | **Perlu validasi** |
| <a id="a-36"></a>A-36 | **Item per potong:** satuan dasar = panjang; saldo tampil sebagai jumlah potongan + total panjang; permintaan dalam potongan nominal atau total panjang; konversi kemasan hanya untuk input; offcut hasil pilah retur mendapat ID + silsilah | Dipakai (BR-STK-09–11) | **Perlu validasi** |
| <a id="a-37"></a>A-37 | **Stok negatif diblokir**; presisi `DECIMAL(18,4)`; kapasitas bin = peringatan (bisa diubah jadi blokir per kategori) | Dipakai (BR-STK-06–07) | **Perlu validasi** |
| <a id="a-38"></a>A-38 | Item **Keduanya**: pilihan Beli/Pinjam di **baris permintaan**, default dari item | Dipakai (BR-REQ-06) | **Perlu validasi** |

**Tema B — Alur dokumen**

| ID | Asumsi | Default bila tidak dijawab | Validasi |
|---|---|---|---|
| <a id="a-32"></a>A-32 | Dokumen baru **Pemakaian Material (`ISU`)**: barang habis pakai di Gudang Site keluar dari stok saat dipakai proyek; kejadian `material_consumed`; kolom *Terpakai* di laporan | Dipakai (KS 2.9, BR-PRJ-08) | **Perlu validasi** |
| <a id="a-33"></a>A-33 | **TRF/RET = dokumen niat**; pergerakan fisik selalu SJ + GRN. Status *Dibatalkan* ditambah ke SJ (sebelum Dikirim), PCK, PUT, PRQ, WST | Dipakai (BR §1, BR-RET-01) | **Perlu validasi** |
| <a id="a-34"></a>A-34 | **GRN:** QC = langkah per baris (Lolos/Karantina/Ditolak); ledger diposting saat *Diterima*; dokumen baru **Retur ke Vendor (`RTV`)** dari Karantina | Dipakai (BR-GRN-01–04) | **Perlu validasi** |
| <a id="a-35"></a>A-35 | **SJ:** kendaraan/ekspedisi wajib; jumlah diterima < dikirim → dokumen **Selisih Pengiriman (`DSC`)** dengan disposisi kembali/disesuaikan/klaim; **short pick** di PCK mengurangi SJ + menandai bin + backorder | Dipakai (BR-SJ-02, BR-SJ-06) | **Perlu validasi** |
| <a id="a-39"></a>A-39 | **Non-katalog:** saat *Ditinjau Staf*, staf wajib memetakan ke item ada atau membuat item *Sementara*; staf boleh mengubah baris klien dengan jejak + notifikasi; REQ punya *tanggal dibutuhkan* | Dipakai (BR-REQ-01–03) | **Perlu validasi** |
| <a id="a-41"></a>A-41 | Bukti terima oleh penerima **tanpa akun**: tautan bertoken sekali pakai + **OTP WA/SMS**, berlaku 24 jam | Dipakai (BR-SJ-05); penyedia OTP di [O-15](#o-15) | **Perlu validasi** |
| <a id="a-43"></a>A-43 | **Penomoran:** `{GUDANG}` = gudang asal (SJ/TRF/PCK/GRN/PUT/ADJ/RTV), gudang pemenuh (REQ/CNV/WST/ISU), `ALL` (OPN multi-gudang, PRQ); urutan dikunci di DB; dokumen offline memakai nomor sementara | Dipakai (BR-GEN-06) | **Perlu validasi** |
| <a id="a-47"></a>A-47 | **Purchasing Fase 1:** role *Penindak Lanjut PR* mencatat PO & kedatangan manual; PRQ punya Ditolak/Dibatalkan & approval opsional; baris GRN merujuk baris PRQ; barang backorder yang tiba otomatis direservasi ke REQ penunggu (urutan tanggal dibutuhkan) | Dipakai (KS 2.15, BR-REQ-08) | **Perlu validasi** |

**Tema C — Proyek, opname, approval, akses**

| ID | Asumsi | Default bila tidak dijawab | Validasi |
|---|---|---|---|
| <a id="a-40"></a>A-40 | **Proyek** punya status Aktif/Ditutup/Dibatalkan/Diarsipkan; penutupan diblokir bila ada dokumen terbuka, aset belum kembali, atau saldo Gudang Site ≠ 0; barang jual-putus milik klien tidak masuk checklist; Gudang Site dinonaktifkan otomatis | Dipakai (BR-PRJ-01–04) | **Perlu validasi** |
| <a id="a-42"></a>A-42 | **Opname:** angka pembanding = fisik; bin beku menolak tugas baru, override SJ mendesak oleh Kepala Gudang; sinkron offline saat beku → antrean tinjauan; toleransi memakai ambang **relatif dan absolut** (default ≤ 1 % dan ≤ 1 unit = kecil; ≤ 5 % = sedang; selebihnya besar) — mengubah [A-16](#a-16) | Dipakai (BR-OPN-01–04) | **Perlu validasi** |
| <a id="a-45"></a>A-45 | **Approval edge case:** SoD (pengaju ≠ approver); orang sama di dua lapis = sekali; delegasi tidak berantai; approver nonaktif → eskalasi; aturan di-snapshot saat diajukan; "jumlah" = satuan dasar per baris atau jumlah baris; 24 jam kalender; keputusan pertama menang; audit WA simpan nomor + `message_id` | Dipakai (BR-APR-01–10) | **Perlu validasi** |
| <a id="a-46"></a>A-46 | **Cakupan akses melekat pada penugasan role × scope** (bukan per user); role Klien tidak digabung role internal; Auditor read-only terhadap mutasi tetapi boleh input hitungan | Dipakai (BR-GEN-09, BR-OPN-08) | **Perlu validasi** |

**Tema D — Platform, SSO, PWA**

| ID | Asumsi | Default bila tidak dijawab | Validasi |
|---|---|---|---|
| <a id="a-44"></a>A-44 | **Ditangguhkan:** antrean PWA ditahan (bukan ditolak); job eskalasi/pengingat berhenti; token WA dijawab "langganan ditangguhkan". **Diakhiri:** hanya Admin Company boleh login untuk ekspor | Dipakai (BR-SUB-02–03) | **Perlu validasi** |
| <a id="a-48"></a>A-48 | **SSO:** bila `sub` terpeta ke user di lebih dari satu company, tampilkan pemilih company; **portal klien** di `abc.wms-domain.id/portal` | Dipakai (BR-SUB-05, BR-PRJ-07) | **Perlu validasi** |
| <a id="a-49"></a>A-49 | Pembeda "approval WA" dan "offline" ditandai **Fase 2** di ringkasan produk; **Driver Fase 1** memakai draf bukti terima tersimpan di perangkat (tanpa sinkron penuh) | Dipakai (Blueprint 1, BR-SJ-08) | **Perlu validasi** |

## 3. Isu terbuka

| ID | Isu | Penanggung jawab | Target |
|---|---|---|---|
| <a id="o-01"></a>O-01 | Pemilihan paket multi-tenancy dan paket lain, verifikasi kompatibilitas Laravel 13 — **selesai 23 Sep 2026**, hasil di [Arsitektur §9](08-arsitektur.md#9-verifikasi-paket-laravel-13-php-83--23-sep-2026) (AD-01, AD-06–AD-09) | Analis | Part 3 ✔ |
| <a id="o-02"></a>O-02 | Pendaftaran WMS sebagai client di NXTG SSO (client_id, satu redirect URI pusat — [A-28](#a-28), role yang diizinkan termasuk `KLIEN`) dan tindak lanjut temuan keamanan SSO (T-12, T-13, T-14, T-17 di [00-audit](../00-audit/README.md)) | Tim SSO | Sebelum Fase 3 |
| <a id="o-03"></a>O-03 | Penyedia WhatsApp (Meta langsung atau BSP), verifikasi bisnis Meta, persetujuan template approval | Pemilik produk | Sebelum Fase 2 |
| <a id="o-04"></a>O-04 | Biaya pesan WhatsApp: termasuk paket atau ditagih terpisah; kuota per company | Pemilik produk | Sebelum Fase 2 |
| <a id="o-05"></a>O-05 | Domain produksi, hosting, SSL wildcard untuk subdomain company | Pemilik produk | Part 3 |
| <a id="o-06"></a>O-06 | Prototipe live: matikan `APP_DEBUG`, hapus 3 data uji | Tim teknis | Segera |
| <a id="o-07"></a>O-07 | Nama produk, logo, identitas brand untuk landing page | Pemilik produk | Part 5 |
| <a id="o-08"></a>O-08 | Harga paket di landing page | Pemilik produk | Part 5 |
| <a id="o-09"></a>O-09 | Ukuran label & jenis printer label yang didukung | Pemilik produk | Part 4 |
| <a id="o-10"></a>O-10 | Jenis reader RFID calon klien | Pemilik produk | Sebelum Fase 2 |
| <a id="o-11"></a>O-11 | Kebijakan privasi & ketentuan layanan (UU PDP) untuk platform dan portal klien | Pemilik produk | Sebelum trial pertama |
| <a id="o-12"></a>O-12 | *(baru)* **Migrasi data prototipe:** apakah data master/stok klien lama dipindahkan ke WMS baru (impor Excel atau skrip), atau mulai dari saldo awal via opname pembukaan | Pemilik produk | Sebelum Part 4 |
| <a id="o-13"></a>O-13 | *(baru)* **Legalitas tanda tangan digital** pada bukti terima & berita acara (UU ITE / PP 71/2019): cukup gambar tanda tangan + jejak audit, atau perlu penyedia tanda tangan tersertifikasi | Pemilik produk | Sebelum Part 4 |
| <a id="o-14"></a>O-14 | *(baru)* **Penyedia email transaksional dan penyimpanan berkas** S3-compatible untuk produksi | Pemilik produk / Tim teknis | Part 3 |
| <a id="o-15"></a>O-15 | *(baru)* **Penyedia OTP** (WA/SMS) untuk bukti terima tanpa akun ([A-41](#a-41)); bila WA belum ada di Fase 1, pakai SMS atau tautan tanpa OTP dengan risiko diterima | Pemilik produk | Sebelum Part 4 |

## 4. Matriks ketertelusuran

Dari keputusan/asumsi ke tempat penerapannya. Kolom BR menunjuk [Aturan Bisnis](05-aturan-bisnis.md); KS = [Katalog Status](06-katalog-status-dan-enum.md).

| Sumber | Blueprint | BR / KS |
|---|---|---|
| D-07, D-08 | §3, §15 | BR-APR-07; akuntansi/01, purchasing/01 |
| D-09, A-02 | §6.3 | BR-STK-02 |
| D-10, D-11, A-06, A-19 | §6.7 | BR-CNV-01–05 |
| D-12, A-38 | §6.4 | BR-REQ-06, BR-STK-08 |
| D-13 | §6.4–6.6 | BR-STK-11–12, BR §15 |
| D-17, D-18, A-08, A-09, A-18, A-45 | §8 | BR-APR-01–11, KS 2.12 |
| D-20, D-21, A-07, A-39 | §7 (REQ) | BR-REQ-01–03, KS 2.1 |
| D-22, D-23, A-16, A-42, A-46 | §9 | BR-OPN-01–08, KS 2.13 |
| D-24, A-49 | §11 | BR-SJ-08, BR-OPN-03 |
| D-26, A-28, A-48 | §13 | BR-SUB-05 |
| D-27, A-11, A-12, A-27, A-44 | §14 | BR-SUB-01–04 |
| A-04, A-46 | §4.2 | BR-GEN-09 |
| A-17, A-43 | §7.1 | BR-GEN-06 |
| A-20, A-41 | §7 (SJ) | BR-SJ-05, KS 2.3 |
| A-21, A-25, A-40 | §6.6, §6.9 | BR-SJ-04, BR-PRJ-01–06, BR-STK-13–14 |
| A-26 | §7 (RET) | BR-RET-03, KS 2.8 |
| A-29, A-30, A-31, A-37 | §6.6 | BR-STK-01–07, BR-REQ-04–05 |
| A-32 | §6.6, §6.9 | BR-PRJ-08, KS 2.9 |
| A-33 | §7.1 | BR §1, BR-RET-01 |
| A-34 | §7 (GRN, RTV) | BR-GRN-01–04, KS 2.5, 2.16 |
| A-35 | §7 (SJ, DSC, PCK) | BR-SJ-02, BR-SJ-06, KS 2.2–2.4 |
| A-36 | §6.5 | BR-STK-09–10 |
| A-47 | §4.2, §7 (PRQ) | BR-REQ-08, KS 2.15 |
