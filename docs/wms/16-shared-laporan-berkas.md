# Spesifikasi Modul — `shared` (Kerangka Laporan & Penyimpanan Berkas)

**Versi:** 0.3
**Tanggal:** 24 September 2026
**Status:** selesai Fase 1 sebagian — dokumen ini **mencatat kode yang sudah ada** (dibangun tanpa spesifikasi); laporan modul Stock, Request, dan Picking/Shipment belum dibangun (§13.4); v0.3: laporan kedelapan *Material per proyek* dari modul Issue ([23-pemakaian](23-pemakaian.md) §9)
**Modul:** `shared` (`app/Domain/Shared`)
**Fase:** F1
**Dokumen terkait:** [Blueprint §6.9a](01-blueprint.md#69a-laporan-inti-fase-1) · [Arsitektur §2 (AD-09, AD-10)](08-arsitektur.md#2-keputusan-arsitektur) · [Blueprint §16 (NFR-14)](01-blueprint.md#16-kebutuhan-non-fungsional) · [A-68](04-keputusan-dan-asumsi.md#a-68) · [Aturan Bisnis §BR-ACC](05-aturan-bisnis.md#br-acc) · [Glosarium](03-glosarium.md)
**Ketergantungan modul:** `access` (permission & cakupan), `master`, `warehouse` (sumber data laporan). Dipakai oleh semua modul yang punya §9 *Laporan & dashboard* atau yang menyimpan berkas.

---

## 1. Tujuan & lingkup

Modul ini bukan modul bisnis; ia menyediakan dua layanan bersama yang dipakai modul lain:

- **Kerangka laporan.** Setiap laporan di §9 spesifikasi modul berbentuk sama: tabel, beberapa penyaring, tombol ekspor. Laporan didefinisikan sebagai **data** (satu kelas turunan `Report`), lalu satu layar dan satu jalur ekspor Excel melayani semuanya. Menambah laporan tidak menambah layar.
- **Penyimpanan berkas.** Satu kelas `StoreUpload` untuk menyimpan, menghapus, dan mengalirkan berkas unggahan (tanda tangan, foto item) di disk privat per company, disajikan lewat route berotorisasi.

**Tidak termasuk:** dashboard/grafik, ekspor PDF (§13.1), impor Excel (`import_batches`, AD-09), tabel `attachments` generik, penyimpanan S3 produksi ([O-14](04-keputusan-dan-asumsi.md#o-14)).

## 2. Aktor & permission

Modul ini **tidak mendaftarkan permission sendiri**. Izin diperiksa per laporan memakai permission `view` milik modul sumber datanya:

| Laporan | Permission | Modul pemilik permission |
|---|---|---|
| `user-role-cakupan`, `log-login` | `user.view` | `access` |
| `daftar-item`, `item-sementara` | `item.view` | `master` |
| `daftar-proyek` | `project.view` | `master` |
| `daftar-gudang` | `warehouse.view` | `warehouse` |
| `daftar-bin` | `bin.view` | `warehouse` |

Menu *Laporan* (`/reports`) tampil untuk semua user internal; isinya hanya laporan yang permission-nya dipegang user (`ReportRegistry::availableTo`).

Berkas: tanda tangan sendiri selalu boleh dibuka; tanda tangan orang lain menuntut `user.view`; foto item mengikuti policy `view`/`update` model `Item`.

## 3. Entitas & data

Tidak ada tabel baru. Laporan membaca tabel modul lain lewat model Eloquent, sehingga global scope `ScopedToUser` ([BR-ACC-05](05-aturan-bisnis.md#br-acc)) ikut berlaku — model `Warehouse` dan `Bin` memakainya. Berkas disimpan sebagai **path** di kolom model pemiliknya (`users.signature_path`, `items.photo_path`), bukan di tabel `attachments` ERD (tabel itu belum ada).

### 3.1 Kelas inti

| Kelas | Berkas | Peran |
|---|---|---|
| `Report` (abstrak) | `app/Domain/Shared/Reports/Report.php` | Kontrak satu laporan: `key()`, `title()`, `permission()`, `description()`, `columns()` (kunci ⇒ judul), `rows(array $filters)`, `filters()` (kunci ⇒ label + pilihan; tanpa pilihan = isian teks), `fileName()` (`<key>-YmdHis`) |
| `ReportRegistry` | `app/Domain/Shared/Reports/ReportRegistry.php` | Daftar statis kelas laporan; `all()`, `availableTo(User)`, `find(key)` |
| `ReportExport` | `app/Domain/Shared/Reports/ReportExport.php` | Satu kelas ekspor `maatwebsite/excel` untuk semua laporan (AD-09): `FromCollection`, `WithHeadings`, `ShouldAutoSize`, baris judul tebal |
| `ReportViewer` | `app/Domain/Shared/Livewire/ReportViewer.php` | Komponen Livewire layar laporan; penyaring terikat ke query string (`#[Url]`) |
| `ReportController` | `app/Http/Controllers/Shared/ReportController.php` | `index`, `show`, `export` |
| `StoreUpload` | `app/Domain/Shared/Files/StoreUpload.php` | `handle()`, `delete()`, `exists()`, `stream()` |
| `FileController` | `app/Http/Controllers/Shared/FileController.php` | Mengalirkan tanda tangan dan foto item setelah izin diperiksa |

### 3.2 Definisi laporan terdaftar (`Reports/Definitions`)

| Kunci | Kelas | Judul | Permission | Kolom | Penyaring | Asal |
|---|---|---|---|---|---|---|
| `user-role-cakupan` | `UserRoleScopeReport` | Pengguna × role × cakupan | `user.view` | pengguna, email, role, cakupan (nama gudang/proyek, bukan id), berlaku dari, berlaku sampai, atasan langsung, status pengguna | status pengguna (aktif/nonaktif), jenis cakupan | [10-access §9](10-access.md#9-laporan--dashboard) *User × role × cakupan* |
| `log-login` | `LoginLogReport` | Log masuk 30 hari | `user.view` | waktu (zona waktu company), email, pengguna, alamat IP, kanal, hasil | hasil, email mengandung | [10-access §9](10-access.md#9-laporan--dashboard) *Log login 30 hari* |
| `daftar-item` | `ItemListReport` | Daftar item | `item.view` | kode, nama, kategori, satuan dasar, mode pelacakan, kepemilikan, titik pesan ulang, stok minimum, status | status, mode pelacakan, kepemilikan | [11-master §9](11-master.md#9-laporan--dashboard) *Daftar item* |
| `item-sementara` | `ProvisionalItemReport` | Item sementara | `item.view` | kode, nama, kategori, satuan dasar, dibuat, umur (hari) | — (selalu `status = provisional`) | [11-master §9](11-master.md#9-laporan--dashboard) *Item sementara* |
| `daftar-proyek` | `ProjectListReport` | Daftar proyek | `project.view` | kode, nama, klien (*Proyek Internal* bila internal), PIC, mulai, target selesai, jumlah Gudang Site, status | status, klien | [11-master §9](11-master.md#9-laporan--dashboard) *Proyek* |
| `daftar-gudang` | `WarehouseListReport` | Daftar gudang | `warehouse.view` | kode, nama, tipe, gudang induk, proyek, kepala gudang, jumlah zona, jumlah bin, status | tipe, status (aktif/nonaktif) | [12-warehouse §9](12-warehouse.md#9-laporan--dashboard) *Daftar gudang* |
| `daftar-bin` | `BinListReport` | Daftar bin | `bin.view` | kode bin, gudang, jenis, kategori penyimpanan, penegakan kapasitas, kapasitas jumlah, kapasitas berat, proyek, perlu dihitung, status | gudang, jenis, status, kategori penyimpanan | [12-warehouse §9](12-warehouse.md#9-laporan--dashboard) *Daftar bin* |
| `material-per-proyek` | `ProjectMaterialReport` | Material per proyek | `issue.view` | proyek, kode & nama item, satuan, diminta, terkirim, terpakai, diretur, di Gudang Site, aset di proyek (dibaca dari kartu stok, [A-151](04-keputusan-dan-asumsi.md#a-151)) | proyek (dalam cakupan), item mengandung | [23-pemakaian §9](23-pemakaian.md#9-laporan--dashboard), Blueprint §9 *Material per proyek* |

Tidak ada kolom nilai uang di laporan mana pun ([D-07](04-keputusan-dan-asumsi.md#d-07)).

## 4. Mesin status

Tidak ada. Modul ini tidak memiliki dokumen berstatus.

## 5. Aturan bisnis yang berlaku

| Rujukan | Catatan implementasi |
|---|---|
| [BR-ACC-05](05-aturan-bisnis.md#br-acc) | Laporan memakai `Model::query()` tanpa `withoutGlobalScopes()` pada data utama, jadi cakupan gudang user berlaku di `daftar-gudang` dan `daftar-bin`. `UserRoleScopeReport` dan `ProjectListReport` memakai `withoutGlobalScopes()` **hanya** untuk memetakan id gudang ke nama / menghitung Gudang Site |
| [BR-GEN-09](05-aturan-bisnis.md#br-gen) | Setiap layar dan ekspor memeriksa `hasPermission($laporan->permission())`; tanpa izin → 403 |
| [D-07](04-keputusan-dan-asumsi.md#d-07) | Tidak ada nilai uang di definisi laporan maupun ekspor |
| AD-09 ([Arsitektur §2](08-arsitektur.md#2-keputusan-arsitektur)) | Ekspor Excel lewat `maatwebsite/excel`; satu kelas `ReportExport` |
| AD-10, NFR-14, [A-68](04-keputusan-dan-asumsi.md#a-68) | Berkas di disk `local` yang dipisah per company, batas 5 MB, disajikan lewat route berotorisasi |

## 6. Layar

### 6.1 Daftar laporan — `GET /reports` — `ReportController@index`

Kartu per laporan (judul, deskripsi, tombol *Buka*) untuk laporan yang boleh dibuka user. Kosong → "Belum ada laporan yang boleh Anda buka." View `resources/views/shared/reports/index.blade.php`. Menu *Laporan* di sidebar dan palet perintah.

### 6.2 Satu laporan — `GET /reports/{report}` — `ReportController@show` + `ReportViewer`

View `resources/views/shared/reports/show.blade.php` memuat komponen `ReportViewer` (`resources/views/livewire/shared/report-viewer.blade.php`).

| Bagian | Perilaku |
|---|---|
| Penyaring | Dibangun dari `filters()`: pilihan → select, tanpa pilihan → isian teks. Nilai disimpan di query string; aksi `bersihkanFilter` mengosongkan semuanya |
| Tabel | Kolom dari `columns()`; layar **dibatasi 500 baris** pertama, jumlah total ditampilkan |
| Ekspor | Tautan ke `/reports/{report}/export` dengan penyaring yang sama |
| Izin | Diperiksa di `mount()` **dan** di setiap `render()` (403) |

### 6.3 Ekspor — `GET /reports/{report}/export` — `ReportController@export`

Mengunduh `<key>-YmdHis.xlsx` berisi **seluruh** baris (tanpa batas 500). Nilai penyaring dari query `filters[...]` dipaksa ke string. Setiap ekspor dicatat ke log aktivitas `report` (pelaku, kunci laporan, penyaring). `LoginLogReport` sendiri membatasi 5.000 baris terbaru.

### 6.4 Berkas

| Route | Aksi | Izin |
|---|---|---|
| `POST /profile/signature`, `DELETE /profile/signature` | unggah/hapus tanda tangan sendiri (`ProfileController`) | user yang login |
| `POST /items/{item}/photo`, `DELETE /items/{item}/photo` | unggah/hapus foto item (`ItemPhotoController`, log aktivitas `master`) | policy `update` Item |
| `GET /files/signature/{user}` | alirkan tanda tangan | diri sendiri atau `user.view` |
| `GET /files/item-photo/{item}` | alirkan foto item | policy `view` Item |

Semua route di grup `auth` + `internal` di `routes/tenant.php`.

## 7. Kejadian stok & integrasi

Tidak ada. Modul ini tidak menulis stok.

## 8. Notifikasi

Tidak ada.

## 9. Laporan & dashboard

Lihat §3.2 untuk laporan yang sudah terdaftar dan §13.4 untuk yang belum.

## 10. Kasus uji

Uji ada di `tests/Feature/Shared`. ID memakai akhiran huruf untuk varian dalam satu TC.

| ID | Given | When | Then | Rujukan |
|---|---|---|---|---|
| TC-RPT-01 | Admin Company dan Driver | `ReportRegistry::availableTo` | 8 laporan terdaftar (v0.3: + *Material per proyek*); Admin membuka 8; Driver hanya `daftar-item`, `item-sementara`, `daftar-proyek`, `daftar-gudang` | BR-GEN-09 |
| TC-RPT-01b | Driver tanpa `user.view` | buka `/reports`, `/reports/daftar-item`, `/reports/log-login`, `/reports/log-login/export` | dua pertama 200; dua terakhir 403 | BR-GEN-09 |
| TC-RPT-01c | Kepala Gudang bercakupan gudang CKG | isi `user-role-cakupan` | cakupan tampil sebagai "Gudang: Gudang Utama Cakung", bukan id | — |
| TC-RPT-01d | Data master demo (4 item) | `daftar-item` dengan `tracking_mode = piece` | 1 baris (`PIPA-PVC-4`) dari 4 | — |
| TC-RPT-01e | Admin Company | ekspor `daftar-item` | 200, `content-type` spreadsheetml, nama berkas memuat `daftar-item` | AD-09 |
| TC-RPT-01f | Dua gudang, Kepala Gudang hanya di CKG | isi `daftar-gudang` | hanya CKG | BR-ACC-05 |
| TC-FIL-01 | Staf gudang | unggah lalu hapus tanda tangan | path tersimpan, bisa dibuka lewat `/files/signature/{id}`, lalu null | A-68 |
| TC-FIL-01b | Tanda tangan milik staf | Driver lalu Admin membukanya | Driver 403, Admin 200 | A-68 |
| TC-FIL-01c | — | unggah PDF sebagai tanda tangan | galat validasi, tidak tersimpan | NFR-14 |
| TC-FIL-01d | — | unggah gambar 6.000 KB | galat validasi, tidak tersimpan | NFR-14 |
| TC-FIL-01e | Admin Company, item `BAUT-M12` | unggah, buka, hapus foto item | tersimpan, 200, lalu null | A-68 |
| TC-FIL-01f | Staf gudang (hanya `item.view`) | unggah foto item | 403 | BR-GEN-09 |
| TC-FIL-01g | — | `StoreUpload::handle` langsung | path fisik memuat pengenal `tenant` dan folder `signatures` | A-68 |
| TC-FIL-02 | Tenancy aktif | `asset()` untuk build Vite dan logo | URL tidak memuat `/tenancy/assets/` | A-68 |
| TC-FIL-02b | — | buka halaman login company | 200; logo dari `/img/logo.svg`, tidak ada `/tenancy/assets/` | A-68 |

## 11. Di luar lingkup modul ini

Grafik/dashboard per peran; ekspor PDF; impor Excel dan pratinjau `import_batches` (AD-09); tabel `attachments` untuk lampiran dokumen; kompresi foto sisi klien di PWA (AD-10); penyimpanan S3 produksi ([O-14](04-keputusan-dan-asumsi.md#o-14)); laporan yang memerlukan data modul yang belum dibangun (receipt, transfer, return, issue, conversion, asset, count).

## 12. Definisi selesai

- [x] Kontrak `Report`, `ReportRegistry`, `ReportExport`, `ReportViewer`
- [x] Tiga route laporan dan menu *Laporan*
- [x] Laporan §9 modul Access, Master, Warehouse (7 definisi)
- [x] `StoreUpload` dan route berkas berotorisasi
- [x] TC-RPT-01 dan TC-FIL-01 (beserta variannya) lulus
- [ ] Laporan §9 modul Stock, Request, Picking/Shipment (§13.4)
- [ ] Ekspor PDF (§13.1)

## 13. Status & catatan implementasi (24 September 2026)

Kode modul ini dibangun lebih dulu daripada dokumennya, bersamaan dengan modul Access, Master, dan Warehouse. Dokumen ini ditulis belakangan untuk mencatat apa yang ada; tidak ada fitur yang ditambahkan di sini.

### 13.1 Penyimpangan dari dokumen lain

1. **Hanya ekspor Excel.** [Blueprint §6.9a](01-blueprint.md#69a-laporan-inti-fase-1) menuntut ekspor Excel **dan PDF**; kode baru menyediakan Excel.
2. **Penyaring berbeda dari §9 modul asal:**
   - *User × role × cakupan* ([10-access §9](10-access.md#9-laporan--dashboard)) meminta penyaring role, gudang/proyek, status; kode menyediakan status pengguna dan **jenis** cakupan (bukan gudang/proyek tertentu), tanpa penyaring role.
   - *Log login 30 hari* meminta penyaring user; kode menyediakan *email mengandung*.
   - *Daftar item* ([11-master §9](11-master.md#9-laporan--dashboard)) meminta penyaring kategori; kode menyediakan status, mode pelacakan, kepemilikan (tanpa kategori). Kolom *stok minimum* ditambahkan.
   - *Item sementara* meminta kolom *dibuat dari REQ*; kode menampilkan tanggal dibuat, kategori, dan satuan dasar, tanpa nomor REQ asal.
   - *Daftar gudang* ([12-warehouse §9](12-warehouse.md#9-laporan--dashboard)) meminta penyaring proyek; kode hanya tipe dan status.
   - *Daftar bin* menambah kolom penegakan kapasitas, kapasitas berat, proyek, dan penanda hitung.
3. **Kunci laporan tidak dikenal menghasilkan 500, bukan 404.** `ReportRegistry::find()` melempar `InvalidArgumentException` dan tidak dipetakan di `bootstrap/app.php`.
4. **Berkas disimpan sebagai path di model pemilik**, bukan lewat tabel `attachments` ERD.

### 13.2 Keputusan implementasi

1. **Laporan sebagai data, bukan layar.** Modul baru menambahkan kelas di `Reports/Definitions` dan mendaftarkannya di `ReportRegistry::REPORTS`; tidak membuat route atau komponen sendiri.
2. **Layar dibatasi 500 baris, ekspor tidak.** Laporan besar tidak membuat halaman berat, tetapi data lengkap tetap bisa diambil.
3. **Tipe berkas dibaca dari isi**, bukan dari nama atau header klien. Hanya `image/jpeg`, `image/png`, `image/webp`; berkas lama dengan ekstensi lain dibuang saat diganti. Controller juga memvalidasi `image|mimes:jpg,jpeg,png,webp|max:5120`.
4. **Disk `local` sudah dipisah per company** oleh `FilesystemTenancyBootstrapper` (`config/tenancy.php`), sesuai [A-68](04-keputusan-dan-asumsi.md#a-68); berkas tidak pernah ada di `public/`.

### 13.3 Layar yang sudah ada

| Layar | Route | Komponen | Isi |
|---|---|---|---|
| Daftar laporan | `/reports` | view `shared.reports.index` | Kartu laporan yang boleh dibuka |
| Satu laporan | `/reports/{report}` | Livewire `ReportViewer` | Penyaring, tabel ≤ 500 baris, tombol ekspor |
| Ekspor | `/reports/{report}/export` | `ReportController@export` | Unduhan `.xlsx` |

Uji: `tests/Feature/Shared/ReportTest.php` (TC-RPT-01, 01b–01f) dan `tests/Feature/Shared/FileUploadTest.php` (TC-FIL-01, 01b–01g), `tests/Feature/Shared/TenantAssetUrlTest.php` (TC-FIL-02, 02b).

**Aset aplikasi vs berkas company (24 Sep 2026).** `config/tenancy.php` `asset_helper_tenancy` dimatikan. Saat aktif, `asset()` dan `@vite` di halaman company menunjuk `/tenancy/assets/…`, yang dilayani dari storage company. Akibatnya build Vite dan logo 404 dan seluruh halaman company tampil tanpa gaya. Aset aplikasi bersifat global (`public/`). Berkas milik company tidak pernah lewat `asset()`: berkas itu disajikan route berotorisasi `/files/…` ([A-68](04-keputusan-dan-asumsi.md#a-68)), jadi tidak ada yang hilang.

### 13.4 Sisa pekerjaan

Laporan §9 berikut **belum** didefinisikan di `ReportRegistry`:

| Modul | Laporan (nama dari §9 spesifikasinya) |
|---|---|
| [13-stock §9](13-stock.md#9-laporan--dashboard) | Saldo stok · Kartu stok · Reservasi menggantung · Stok di bawah titik pesan ulang |
| [14-request §9](14-request.md#9-laporan--dashboard) | Daftar REQ · REQ menunggu tinjau · Baris tanpa sumber · Penggantian item menunggu tanggapan |
| [15-picking-shipment §9](15-picking-shipment.md#9-laporan--dashboard) | Daftar pengiriman · Short pick · Posisi barang rusak & selisih · Kinerja pengiriman |

*Material per proyek* ([23-pemakaian §9](23-pemakaian.md#9-laporan--dashboard)) terdaftar sejak modul Issue; kolom *Waste* dan *Rencana* `[F2]`-nya belum.

Juga belum: ekspor PDF (§13.1 no. 1), 404 untuk kunci tidak dikenal (§13.1 no. 3), dan penyaring §9 yang hilang (§13.1 no. 2). Prompt lanjutan: [prompts/16-shared](../prompts/16-shared.md).
