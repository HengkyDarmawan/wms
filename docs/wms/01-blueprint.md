# Blueprint WMS Proyek

**Versi:** 0.7 (pasca-validasi & diskusi lanjutan 23 Sep 2026 — lihat [laporan validasi](../00-laporan-validasi-2026-09-23.md), [laporan audit](../00-laporan-audit-dokumentasi.md), dan catatan perubahan di [README](../README.md))
**Tanggal:** 23 September 2026
**Status:** D-01–D-29; A-01–A-49 dan A-51–A-66 disetujui (A-40 diubah); [A-50](04-keputusan-dan-asumsi.md#a-50) menunggu validasi
**Sumber:** sesi diskusi kebutuhan dengan pemilik produk · audit prototipe ([00-audit](../00-audit/README.md)) · [riset](02-riset-wms-sejenis.md)
**Dokumen terkait:** [Glosarium](03-glosarium.md) · [Keputusan & Asumsi](04-keputusan-dan-asumsi.md) · [Aturan Bisnis](05-aturan-bisnis.md) · [Katalog Status & Enum](06-katalog-status-dan-enum.md)

Tag fase: `[F1]` MVP · `[F2]` · `[F3]` — rinciannya di [§18](#18-peta-modul--fase-rilis). Kebutuhan tanpa tag = `[F1]`.

---

## 1. Ringkasan produk

WMS Proyek adalah aplikasi **SaaS multi-company** untuk perusahaan yang menyediakan dan mengelola material, mesin, dan peralatan bagi **proyek pembangunan**. Setiap company (tenant) berlangganan bulanan, memiliki database sendiri, dan mengatur sendiri gudang, struktur organisasi, alur approval, serta metode stok yang dipakai.

Klien company (pemilik proyek) bisa login ke **portal klien** untuk mengajukan permintaan barang, termasuk barang yang belum ada di katalog, lalu melacak pemenuhan dan pengirimannya sampai diterima di site.

**Pembeda utama dibanding WMS umum:**

1. **Konversi material berbasis ukuran.** Pipa 6 m dipotong menjadi pipa scaffolding 2,5 m; sisa menjadi *offcut* (kembali ke stok) atau *waste*, semuanya tercatat di proyek beserta silsilahnya.
2. **Barang jual-putus dan barang dipinjamkan dalam satu sistem.** Aset seperti mesin punya siklus hidup sendiri (dipinjam, kembali, maintenance, hilang).
3. **Gudang mengikuti proyek.** Hierarki gudang utama → cabang → site proyek, dengan stok on-site terlihat per proyek dan **pemakaian material** tercatat per proyek.
4. **Approval mengikuti struktur organisasi**, berlapis, dan `[F2]` bisa dilakukan lewat WhatsApp.
5. `[F2]` **Tetap jalan saat sinyal lemah** (PWA offline) untuk petugas gudang site dan driver. Di Fase 1, PWA sudah *installable* dengan scan kamera dan draf bukti terima tersimpan di perangkat ([A-49](04-keputusan-dan-asumsi.md#a-49)).

## 2. Tujuan & ukuran keberhasilan

| Tujuan | Ukuran (usulan target) |
|---|---|
| Stok bisa dipercaya | Akurasi stok hasil stock opname ≥ 98% di 6 bulan pertama, ≥ 99% sesudahnya |
| Semua material tertelusur | 100% barang keluar, terpakai, retur, konversi, dan waste terikat ke proyek & dokumen |
| Tidak ada lagi koordinasi manual | 0 permintaan barang lewat chat/kertas; semua lewat sistem atau portal klien |
| Audit tanpa Excel | Laporan rekonsiliasi stock opname langsung dari sistem |
| Waste terukur | Persentase waste per proyek & per jenis material tersedia di dashboard |
| Aset tidak hilang | Aset lewat jatuh tempo pengembalian terdeteksi otomatis dan dilaporkan harian |

## 3. Lingkup

### Termasuk (WMS)

Platform & langganan, manajemen company, user & hak akses, struktur organisasi, master data, gudang & lokasi (rak/bin), stok berbasis kartu stok, penerimaan & put-away, permintaan material (internal & portal klien), picking & pengiriman, pemakaian material di site, retur (dari proyek dan ke vendor), transfer, konversi material, aset dipinjamkan, stock opname & audit, approval engine, notifikasi (termasuk WhatsApp), template dokumen, laporan & dashboard, PWA offline, landing page produk.

### Tidak termasuk (modul terpisah)

| Hal | Pindah ke |
|---|---|
| Harga jual, harga modal, nilai persediaan, pembayaran (DP/lunas), tagihan sewa, jurnal | Modul **Akuntansi** — [lingkup & integrasi](../akuntansi/01-lingkup-dan-integrasi-wms.md) |
| Purchase Order, pemilihan vendor, harga beli | Modul **Purchasing** — [lingkup & integrasi](../purchasing/01-lingkup-dan-integrasi-wms.md) |
| Penjadwalan proyek, RAB, SDM | Di luar produk |
| Transfer stok antar company dalam satu grup | Di luar produk (database terpisah, [D-03](04-keputusan-dan-asumsi.md#d-03)); bila dibutuhkan, keputusan baru |
| Biaya ongkir / nilai pengiriman | Modul **Akuntansi**; WMS hanya meneruskan nama ekspedisi & resi ([A-57](04-keputusan-dan-asumsi.md#a-57)) |

WMS hanya menyimpan **kuantitas**. Nilai uang dikelola modul Akuntansi ([D-07](04-keputusan-dan-asumsi.md#d-07)).

## 4. Pengguna & peran

### 4.1 Level platform (database pusat)

| Peran | Tugas |
|---|---|
| **Super Admin** | Membuat company, mengatur paket & trial, memverifikasi pembayaran langganan, memantau seluruh tenant, menangguhkan company. Tidak bisa membuka data operasional company tanpa akses dukungan ([A-27](04-keputusan-dan-asumsi.md#a-27)) |

### 4.2 Level company (database tenant)

Role berikut adalah **template bawaan**. Admin Company bisa mengubah hak aksesnya atau membuat role baru dari daftar permission (`<modul>.<aksi>`, lihat [Katalog Status](06-katalog-status-dan-enum.md)).

| Role | Tugas utama | Kanal |
|---|---|---|
| **Admin Company** | Setup awal (wizard), user, role, struktur organisasi, aturan approval, pengaturan fitur stok, template dokumen | Web |
| **Manajemen** | Dashboard & laporan seluruh gudang, approver tingkat atas | Web, WhatsApp `[F2]` |
| **Kepala Gudang** | Menyetujui permintaan/transfer/penyesuaian, mengawasi gudang yang ditugaskan, menyelesaikan selisih pengiriman | Web, PWA, WhatsApp `[F2]` |
| **Staf Gudang** | Penerimaan, QC, put-away, picking, konversi, pemakaian material di site, hitung stok | PWA (utama), Web |
| **Driver** | Menerima tugas kirim, surat jalan digital, bukti terima (foto + tanda tangan) | PWA |
| **Pemohon Internal** | Engineer / PIC proyek dari company: mengajukan permintaan, konfirmasi terima, mengajukan retur | Web, PWA |
| **Klien** | User dari pemilik proyek: mengajukan permintaan (termasuk non-katalog), menambah baris, menanggapi pengganti, mengajukan pembatalan ([A-54](04-keputusan-dan-asumsi.md#a-54), [A-55](04-keputusan-dan-asumsi.md#a-55), [A-61](04-keputusan-dan-asumsi.md#a-61)), melacak status & tanggal janji, **mengonfirmasi atau mengajukan keberatan terima** (kurang/rusak, [A-63](04-keputusan-dan-asumsi.md#a-63)), mengajukan retur | Portal klien (web) |
| **Penindak Lanjut PR** | Mencatat **catatan pemesanan** per vendor/toko online di Fase 1 (nomor PO/pesanan, resi, perkiraan datang), membuat vendor sementara; di `[F3]` digantikan modul Purchasing ([A-47](04-keputusan-dan-asumsi.md#a-47), [A-51](04-keputusan-dan-asumsi.md#a-51), [A-53](04-keputusan-dan-asumsi.md#a-53)) | Web |
| **Auditor Internal** | Stock opname & rekonsiliasi; read-only terhadap mutasi stok, boleh menginput hitungan | Web, PWA |
| **Auditor Eksternal** `[F2]` | Sama dengan auditor internal, akun tamu berbatas waktu & cakupan | Web, PWA |

**Cakupan akses data:** cakupan **gudang** dan/atau **proyek** melekat pada **penugasan role**, bukan pada user ([A-46](04-keputusan-dan-asumsi.md#a-46), [BR-GEN-09](05-aturan-bisnis.md#br-gen)). Contoh: user X = Kepala Gudang untuk Gudang Cakung dan Staf Gudang untuk Gudang Bekasi. Role Klien tidak bisa digabung dengan role internal.

## 5. Prinsip desain

| ID | Prinsip | Alasan (temuan audit prototipe) |
|---|---|---|
| P-01 | **Stok hanya berubah lewat kartu stok (ledger)** yang tidak bisa diedit/dihapus. Saldo dihitung dari ledger. | BUG-20 |
| P-02 | **Setiap baris permintaan yang disetujui harus punya sumber pemenuhan**: stok tersedia (dicadangkan), transfer dari gudang lain, atau pembelian. Stok tersedia = saldo − reservasi. *(ditulis ulang di v0.3, [A-30](04-keputusan-dan-asumsi.md#a-30))* | BUG-14, BUG-15 |
| P-03 | **Tidak ada hapus untuk data yang sudah dipakai.** Master dinonaktifkan; transaksi dibatalkan lewat dokumen pembalik. Batasan per dokumen: [BR-GEN-04](05-aturan-bisnis.md#br-gen). | BUG-07, BUG-11, BUG-18 |
| P-04 | **Satu mesin status untuk semua dokumen**, didefinisikan di [Katalog Status](06-katalog-status-dan-enum.md). Transisi hanya lewat aksi POST dengan konfirmasi dan alasan bila menolak/membatalkan. | SEC-04, UX-28, UX-29 |
| P-05 | **Approval ≠ tugas.** Approval adalah keputusan (berlapis, per company). Tugas (picking, muat, kirim, terima) cukup dikonfirmasi pelaksananya dan tercatat. | 5 approval di diagram "Sistem Outbond" |
| P-06 | **Lokasi adalah entitas**, bukan teks. Barang hanya boleh diambil dari bin yang memang menyimpannya. | BUG-16 |
| P-07 | **Semua perubahan tercatat** lewat dua catatan: timeline dokumen (untuk user) dan audit log (teknis) — [BR-GEN-05](05-aturan-bisnis.md#br-gen). | UX-05 |
| P-08 | **Fitur diaktifkan dua lapis:** company menyalakan fitur (mis. serial number), item menentukan apakah fitur itu berlaku untuknya. | Kebutuhan "semua metode, tinggal pilih" |
| P-09 | **Konfigurasi, bukan kode.** Tipe gudang, alur approval, format nomor dokumen, template cetak, kanal notifikasi diatur lewat UI. | Kebutuhan SaaS multi-company |
| P-10 | **Offline-first untuk pekerjaan lapangan** `[F2]`; server tetap menjadi acuan akhir. | Sinyal lemah di site |
| P-11 | **Konsisten:** satu bahasa, satu format tanggal, satu pola tabel/form, istilah sesuai [glosarium](03-glosarium.md), status sesuai katalog. | BUG-02, BUG-22, UX-13 |
| P-12 | **Aman sejak awal:** debug mati di produksi, CSRF, rate limit, audit login, halaman error rapi. | SEC-01, SEC-02 |

## 6. Konsep inti domain

### 6.1 Tenancy

- **Database pusat** menyimpan: company, domain, paket & langganan, tagihan langganan, pembayaran manual, user platform, pemetaan login SSO, feature flag per company.
- **Database per company** menyimpan seluruh data operasional company.
- **Identifikasi tenant lewat subdomain**, mis. `abc.wms-domain.id` ([A-01](04-keputusan-dan-asumsi.md#a-01)); portal klien di `abc.wms-domain.id/portal` ([A-48](04-keputusan-dan-asumsi.md#a-48)).
- **Provisioning otomatis** saat Super Admin membuat company: buat database → migrasi → data awal (role bawaan, satuan standar, tipe gudang & bin standar, alasan standar, template dokumen standar) → undangan ke Admin Company.
- Migrasi skema dijalankan ke semua tenant berurutan dengan log hasil per tenant.

### 6.2 Struktur organisasi & gudang

- **Struktur organisasi:** unit (divisi/departemen), jabatan, relasi atasan. Dipakai approval engine ("atasan langsung pemohon", "jabatan X di divisi Y").
- **Tipe gudang** adalah master yang bisa ditambah company. Bawaan: *Gudang Utama*, *Gudang Cabang*, *Gudang Site*.
- **Hierarki gudang** berbentuk pohon. Gudang Site selalu terikat satu **Proyek**; satu proyek boleh punya **beberapa Gudang Site** (titik/segmen lokasi, mis. galian pipa — [A-40](04-keputusan-dan-asumsi.md#a-40)), dan setiap Gudang Site dinonaktifkan otomatis saat proyek ditutup dengan saldo nol ([BR-PRJ-04](05-aturan-bisnis.md#br-prj)).
- Setiap gudang punya penanggung jawab (Kepala Gudang) dan daftar penugasan role yang boleh mengaksesnya.

### 6.3 Lokasi (rak & bin) — wajib

```
Gudang
 └─ Zona          (mis. A = material besi, B = kelistrikan)
     └─ Rak       (mis. R03)
         └─ Level (mis. L2)
             └─ Bin      ← satuan lokasi terkecil tempat stok disimpan
```

- Kode lokasi otomatis dari hierarki, mis. `CKG-A-R03-L2-B05`, bisa dicetak sebagai label barcode/QR.
- **Jenis bin** (`bin_type`): Penyimpanan, Penerimaan, Loading Area, Karantina QC, Retur, Waste, serta dua **bin virtual** per gudang: *Dalam Perjalanan* (milik gudang asal) dan *On-site Proyek* (satu per proyek, hanya aset). Bin virtual adalah **lokasi**, bukan status ([A-29](04-keputusan-dan-asumsi.md#a-29)).
- Atribut bin: kapasitas opsional (jumlah/berat/volume/panjang; peringatan atau blokir — [BR-STK-07](05-aturan-bisnis.md#br-stk)), kategori penyimpanan, status (aktif, dibeku saat opname, nonaktif).
- Gudang site sederhana boleh memakai **satu zona & satu bin default** ([A-02](04-keputusan-dan-asumsi.md#a-02)).

### 6.3a Master data lain

| Master | Isi pokok | Catatan |
|---|---|---|
| **Klien** | Nama perusahaan, NPWP, alamat, kontak, user portal | Satu klien bisa punya banyak proyek |
| **Vendor** | Nama, **jenis** (perusahaan / toko / toko online / perorangan), NPWP, contact person, telepon, email, alamat, termin; **vendor tetap per item** (prioritas, tanpa harga) | Dikelola WMS sampai modul Purchasing tersedia (UX-17); vendor sementara boleh dibuat saat memesan ([A-52](04-keputusan-dan-asumsi.md#a-52), [A-53](04-keputusan-dan-asumsi.md#a-53)) |
| **Kategori barang** | Nama, induk, kategori penyimpanan bawaan, strategi pengambilan bawaan, ambang toleransi opname | Tidak bisa dihapus bila dipakai (UX-14) |
| **Kendaraan & driver** | Nomor polisi, jenis, driver bawaan; ekspedisi pihak ketiga | Dipakai di pengiriman |
| **Alasan** | Daftar alasan untuk tolak, batal, penyesuaian, waste, kerusakan, short pick, selisih kirim | Agar laporan akar masalah bisa dikelompokkan. Form tolak/batal: Alasan wajib `*`, keterangan bebas opsional; semua field wajib ditandai `*` ([BR-GEN-11](05-aturan-bisnis.md#br-gen)) |

Semua master bisa **diimpor dari Excel** dengan template, validasi baris, dan pratinjau sebelum disimpan.

### 6.4 Item (barang)

| Atribut | Keterangan |
|---|---|
| Kode, nama, kategori, spesifikasi, foto | Kode unik per company; status `active` / `provisional` (dibuat dari baris non-katalog, [A-39](04-keputusan-dan-asumsi.md#a-39)) / `inactive` |
| **Model kepemilikan** | *Habis pakai / jual putus*, *Aset dipinjamkan*, atau *Keduanya*. Untuk *Keduanya*, pilihan Beli/Pinjam ditentukan **per baris permintaan** dengan default dari item ([A-38](04-keputusan-dan-asumsi.md#a-38)) |
| **Mode pelacakan** | *Tanpa*, *Batch/Lot*, *Serial number*, atau *Per potong (ukuran)*. Aset wajib serial. Kombinasi sah: [BR-STK-11](05-aturan-bisnis.md#br-stk) |
| Tanggal kedaluwarsa | Ya/tidak (hanya bila fitur aktif di company; hanya lot/serial) |
| Satuan dasar & konversi | Lihat 6.5 |
| Bisa dipotong/dikonversi | Ya/tidak, panjang minimum offcut (wajib bila ya, [A-19](04-keputusan-dan-asumsi.md#a-19)), rugi potong (kerf) opsional |
| Wajib QC saat terima | Ya/tidak |
| Stok minimum / titik pesan ulang | Membuat draf Purchase Request harian yang ditinjau Kepala Gudang ([BR-REQ-11](05-aturan-bisnis.md#br-req)) |
| Identitas fisik | Barcode, QR, dan/atau tag RFID `[F2]` (lihat 6.10) |
| Dimensi & berat | Dengan satuan yang bisa dipilih |

### 6.5 Satuan dinamis (UoM)

- **Kategori satuan:** jumlah (pcs, set), panjang (mm, cm, m), berat (g, kg, ton), volume (ml, L, m³), luas (m²). Setiap kategori punya satuan acuan; konversi dalam kategori berlaku global.
- **Konversi khusus per item** untuk kemasan: 1 batang = 6 m (pipa X), 1 box = 12 pcs, 1 zak = 40 kg.
- Semua stok disimpan dalam **satuan dasar item** (`DECIMAL(18,4)`); transaksi boleh diinput dalam satuan lain dan dikonversi otomatis dengan pembulatan per satuan.
- **Item per potong:** satuan dasar = panjang; saldo ditampilkan sebagai *jumlah potongan + total panjang*; konversi kemasan hanya untuk input dan hanya pada potongan berukuran nominal ([A-36](04-keputusan-dan-asumsi.md#a-36), [BR-STK-09](05-aturan-bisnis.md#br-stk)).

### 6.6 Stok

Model tunggal ([A-29](04-keputusan-dan-asumsi.md#a-29), [A-30](04-keputusan-dan-asumsi.md#a-30)):

| Konsep | Bentuk | Nilai |
|---|---|---|
| **Kartu stok** (`stock_movement`) | Baris ledger append-only: item, bin asal/tujuan, lot/serial/potongan, jumlah, dokumen sumber, proyek, user, waktu | — |
| **Lokasi** | Bin, termasuk bin virtual | — |
| **Kondisi** (`stock_status`) | Kolom pada saldo | Tersedia · Karantina · Rusak |
| **Reservasi** (`stock_reservation`) | Tabel terpisah, bukan status | Lunak (item × gudang, saat approval) → keras (bin/lot/serial/potongan, saat picking) |
| **Stok tersedia** | Turunan | Saldo *Tersedia* − reservasi aktif |

- **Strategi pengambilan** (per company, bisa ditimpa per kategori/item): FIFO, FEFO, Manual, **Sisa potongan dulu**. Sistem menyarankan bin & lot saat picking; user boleh mengganti dengan alasan.
- **Backorder:** bila stok tersedia kurang saat approval, yang ada dicadangkan dan sisanya wajib diberi sumber: transfer dari gudang lain (TRF) atau Purchase Request (PRQ). Tanpa sumber, approval tertahan ([BR-REQ-05](05-aturan-bisnis.md#br-req)). Satu baris boleh **dipecah** ke beberapa gudang sumber yang masing-masing mengirim langsung ke tujuan ([A-56](04-keputusan-dan-asumsi.md#a-56)).
- **Stok negatif diblokir**; penguncian baris saldo dalam satu transaksi (NFR-13).
- **Tutup periode stok:** mutasi berkejadian pada/sebelum tanggal kunci company ditolak, koreksi di periode berjalan ([A-58](04-keputusan-dan-asumsi.md#a-58)); **reservasi menggantung** dilaporkan dan diperingatkan setelah 7 hari ([A-59](04-keputusan-dan-asumsi.md#a-59)).
- **Efek pengiriman terhadap stok company** ([A-25](04-keputusan-dan-asumsi.md#a-25), [BR-SJ-04](05-aturan-bisnis.md#br-sj)):

| Tujuan & kepemilikan | Efek di stok |
|---|---|
| Ke **Gudang Site** / gudang lain (barang apa pun) | Transfer — tetap stok company; *Dalam Perjalanan* milik gudang asal sampai GRN tujuan |
| Ke klien, barang **jual putus** | Keluar dari stok saat bukti terima **untuk jumlah baik**; tercatat di riwayat proyek sebagai *Terkirim ke Klien*. Rusak & kurang tetap *Dalam Perjalanan* (rusak berkondisi Rusak) sampai DSC selesai; rusak dibawa balik driver, tidak pernah menjadi stok klien/site ([A-65](04-keputusan-dan-asumsi.md#a-65), [BR-SJ-10](05-aturan-bisnis.md#br-sj)) |
| Ke proyek, **aset dipinjamkan** | Tetap stok company, pindah ke bin virtual *On-site Proyek* sampai dikembalikan |

- **Pemakaian material** ([A-32](04-keputusan-dan-asumsi.md#a-32)): barang habis pakai di Gudang Site keluar dari stok lewat dokumen `ISU` saat dipakai proyek. Tanpa ini, beban proyek tidak pernah tercatat.

### 6.7 Konversi material, offcut & waste

- **Item per potong:** setiap batang/lembar fisik adalah satu *potongan* dengan ID dan ukuran (mis. `PIPA-2IN #P-000123`, 6,00 m).
- **Dokumen Konversi** mencatat input (potongan/lot), output (bisa item berbeda), sisa (≥ minimum → **offcut** kembali ke stok; < minimum → **waste**), dan kerf opsional. Neraca ukuran wajib seimbang ([BR-CNV-02](05-aturan-bisnis.md#br-cnv)).
- **Resep konversi** `[F2]` untuk konversi berulang.
- **Silsilah** dua arah untuk setiap output dan offcut, termasuk offcut hasil pemilahan retur.
- **Wajib terikat proyek** ([D-10](04-keputusan-dan-asumsi.md#d-10)); konversi persiapan stok memakai Proyek Internal ([A-06](04-keputusan-dan-asumsi.md#a-06)).
- **Waste** disimpan di bin Waste dengan alasan, ditutup lewat **Berita Acara Waste** (dibuang, dijual scrap, dipakai ulang).
- Pola yang sama untuk rakit, bongkar, dan ganti kemasan.

### 6.8 Aset dipinjamkan

- Aset wajib **serial number / kode aset**.
- **Siklus hidup** (`asset_state`): Tersedia → Dicadangkan → Dalam Perjalanan → Dipinjam → Dikembalikan → Pemeriksaan → Tersedia / Maintenance / Rusak; Hilang → Dihapuskan. Pemetaan state → bin → kondisi: [BR-AST-01](05-aturan-bisnis.md#br-ast).
- **Serah terima** mencatat peminjam (proyek; peminjaman ke orang memakai Proyek Internal), tanggal kembali, kondisi & foto.
- **Pengembalian** wajib pemeriksaan (grade A–D + **skor kondisi 0–100 %** + foto + catatan komponen → riwayat kondisi aset). Kerusakan/kehilangan diteruskan ke Akuntansi; kehilangan dihapuskan lewat ADJ.
- **Peringatan otomatis** aset lewat jatuh tempo; daftar aset belum kembali saat proyek ditutup.
- **Maintenance** `[F2]`: jadwal servis, riwayat, status tidak bisa dipinjam.
- WMS menyediakan **hari pakai** dan **meter pemakaian** (jam/km dibaca saat keluar & kembali) per aset per proyek, serta **umur harapan & sisa umur %** dengan peringatan ([BR-AST-05](05-aturan-bisnis.md#br-ast), [A-66](04-keputusan-dan-asumsi.md#a-66)); tagihan sewa dan penyusutan di Akuntansi.

### 6.9 Proyek

- Master proyek: kode, nama, klien, alamat & titik peta, tanggal mulai/target selesai, PIC, **Gudang Site (boleh lebih dari satu: titik/segmen lokasi)**, **status** (Aktif / Ditutup / Dibatalkan / Diarsipkan — [A-40](04-keputusan-dan-asumsi.md#a-40)).
- **Tab detail proyek:** Ringkasan · Permintaan · Pengiriman · **Stok On-site** (tiga sub-tampilan: *Di Gudang Site*, *Aset di Proyek*, *Terkirim ke Klien*) · Pemakaian · Konversi · Retur · Waste · Dokumen · Riwayat.
- **Penutupan proyek** memakai checklist dengan guard ([BR-PRJ-02](05-aturan-bisnis.md#br-prj)): tidak ada dokumen terbuka, aset sudah kembali, saldo **semua** Gudang Site nol (pilihan per titik: retur ke gudang, transfer ke titik lain / proyek lain, pemakaian akhir, waste). Barang jual-putus yang sudah diterima klien tidak masuk checklist.
- Proyek tidak pernah dihapus, hanya dibatalkan/diarsipkan.
- **Klien** melihat ketiga sub-tampilan Stok On-site untuk proyeknya, tidak melihat gudang company lain ([A-21](04-keputusan-dan-asumsi.md#a-21) diperjelas).

### 6.9a Laporan inti (Fase 1)

Semua laporan punya filter periode bebas dan **ekspor Excel & PDF** (UX-11, UX-12).

| Laporan | Isi |
|---|---|
| Kartu stok per item | Saldo awal, setiap mutasi, saldo akhir |
| Saldo stok | Per gudang, zona, bin, lot/serial/potongan, kondisi; potongan: jumlah + total panjang |
| Mutasi periode | Masuk, keluar, transfer, konversi, pemakaian, penyesuaian dengan saldo awal & akhir |
| Material per proyek | Diminta vs terkirim vs **terpakai** vs diretur vs on-site vs waste; `[F2]` vs **rencana** (BoQ kuantitas, [A-62](04-keputusan-dan-asumsi.md#a-62)) |
| Konversi & waste | Per proyek, per item, persentase waste |
| Aset | Posisi aset, hari & jam pakai per proyek, sisa umur %, skor kondisi & riwayatnya, lewat jatuh tempo, riwayat maintenance |
| Permintaan terbuka & posisi barang rusak | Backorder, menunggu approval, lewat tanggal dibutuhkan, DSC terbuka > N hari; rusak dalam perjalanan / di bin Retur / diklaim ([BR-SJ-10](05-aturan-bisnis.md#br-sj)) |
| Akurasi stok | Hasil opname per gudang/zona dan trennya |

### 6.10 Identifikasi & perangkat

- **Label:** barcode 1D (Code 128) dan QR untuk item, lot, potongan, aset, bin, dokumen. Printer label thermal atau kertas A4.
- **Scan:** kamera HP/tablet di PWA, scanner USB/Bluetooth, handheld Android.
- **RFID** `[F2]`: reader mode *keyboard wedge* atau API untuk reader tetap. Tag dipetakan ke item/aset/bin.
- Metode dipilih di pengaturan company; semuanya bisa aktif bersamaan.

## 7. Dokumen & alur utama

Status lengkap, aksi, guard, dan efek ada di [Katalog Status & Enum](06-katalog-status-dan-enum.md). Di sini hanya ringkasan.

| Kode | Dokumen | Status inti | Catatan |
|---|---|---|---|
| `REQ` | **Permintaan Material** | Draf → Diajukan → (Ditinjau Staf, khusus klien) → Menunggu Approval → Disetujui → Diproses → Sebagian Terpenuhi → Selesai / Ditutup dengan Sisa · Ditolak / Dibatalkan | Satu proyek per REQ; baris katalog atau non-katalog; `required_date`; Disetujui = reservasi lunak + TRF/PRQ untuk kekurangan; tanggal janji per baris; klien boleh menambah baris (REQ Tambahan setelah disetujui), menolak pengganti dalam 1 hari, mengajukan pembatalan ([A-54](04-keputusan-dan-asumsi.md#a-54), [A-55](04-keputusan-dan-asumsi.md#a-55), [A-60](04-keputusan-dan-asumsi.md#a-60), [A-61](04-keputusan-dan-asumsi.md#a-61)) |
| `PCK` | Tugas Picking | Menunggu → Dikerjakan → Selesai · Dibatalkan | Alokasi keras; short pick dengan alasan |
| `SJ` | **Pengiriman / Surat Jalan** | Disiapkan → Dikirim → Diterima / Diterima Sebagian · Dibatalkan (sebelum Dikirim) | Cara kirim: kendaraan sendiri / ekspedisi / diantar sendiri ([A-57](04-keputusan-dan-asumsi.md#a-57)); satu SJ boleh memuat beberapa REQ ke tujuan yang sama ([BR-SJ-09](05-aturan-bisnis.md#br-sj)); bukti terima per baris baik/rusak/kurang (per unit untuk serial/potongan, foto bila rusak) oleh driver atau penerima bertoken ([A-41](04-keputusan-dan-asumsi.md#a-41), [A-64](04-keputusan-dan-asumsi.md#a-64)); klien konfirmasi/keberatan 3 hari ([A-63](04-keputusan-dan-asumsi.md#a-63)) |
| `DSC` | Selisih Pengiriman *(baru)* | Terbuka → Diselesaikan | Otomatis dari Diterima Sebagian atau keberatan klien; baris kurang/rusak; disposisi: kembali ke gudang / disesuaikan / klaim / **kirim pengganti**; klien memilih masih perlu atau tidak ([A-35](04-keputusan-dan-asumsi.md#a-35), [A-64](04-keputusan-dan-asumsi.md#a-64)) |
| `GRN` | **Penerimaan Barang** | Draf → Diterima → Selesai · Dibatalkan (sebelum Diterima) | QC = langkah per baris (Lolos / Karantina / Ditolak); cross-dock ke Loading Area |
| `PUT` | Tugas Put-away | Menunggu → Selesai · Dibatalkan | Saran bin |
| `RTV` | Retur ke Vendor *(baru)* | Diajukan → Disetujui → Dikirim → Selesai · Ditolak / Dibatalkan | Dari Karantina hasil QC ([A-34](04-keputusan-dan-asumsi.md#a-34)) |
| `TRF` | **Transfer** | Diajukan → Disetujui → Diproses → Selesai · Ditolak / Dibatalkan | Dokumen niat; fisik lewat SJ + GRN. Antar gudang, **antar proyek**, dan **antar titik dalam proyek** dengan jalur ringan ([A-50](04-keputusan-dan-asumsi.md#a-50)) |
| `RET` | **Retur dari Proyek** | Diajukan → Disetujui → Diproses → Diterima → Dipilah · Ditolak / Dibatalkan | Pemilahan: layak, rusak, offcut, waste; jual-putus boleh diretur ([A-26](04-keputusan-dan-asumsi.md#a-26)) |
| `ISU` | Pemakaian Material *(baru)* | Draf → Dikonfirmasi · Dibatalkan | Gudang Site → proyek, hanya habis pakai ([A-32](04-keputusan-dan-asumsi.md#a-32)) |
| `CNV` | **Konversi Material** | Draf → (Approval opsional) → Selesai · Dibatalkan | Wajib proyek |
| `AST` | Serah Terima Aset | Dipinjam → Dikembalikan → Diperiksa | Menempel pada SJ dan GRN retur |
| `ADJ` | Penyesuaian Stok | Diajukan → Menunggu Approval → Disetujui → Diposting · Ditolak / Dibatalkan | Manual selalu approval; dari OPN disetujui di sesi |
| `OPN` | **Sesi Stock Opname** | Direncanakan → Berjalan → Hitung Ulang → Rekonsiliasi → Disetujui → Ditutup · Dibatalkan | Lihat §9 |
| `WST` | Berita Acara Waste | Diajukan → Disetujui → Ditutup · Ditolak / Dibatalkan | Disposisi waste |
| `PRQ` | Purchase Request | Draf (titik pesan ulang) → Diajukan → (Approval opsional) → Disetujui → Diteruskan → Sebagian / Dipenuhi · Ditolak / Dibatalkan | Fase 1 manual: **catatan pemesanan** per vendor/toko online ([A-51](04-keputusan-dan-asumsi.md#a-51)); [Purchasing](../purchasing/01-lingkup-dan-integrasi-wms.md) |

### 7.1 Relasi antar dokumen

Dokumen **niat** (REQ, TRF, RET) dipisah dari dokumen **pergerakan fisik** (PCK, SJ, GRN, PUT). Pergerakan antar lokasi selalu lewat SJ (keluar) dan GRN (masuk) ([A-33](04-keputusan-dan-asumsi.md#a-33)). Diagram dan kardinalitas: [Aturan Bisnis §1](05-aturan-bisnis.md#1-relasi-antar-dokumen).

**Aturan umum semua dokumen** ([BR-GEN](05-aturan-bisnis.md#br-gen)):
- Nomor otomatis dengan format per company, mis. `REQ/CKG/2026/09/0001`; segmen gudang mengikuti [A-43](04-keputusan-dan-asumsi.md#a-43).
- Setiap dokumen punya **timeline** (status, pelaku, waktu, kanal, catatan).
- Pembatalan setelah stok bergerak = dokumen pembalik, bukan hapus.
- "Resend" prototipe digantikan Permintaan baru dengan visibilitas stok lintas gudang.

## 8. Approval engine

### 8.1 Konsep

Setiap company membuat **aturan approval per jenis dokumen** yang terdiri dari beberapa **lapis**:

| Pengaturan per lapis | Pilihan |
|---|---|
| Approver | User tertentu · Jabatan · Role · Atasan langsung pemohon · Kepala gudang terkait · PIC proyek |
| Cara putus | Berurutan · Cukup salah satu · Semua harus setuju |
| Kondisi berlaku | Gudang · proyek · kategori barang · model kepemilikan · jumlah (satuan dasar per baris / jumlah baris) di atas batas · permintaan dari klien · jenis vendor & asal PRQ · `[F2]` melebihi rencana proyek |
| Batas waktu | Eskalasi ke approver cadangan atau atasan bila lewat X jam (default 24 jam kalender) |
| Kanal | Web · WhatsApp `[F2]` · keduanya |

Fitur pendukung: **delegasi** berperiode (tidak berantai), **simulasi aturan** sebelum disimpan, **riwayat** lengkap. Aturan di-*snapshot* saat dokumen diajukan. Edge case (SoD, approver ganda, approver nonaktif, keputusan bersamaan): [BR-APR](05-aturan-bisnis.md#br-apr).

Tanpa aturan, dokumen langsung disetujui ([A-08](04-keputusan-dan-asumsi.md#a-08)), **kecuali Penyesuaian Stok manual** ([A-09](04-keputusan-dan-asumsi.md#a-09)). Approval berdasarkan **nilai uang** ada di modul Purchasing dengan mesin approval yang sama ([D-28](04-keputusan-dan-asumsi.md#d-28)); template aturan bawaan: "PRQ manual → Kepala Gudang → Manajemen bila melebihi batas jumlah/kategori", "toko online → satu lapis tambahan".

### 8.2 Approval via WhatsApp `[F2]`

- **WhatsApp Business Platform (Cloud API)** dengan template bertombol **Setujui / Tolak / Lihat Detail**.
- Keamanan: nomor pengirim harus cocok dengan approver; **token sekali pakai** kedaluwarsa; menolak wajib alasan; dokumen berisiko tinggi bisa diwajibkan PIN atau web saja.
- Keputusan via WA tercatat di timeline dengan kanal "WhatsApp", nomor pengirim, dan `wa_message_id` ([BR-APR-10](05-aturan-bisnis.md#br-apr)).
- **Biaya:** mulai 1 Oktober 2026 template utility selalu ditagih; balasan dalam jendela 24 jam ditagih setelah 1.000 pesan gratis/bulan per nomor. Notifikasi WA diatur per kejadian, bisa digabung (ringkasan harian), balasan konfirmasi singkat & bisa dimatikan, pemakaian per company dicatat ([O-04](04-keputusan-dan-asumsi.md#o-04)). Rincian: [Riset §2.6](02-riset-wms-sejenis.md#26-whatsapp-untuk-notifikasi--approval).

## 9. Stock opname & audit

- **Jenis sesi:** bulanan, tahunan, ad-hoc, **pemeriksaan mendadak** (cakupan kecil tanpa pembekuan — [BR-OPN-10](05-aturan-bisnis.md#br-opn)), dan `[F2]` *cycle count* ABC.
- **Cakupan:** per gudang, zona, atau daftar bin/item. Semua sesi terkonsolidasi di satu dashboard.
- **Angka pembanding = saldo fisik** per bin (termasuk dicadangkan & Loading Area) — [BR-OPN-01](05-aturan-bisnis.md#br-opn).
- **Pembekuan lokasi** per sesi: bin beku menolak tugas baru; override SJ mendesak oleh Kepala Gudang dengan alasan ([BR-OPN-02](05-aturan-bisnis.md#br-opn)).
- **Hitung buta** dan **toleransi berjenjang** dengan ambang **relatif dan absolut** (default ≤ 1 % *dan* ≤ 1 unit = kecil; ≤ 5 % = sedang → hitung ulang oleh orang berbeda; selebihnya besar → approval + akar masalah) — [A-42](04-keputusan-dan-asumsi.md#a-42).
- **Rekonsiliasi** menghasilkan satu ADJ per gudang, **disetujui di tingkat sesi** ([A-09](04-keputusan-dan-asumsi.md#a-09)).
- **Auditor** internal / `[F2]` eksternal (akun tamu berbatas gudang & periode, nonaktif otomatis). **Pemisahan tugas:** penghitung tidak menyetujui sesinya; sesi tahunan/audit disetujui Auditor Internal atau Manajemen ([BR-OPN-09](05-aturan-bisnis.md#br-opn)).
- **Riwayat audit** lengkap per sesi dan **dashboard opname** (progres, akurasi, tren, top selisih, akar masalah).

## 10. Notifikasi

| Kanal | Dipakai untuk |
|---|---|
| In-app (lonceng) | Semua kejadian, termasuk pengingat SLA tinjau permintaan klien dan reservasi menggantung ([A-59](04-keputusan-dan-asumsi.md#a-59), [A-60](04-keputusan-dan-asumsi.md#a-60)) |
| Email | Ringkasan, undangan user, tagihan langganan |
| WhatsApp `[F2]` | Approval, dokumen butuh tindakan, aset lewat jatuh tempo, pengingat opname, OTP bukti terima |

User mengatur preferensi kanal; Admin Company menentukan kejadian mana yang boleh memakai WhatsApp.

## 11. PWA & mode offline

- **Fase 1:** PWA installable, scan kamera, semua aset front-end di-*bundle* lokal; **draf bukti terima & hitungan opname tersimpan di perangkat** dan dikirim saat online ([A-49](04-keputusan-dan-asumsi.md#a-49)).
- **Fase 2 — offline penuh:** penerimaan, put-away, picking, konfirmasi muat, bukti terima, hitung opname, foto. Data lokal: master item & bin gudang yang ditugaskan, tugas yang dibagikan, antrean transaksi.
- **Sinkronisasi:** otomatis saat koneksi kembali dan saat aplikasi dibuka; tidak bergantung pada *background sync* (dukungan iOS tidak merata).
- **Konflik:** server acuan akhir. Transaksi offline yang mengurangi stok divalidasi ulang; bila bentrok atau bin sedang dibeku, masuk **antrean tinjauan**. Dokumen offline memakai nomor sementara ([A-43](04-keputusan-dan-asumsi.md#a-43)). Bila langganan ditangguhkan, antrean **ditahan** ([A-44](04-keputusan-dan-asumsi.md#a-44)).
- **Sesi:** perangkat terdaftar per user; token diperbarui otomatis; antrean tetap tersimpan bila sesi kedaluwarsa.

## 12. Template dokumen

- **Layout induk per company:** logo, kop, warna, footer, blok tanda tangan, QR verifikasi.
- **Template per jenis dokumen** dengan variabel `{nomor_dokumen}`, `{nama_proyek}`, `{tanggal}`, `{daftar_barang}`.
- Dokumen bawaan: Surat Jalan, Bukti Terima, Picklist, Label, Berita Acara Serah Terima Aset, Berita Acara Waste, Laporan Stock Opname, Berita Acara Penyesuaian, Berita Acara Selisih Pengiriman, Surat Retur ke Vendor.
- Tanda tangan digital dari profil user atau dibubuhkan di perangkat. Legalitas tanda tangan: [O-13](04-keputusan-dan-asumsi.md#o-13).
- Editor template penuh `[F2]`.

## 13. Autentikasi & SSO

- **Fase 1: login lokal per company.** Undangan user, atur password sendiri, lupa password, 2FA opsional, kunci akun setelah gagal berulang. Halaman dari template `template/auth/` (login, lupa/reset password, verifikasi undangan, 2FA); tombol "Masuk dengan NXTG" ditambahkan di halaman login yang sama pada Fase 3 ([checklist §4](../00-checklist-persiapan.md#4-halaman-login-lokal-dulu-sso-menyusul)).
- `[F3]` **"Masuk dengan NXTG"** memakai OAuth2 Authorization Code (WMS sebagai *confidential client*).
- **Identitas dari SSO, hak akses dari WMS.** Kunci = ID user SSO (`sub`); keanggotaan company, role, dan cakupan tetap di WMS.
- **Auditor eksternal dan user klien** tetap memakai login lokal.
- **Satu alamat callback pusat** (mis. `auth.wms-domain.id/sso/callback`), lalu user diarahkan ke subdomain company-nya dengan token sekali pakai ([A-28](04-keputusan-dan-asumsi.md#a-28)). Bila `sub` terpeta ke user di lebih dari satu company, tampilkan **pemilih company** ([A-48](04-keputusan-dan-asumsi.md#a-48)).

## 14. Platform & langganan

- **Paket bulanan flat per company** (bukan per user/gudang).
- **Trial** dengan durasi yang diatur Super Admin (default 14 hari, [A-11](04-keputusan-dan-asumsi.md#a-11)).
- **Pembayaran manual:** tagihan otomatis → Admin Company unggah bukti transfer → Super Admin verifikasi → masa aktif diperpanjang. Pengingat via email/WA. Payment gateway `[F3]`.
- **Status langganan:** Trial → Aktif → Jatuh Tempo (tenggang 7 hari) → Ditangguhkan (hanya-baca 30 hari) → Diakhiri (data 90 hari, bisa diekspor) — [A-12](04-keputusan-dan-asumsi.md#a-12). Efek penangguhan terhadap antrean PWA, job, dan token WA: [BR-SUB-02](05-aturan-bisnis.md#br-sub).
- **Akses dukungan:** Super Admin hanya bisa membuka data company dengan izin sementara dari Admin Company, tercatat dan terlihat ([A-27](04-keputusan-dan-asumsi.md#a-27)).

## 15. Integrasi

| Dengan | Cara | Dokumen |
|---|---|---|
| Modul Akuntansi | WMS menerbitkan **kejadian stok** (kuantitas + referensi, satu kejadian per pergerakan); Akuntansi memberi nilai | [akuntansi/01](../akuntansi/01-lingkup-dan-integrasi-wms.md), [matriks kejadian](05-aturan-bisnis.md#14-matriks-kejadian-stok) |
| Modul Purchasing | WMS menerbitkan Purchase Request; Purchasing mengirim PO yang diterima lewat GRN; approval PO (nilai uang) memakai mesin approval WMS ([D-28](04-keputusan-dan-asumsi.md#d-28)) | [purchasing/01](../purchasing/01-lingkup-dan-integrasi-wms.md) |
| NXTG SSO `[F3]` | OAuth2 Authorization Code | §13 |
| WhatsApp `[F2]` | Cloud API + webhook | §8.2 |
| Perangkat & sistem lain `[F3]` | REST API dengan token per perangkat/aplikasi | Part 3 |

## 16. Kebutuhan non-fungsional

| ID | Kebutuhan |
|---|---|
| NFR-01 | `APP_DEBUG=false` di produksi; halaman error bermerek untuk 400/401/403/404/419/422/429/500/503 |
| NFR-02 | Semua aksi pengubah data memakai POST/PUT/DELETE + CSRF; aksi berisiko memakai konfirmasi |
| NFR-03 | Audit log untuk master, transaksi, login, pengaturan; IP hanya terlihat Admin. Dipisah dari timeline dokumen ([BR-GEN-05](05-aturan-bisnis.md#br-gen)) |
| NFR-04 | Rate limit untuk login, API, webhook, dan tautan bukti terima bertoken |
| NFR-05 | Backup harian per database tenant, retensi minimal 30 hari ([A-13](04-keputusan-dan-asumsi.md#a-13)), uji restore berkala |
| NFR-06 | Halaman daftar ≤ 2 detik untuk 10.000 baris dengan paginasi server |
| NFR-07 | Zona waktu per company (WIB/WITA/WIT); **simpan UTC**, tampilan/reset urutan/hari pakai per zona company ([BR-GEN-07](05-aturan-bisnis.md#br-gen)) |
| NFR-08 | Bahasa Indonesia penuh; teks UI lewat file bahasa agar siap Inggris |
| NFR-09 | Responsif: desktop untuk back-office, HP/tablet untuk PWA lapangan |
| NFR-10 | Aksesibilitas dasar: skip link, label form, kontras, `prefers-reduced-motion` |
| NFR-11 | Kepatuhan **UU No. 27 Tahun 2022 (PDP)**: data pribadi hanya untuk operasional, akses per role, bisa diekspor/dihapus atas permintaan, kebijakan privasi & ketentuan layanan sebelum trial ([O-11](04-keputusan-dan-asumsi.md#o-11)) |
| NFR-12 | Webhook WhatsApp satu nomor platform ([A-24](04-keputusan-dan-asumsi.md#a-24)); setiap pesan membawa penanda company |
| NFR-13 | *(baru)* Mutasi ledger & reservasi **atomik** dalam satu transaksi database dengan penguncian baris saldo; tidak ada stok negatif; uji beban untuk picking bersamaan pada bin yang sama |
| NFR-14 | *(baru)* Penyimpanan berkas (foto, tanda tangan, lampiran) lewat driver S3-compatible di produksi, disk lokal di pengembangan; maksimal 5 MB per file ([A-23](04-keputusan-dan-asumsi.md#a-23)); penyedia: [O-14](04-keputusan-dan-asumsi.md#o-14) |
| NFR-15 | *(baru)* Pemantauan: log terpusat per tenant, health check per tenant, peringatan antrean & job gagal |
| NFR-16 | *(baru)* Dukungan browser PWA: Chrome Android 2 versi terakhir, Safari iOS ≥ 16.4; desktop: Chrome/Edge/Firefox 2 versi terakhir |

## 17. Stack teknologi

| Lapisan | Pilihan |
|---|---|
| Backend | **Laravel 13**, PHP 8.3 (kompatibel 8.4) |
| Database | **MySQL 8.x** ([A-14](04-keputusan-dan-asumsi.md#a-14)); pusat + satu database per company |
| UI back-office | **Blade + Livewire + Alpine.js** dengan template **NexaDash** (Bootstrap 5.3.3, Bootstrap Icons, Inter) |
| Landing page | Blade + Alpine.js, gaya visual terinspirasi indonesia.travel |
| Build aset | Vite, semua library di-bundle lokal (tanpa CDN) |
| Antrean & jadwal | Laravel Queue + Scheduler (notifikasi, eskalasi, pengingat, sinkron, kejadian stok) |
| Paket (terverifikasi 23 Sep 2026) | `stancl/tenancy` 3.10, `spatie/laravel-permission` 8.3, `spatie/laravel-activitylog` 5.1, `barryvdh/laravel-dompdf` 3.1, `maatwebsite/excel` 4.0, `picqer/php-barcode-generator` 3.3, `bacon/bacon-qr-code` 3.1 — rincian & alasan di [Arsitektur §9](08-arsitektur.md#9-verifikasi-paket-laravel-13-php-83--23-sep-2026) ([O-01](04-keputusan-dan-asumsi.md#o-01) ✔) |

## 18. Peta modul & fase rilis

| Modul | Fase 1 (MVP) | Fase 2 | Fase 3 |
|---|---|---|---|
| Platform, tenancy, trial, tagihan manual | ✔ | | Payment gateway |
| Login lokal, user, role & permission, cakupan role × scope | ✔ | | SSO NXTG |
| Struktur organisasi | ✔ | | |
| Wizard setup awal company | ✔ | | |
| Impor data master dari Excel | ✔ | | |
| Master data, UoM dinamis, gudang & lokasi rak/bin | ✔ | | |
| Kartu stok, reservasi dua tahap, strategi pengambilan | ✔ | | |
| Penerimaan (GRN), QC, put-away, retur ke vendor (RTV) | ✔ (manual) | **1b:** terhubung PO | |
| Permintaan (internal + portal klien, non-katalog, gudang sumber) | ✔ | | |
| Picking, pengiriman, bukti terima (driver & tautan bertoken), selisih pengiriman (DSC) | ✔ | | |
| Pemakaian material di site (ISU) | ✔ | | |
| Retur & transfer (termasuk antar proyek dan antar titik dalam proyek) | ✔ | | |
| **Konversi material, offcut, waste** | ✔ | Resep konversi | |
| Aset dipinjamkan (serah terima, pengembalian, pemeriksaan) | ✔ | Maintenance | |
| Approval engine (web, delegasi, eskalasi, simulasi) | ✔ | Approval via WhatsApp | |
| Stock opname (bulanan/tahunan, blind count, toleransi ganda, rekonsiliasi) | ✔ | Auditor eksternal, cycle count ABC | |
| Notifikasi in-app & email | ✔ | WhatsApp (**2a**) | |
| Template dokumen & label | ✔ (bawaan + layout induk) | Editor template penuh | |
| Laporan & dashboard | ✔ (inti) | Dashboard konsolidasi opname & waste | Analitik lanjutan |
| Rencana kebutuhan material per proyek (BoQ, kuantitas) | stub | ✔ | |
| PWA | ✔ (installable, scan kamera, draf lokal) | **Offline penuh + sinkron (2b)** | |
| RFID | | ✔ | |
| Integrasi Purchasing & Akuntansi | Purchase Request manual; kejadian stok ditulis ke tabel *outbox* | **1b:** modul Purchasing inti (PO, harga beli, approval nilai) | Purchasing lengkap; konsumsi oleh Akuntansi |
| Landing page produk | ✔ | | |
| REST API publik | | | ✔ |

Alasan urutan: Fase 1 menutup akar masalah prototipe dan fitur pembeda (konversi, proyek, aset, pemakaian). Peta rilis ([D-29](04-keputusan-dan-asumsi.md#d-29)): Fase 1 → 1b Purchasing inti → 2a WhatsApp → 2b PWA offline → 3 SSO & Purchasing lengkap; persiapan di [00-checklist-persiapan](../00-checklist-persiapan.md). Fitur yang bergantung pihak luar (WhatsApp, SSO, payment gateway) atau paling berisiko teknis (offline sync) ditaruh sesudahnya. Kebutuhan `[F2]`/`[F3]` di Fase 1 dibangun sebagai *stub* ([BR-GEN-10](05-aturan-bisnis.md#br-gen)).

## 19. Risiko & mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Sinkron offline menimbulkan selisih stok | Tinggi | Server acuan akhir, antrean tinjauan, offline penuh di Fase 2 setelah ledger stabil |
| Reservasi & picking bersamaan menghasilkan stok negatif / dobel alokasi | Tinggi | NFR-13, reservasi dua tahap, uji beban |
| Konfigurasi approval terlalu rumit untuk klien trial | Sedang | Template aturan bawaan + simulasi + wizard setup |
| Biaya pesan WhatsApp membengkak | Sedang | Pilihan kejadian per company, ringkasan harian, pencatatan pemakaian, batas per paket |
| Kompatibilitas paket pihak ketiga dengan Laravel 13 | Sedang | Diverifikasi di Part 3 sebelum coding |
| Pemeliharaan banyak database tenant | Sedang | Migrasi terotomasi dengan log per tenant, backup per tenant, dashboard kesehatan tenant |
| Data master awal klien berantakan | Tinggi | Impor Excel dengan validasi & pratinjau |
| Ketergantungan SSO pada pembuatan akun manual | Sedang | Login lokal tetap tersedia |
| Asumsi A-50 (pemindahan dalam proyek) belum divalidasi | Rendah | A-25–A-49 divalidasi 23 Sep 2026; A-50 hanya menyentuh jalur ringan TRF/SJ, divalidasi sebelum Part 4 modul Transfer |

## 20. Langkah berikutnya

1. A-25–A-49 divalidasi 23 Sep 2026 ([laporan](../00-laporan-validasi-2026-09-23.md)); tersisa [A-50](04-keputusan-dan-asumsi.md#a-50), O-12/O-13/O-15, dan keterangan di [00-audit](../00-audit/README.md) — semuanya di [checklist persiapan](../00-checklist-persiapan.md).
2. **Part 2 (draf sudah ada):** proses bisnis to-be BPMN 2.0 di `docs/diagram/` + ringkasan tekstual per lane di [07-proses-bisnis.md](07-proses-bisnis.md), [07a](07a-proses-bisnis-lanjutan.md), [07b](07b-proses-bisnis-pendukung.md) — 10 alur berbasis nilai default asumsi; difinalkan setelah validasi.
3. **Part 3 (draf sudah ada):** [08-arsitektur.md](08-arsitektur.md) (keputusan AD-xx, paket terverifikasi, outbox kejadian stok) dan model data [08a](08a-model-data-inti.md), [08b](08b-model-data-stok-dokumen.md), [08c](08c-model-data-pendukung.md) + `diagram/erd-*.drawio`; difinalkan setelah validasi asumsi.
4. **Part 4:** spesifikasi modul dari [template](_template-spesifikasi-modul.md), urutan di [08 §12](08-arsitektur.md#12-langkah-berikutnya-part-4).
