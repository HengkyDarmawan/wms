# Riset WMS & Sistem Sejenis

**Versi:** 0.3 (tautan dirapikan, keputusan "tidak dipakai" dicatat eksplisit, daftar riset teknis Part 3 ditambah)
**Tanggal:** 23 September 2026
**Status:** riset produk selesai; riset teknis (§2.8) dikerjakan di Part 3
**Dokumen terkait:** [Blueprint](01-blueprint.md) · [Keputusan & Asumsi](04-keputusan-dan-asumsi.md) · [Aturan Bisnis](05-aturan-bisnis.md)
**Tujuan:** menemukan praktik terbaik dari produk sejenis untuk memperkuat desain WMS Proyek, lalu memutuskan mana yang **diadopsi**, **diadaptasi**, atau **tidak dipakai**.

> Catatan metode: riset berbasis dokumentasi publik, halaman produk, dan artikel praktik industri (bukan uji coba produk). Semua isi diringkas dengan kata-kata sendiri; sumber ada di bagian akhir.

---

## 1. Produk & sumber yang dipelajari

| Kategori | Produk / sumber | Kenapa relevan |
|---|---|---|
| WMS/ERP umum | **Odoo Inventory** | Referensi matang untuk lokasi, aturan put-away, lot/serial, strategi pengambilan |
| Manajemen scaffolding & sewa | **Avontus Quantify** | Paling dekat dengan kasus scaffolding: sewa + jual, stok per job site |
| Pelacakan alat & aset proyek | **ToolWatch (AlignOps)**, **ToolTrak** | Aset dipinjamkan ke site, check-out/in, maintenance |
| Material berukuran & sisa potong | **Acumatica** (diskusi komunitas), **SYSPRO Material Yield System**, **steelprojects**, **AutoBarSizer** | Cara melacak offcut/remnant dan meminimalkan waste |
| Stock opname | Artikel praktik cycle count (Cleverence, PackageX) | Blind count, toleransi selisih, ABC |
| Kanal notifikasi | Dokumentasi & ulasan harga WhatsApp Business Platform | Biaya dan aturan approval via WA |
| Pasar Indonesia | Artikel & jurnal tentang software kontraktor dan stok material proyek | Posisi produk di pasar lokal |

## 2. Temuan per area

### 2.1 Lokasi & put-away — Odoo

- Odoo memakai **kategori penyimpanan** pada lokasi (mis. syarat suhu atau aksesibilitas) plus batas kapasitas, lalu **aturan put-away** per produk/kategori memilih lokasi terbaik secara otomatis saat barang masuk.
- Strategi pengambilan **FIFO/FEFO** bergantung pada fitur lot/serial dan tanggal kedaluwarsa yang dinyalakan di pengaturan — pola "nyalakan fitur di tingkat perusahaan, pakai di tingkat produk".

**Keputusan:** *Adopsi* kategori penyimpanan + kapasitas bin + saran put-away ([Blueprint 6.3](01-blueprint.md#63-lokasi-rak--bin--wajib), dokumen `PUT`, [BR-GRN-03](05-aturan-bisnis.md#br-grn)). *Adopsi* pola aktivasi fitur dua lapis (P-08). *Tidak dipakai:* aturan put-away multi-langkah Odoo (dua langkah/tiga langkah penerimaan) — terlalu berat untuk gudang site; cukup Penerimaan → (QC) → Put-away.

### 2.2 Scaffolding: sewa + jual — Avontus Quantify

- Quantify melacak **di mana setiap peralatan berada** (gudang atau job site) dan menghitung hak tagih sewa, dengan jual barang habis pakai dan sewa dalam satu sistem.
- Barang yang **tidak kembali dari job site langsung ditandai hilang**.
- Penawaran dibuat berdasarkan **ketersediaan saat ini dan mendatang**, termasuk material yang sedang ada di site.
- Penagihan sewa punya siklus sendiri (dimuka, belakangan, atau kombinasi) dan terhubung ke sistem akuntansi.

**Keputusan:** *Adopsi* bin virtual **On-site Proyek** (hanya aset) dan tab **Stok On-site** per proyek dengan tiga sub-tampilan, pemisahan tegas efek stok antara barang jual-putus dan barang sewa ([Blueprint 6.6](01-blueprint.md#66-stok), [A-25](04-keputusan-dan-asumsi.md#a-25), [BR-SJ-04](05-aturan-bisnis.md#br-sj)), dan laporan **Material per Proyek** ([Blueprint 6.9a](01-blueprint.md#69a-laporan-inti-fase-1)). *Adopsi* deteksi aset/material belum kembali saat penutupan proyek ([BR-PRJ-02](05-aturan-bisnis.md#br-prj)). *Adaptasi* penagihan sewa: WMS hanya menyediakan **hari pakai**, perhitungan tagihan di Akuntansi ([D-07](04-keputusan-dan-asumsi.md#d-07)). *Tidak dipakai (sekarang):* penawaran berdasarkan ketersediaan mendatang (forecast availability) — kandidat Fase 3 setelah reservasi stabil.

### 2.3 Alat & aset proyek — ToolWatch, ToolTrak

- Status aset yang jelas: tersedia, dipakai, servis, atau ditandai bermasalah, dengan filter per site, orang, dan kategori.
- **Jejak audit** untuk setiap scan, check-out, transfer, dan pengembalian.
- **Permintaan dari lapangan** lewat aplikasi, **transfer cepat antar lokasi**, **hierarki lokasi** yang mencakup job site baru.
- **Pengingat maintenance** dan **pengembalian terlambat**; antarmuka "scan dulu" di HP; mendukung barcode, QR, RFID, dan Bluetooth.
- Daftar standar (kit) untuk mempercepat picking.

**Keputusan:** *Adopsi* siklus hidup aset & maintenance ([Blueprint 6.8](01-blueprint.md#68-aset-dipinjamkan), [BR-AST](05-aturan-bisnis.md#br-ast)), pengingat jatuh tempo, antarmuka scan-first di PWA. *Adaptasi* "kit" menjadi **resep konversi / set barang** di Fase 2. *Tidak dipakai:* peminjaman aset ke orang tanpa proyek — WMS mewajibkan Proyek Internal ([BR-AST-02](05-aturan-bisnis.md#br-ast)) agar semua aset selalu punya konteks proyek.

### 2.4 Material berukuran & sisa potong

- Komunitas Acumatica menunjukkan bahwa sisa potongan sering ditangani dengan **atribut per lot/potongan**: tetap satu SKU, tetapi ukuran tiap potongan disimpan sendiri.
- SYSPRO MYS **memberi ID otomatis pada offcut** dan mengembalikannya ke stok, lalu menyarankan pemenuhan dari stok sisa di lokasi mana pun sebelum membeli baru.
- steelprojects membuat **ID untuk setiap sisa** berikut ukurannya agar bisa dipakai ulang, serta melacak asal material (nomor cor pemasok) sepanjang proses.
- AutoBarSizer memperhitungkan **tebal mata gergaji (kerf)**, **panjang minimum sisa** yang masih layak pakai, dan **preferensi memakai sisa lebih dulu**.

**Keputusan:** *Adopsi* mode pelacakan **Per potong** dengan ID + ukuran per potongan, panjang minimum offcut, kerf opsional, strategi **"Sisa potongan dulu"**, dan silsilah ([Blueprint 6.7](01-blueprint.md#67-konversi-material-offcut--waste), [BR-CNV](05-aturan-bisnis.md#br-cnv), [A-36](04-keputusan-dan-asumsi.md#a-36)). *Tidak dipakai (sekarang):* optimasi pola potong otomatis — kandidat fitur lanjutan; pelacakan nomor cor pemasok (heat number) — bisa dicatat sebagai atribut lot bila dibutuhkan, tidak dibuat fitur khusus.

### 2.5 Stock opname

- Program hitung yang baik memakai **hitung buta** agar penghitung tidak sekadar mengonfirmasi angka sistem.
- **Toleransi berjenjang:** selisih kecil disetujui otomatis, selisih sedang dihitung ulang (oleh orang berbeda), selisih besar butuh persetujuan atasan dan analisis akar masalah.
- **Pembekuan lokasi** selama hitung, validasi satuan sebelum hitung, dan **kategori akar masalah** (salah ambil, salah taruh, salah satuan, rusak/hilang).
- **Kelas ABC** menentukan frekuensi hitung; hitung berbasis lokasi melengkapinya untuk zona berisiko.

**Keputusan:** *Adopsi* semuanya untuk sesi bulanan/tahunan (Fase 1); cycle count ABC di Fase 2 ([Blueprint 9](01-blueprint.md#9-stock-opname--audit), [BR-OPN](05-aturan-bisnis.md#br-opn)). Toleransi memakai ambang relatif **dan** absolut ([A-42](04-keputusan-dan-asumsi.md#a-42)) karena persentase saja tidak bermakna untuk bin berisi sedikit unit.

### 2.6 WhatsApp untuk notifikasi & approval

- Pesan yang dimulai bisnis wajib memakai **template yang disetujui Meta**, dan template bisa memuat **tombol balasan cepat** (cocok untuk Setujui/Tolak).
- **Perubahan harga Meta mulai 1 Oktober 2026** (dikonfirmasi beberapa penyedia resmi):
  - **Template utility** — jenis yang dipakai untuk notifikasi approval — **selalu ditagih**, baik dikirim di dalam maupun di luar jendela layanan 24 jam. Sebelumnya, template utility di dalam jendela gratis sejak Juli 2025.
  - **Pesan layanan** (balasan bebas di dalam jendela 24 jam, termasuk pesan bertombol) juga mulai ditagih, dengan **1.000 pesan gratis per bulan per nomor**.
  - Pesan masuk dari user (termasuk saat approver menekan tombol) tetap **gratis**.
  - Waktu mulai penagihan mengikuti zona waktu akun WhatsApp Business.

**Keputusan:** *Adopsi* Cloud API resmi dengan template bertombol. *Tambahan desain:*
1. Kejadian yang memakai WA bisa dipilih per company, dengan opsi ringkasan harian ([O-04](04-keputusan-dan-asumsi.md#o-04)).
2. **Balasan konfirmasi setelah approver menekan tombol dibuat singkat, satu pesan saja**, dan bisa dimatikan. Balasan ini termasuk pesan layanan, sehingga menghabiskan kuota 1.000 gratis per bulan yang dipakai bersama semua company (satu nomor platform, [A-24](04-keputusan-dan-asumsi.md#a-24)).
3. Pemakaian pesan dicatat **per company dan per jenis pesan** (template utility vs pesan layanan) sebagai dasar penentuan harga paket.
4. Keputusan via WA mencatat nomor pengirim dan `wa_message_id` ([BR-APR-10](05-aturan-bisnis.md#br-apr)); OTP bukti terima tanpa akun juga lewat kanal ini bila sudah aktif ([O-15](04-keputusan-dan-asumsi.md#o-15)).

*Tidak dipakai:* percakapan bebas (chatbot) di WhatsApp — hanya template bertombol dan balasan konfirmasi satu pesan, agar biaya terkendali.

### 2.7 Pasar Indonesia

- Dari penelusuran terbatas, pilihan yang umum ditemui adalah ERP/akuntansi umum dengan fitur stok, software manajemen konstruksi luar negeri yang berfokus pada jadwal/anggaran, dan aplikasi stok material hasil penelitian atau buatan khusus.
- Belum ditemukan produk lokal yang menggabungkan **stok per proyek + konversi material berukuran + aset dipinjamkan + portal klien** dalam satu SaaS.

**Implikasi:** posisi produk sebagai **"WMS khusus material proyek"** masuk akal. Landing page sebaiknya menonjolkan konversi material, pelacakan per proyek, pemakaian material per proyek, dan aset.

### 2.8 Riset teknis untuk Part 3

Riset produk di atas tidak menyentuh risiko teknis tertinggi ([Blueprint §19](01-blueprint.md#19-risiko--mitigasi)). Daftar topik yang **wajib diriset dan diputuskan di Part 3** sebelum coding, masing-masing dengan kriteria keputusan:

| Topik | Pertanyaan yang harus dijawab | Kriteria | Terkait |
|---|---|---|---|
| Paket multi-tenancy multi-database untuk Laravel 13 | Kandidat, dukungan Laravel 13, cara migrasi per tenant, identifikasi subdomain, koneksi pusat + tenant dalam satu request | Aktif dipelihara, tes migrasi 50 tenant, kompatibel queue | [O-01](04-keputusan-dan-asumsi.md#o-01) |
| Pola reservasi & penguncian stok | `SELECT … FOR UPDATE` per saldo vs optimistic locking; urutan kunci untuk mencegah deadlock saat picking bersamaan | Tidak ada stok negatif pada uji beban 50 picking paralel | NFR-13, [BR-STK-06](05-aturan-bisnis.md#br-stk) |
| Sinkron offline PWA (Fase 2) | Penyimpanan lokal (IndexedDB), antrean, resolusi konflik, nomor sementara, batas dukungan iOS | Prototipe: 100 transaksi offline sinkron tanpa duplikat | [A-43](04-keputusan-dan-asumsi.md#a-43), [BR-OPN-03](05-aturan-bisnis.md#br-opn) |
| Kejadian stok (outbox → konsumen) | Tabel outbox + job vs webhook; jaminan urutan & idempoten | Tidak ada kejadian hilang saat job gagal | [Akuntansi §5](../akuntansi/01-lingkup-dan-integrasi-wms.md#5-aturan-integrasi) |
| PDF & label | Paket PDF (server-side) dan barcode/QR; ukuran label thermal | Render Surat Jalan 50 baris < 2 detik | [O-09](04-keputusan-dan-asumsi.md#o-09) |
| Penyimpanan berkas & kompresi foto | Driver S3-compatible, kompresi sisi klien di PWA | Foto ≤ 5 MB, unggah di jaringan lemah | NFR-14, [O-14](04-keputusan-dan-asumsi.md#o-14) |
| WhatsApp BSP & OTP | Penyedia, biaya, persetujuan template, OTP SMS sebagai cadangan | Biaya per company terukur | [O-03](04-keputusan-dan-asumsi.md#o-03), [O-15](04-keputusan-dan-asumsi.md#o-15) |
| Role & permission, activity log | Paket yang mendukung scope per penugasan (role × gudang/proyek) | Query permission ≤ 5 ms per request | [BR-GEN-09](05-aturan-bisnis.md#br-gen) |

## 3. Ringkasan adopsi

| Fitur | Sumber inspirasi | Status | Fase |
|---|---|---|---|
| Kategori penyimpanan + kapasitas bin + saran put-away | Odoo | Adopsi | 1 |
| Aktivasi fitur dua lapis (company → item) | Odoo | Adopsi | 1 |
| Stok on-site per proyek | Quantify | Adopsi | 1 |
| Deteksi barang belum kembali saat tutup proyek | Quantify | Adopsi | 1 |
| Penagihan sewa | Quantify | Adaptasi (di Akuntansi) | 3 |
| Siklus hidup aset + maintenance | ToolWatch/ToolTrak | Adopsi | 1–2 |
| Antarmuka scan-first di HP | ToolTrak | Adopsi | 1 |
| Kit / resep | ToolWatch | Adaptasi (resep konversi) | 2 |
| ID + ukuran per potongan, offcut, kerf | SYSPRO, steelprojects, AutoBarSizer | Adopsi | 1 |
| Optimasi pola potong | AutoBarSizer | Ditunda | — |
| Blind count, toleransi berjenjang, pembekuan | Praktik cycle count | Adopsi | 1 |
| Cycle count ABC | Praktik cycle count | Adopsi | 2 |
| Approval via tombol WA | WhatsApp Cloud API | Adopsi | 2 |
| Efek stok jual-putus vs sewa | Quantify | Adopsi ([A-25](04-keputusan-dan-asumsi.md#a-25)) | 1 |
| Laporan material per proyek (termasuk *terpakai*) | Quantify | Adopsi ([A-32](04-keputusan-dan-asumsi.md#a-32)) | 1 |
| Balasan konfirmasi WA hemat kuota | Perubahan harga Meta Okt 2026 | Adopsi | 2 |
| Penerimaan multi-langkah (2/3 langkah) | Odoo | **Tidak dipakai** | — |
| Penawaran berdasarkan ketersediaan mendatang | Quantify | **Tidak dipakai** (kandidat F3) | — |
| Pinjam aset ke orang tanpa proyek | ToolWatch | **Tidak dipakai** (pakai Proyek Internal) | — |
| Pelacakan nomor cor pemasok | steelprojects | **Tidak dipakai** (atribut lot bila perlu) | — |
| Chatbot WhatsApp bebas | — | **Tidak dipakai** | — |

## 4. Referensi

- Odoo — Putaway Rules & Storage Categories: https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/putaway.html · https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/storage_category.html
- Odoo — Removal Strategies (FIFO/FEFO): https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/removal_strategies.html
- Avontus Quantify: https://avontus.com/quantify/ · https://docs.avontus.com/quantify · https://www.avontus.com/blog/maximize-scaffolding-business-roi/
- ToolWatch by AlignOps: https://alignops.com/toolwatch · https://l.alignops.com/toolwatch-by-alignops
- ToolTrak (Capterra): https://www.capterra.ca/software/1094896/ToolTrak
- Acumatica Community — remnant: https://community.acumatica.com/distribution-6/inventory-pieces-remnants-any-ideas-on-best-way-to-deal-with-this-8194
- SYSPRO Material Yield System: https://ke.syspro.com/dl/FS/SYSPRO-Material_Yield_System-FS.pdf
- steelprojects — stock & remnant: https://www.steelprojects.com/?p=2565
- Fraunhofer SCAI AutoBarSizer: https://www.scai.fraunhofer.de/content/dam/scai/de/documents/Mediathek/Produktblaetter/OPT_AutoBarSizer_EN.pdf
- Cycle count: https://packagex.io/blog/cycle-counting-best-practices · https://www.cleverence.com/articles/business-blogs/cycle-count-cycle-count-4837/
- WhatsApp pricing: https://respond.io/id/blog/whatsapp-pricing-change-2026 · https://www.courier.com/blog/whatsapp-pricing-changes-october-2026 · https://m.aisensy.com/blog/whatsapp-pricing-update-october-2026/ · https://zipchat.ai/blog/whatsapp-business-api-complete-guide
- Pasar Indonesia: https://www.jurnal.id/id/blog/software-untuk-perusahaan-kontraktor/
