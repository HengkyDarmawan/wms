# Spesifikasi Modul — `warehouse` bagian Tata Letak Barang (Tempat Simpan)

**Versi:** 0.3
**Tanggal:** 1 Oktober 2026
**Status:** **dibangun (Tata letak gudang Bagian 3)** — Tempat Simpan per barang & gudang, *Khusus Barang Ini* (BR-WH-10) di put-away / pilah retur / penyesuaian (+) / saldo awal, Buka Tempat Khusus oleh Kepala Gudang, kartu di detail item, mode *Tata letak barang* di Denah, impor Excel, dan Cetak denah ([A-365](04b-asumsi-lanjutan.md#a-365)–[A-372](04b-asumsi-lanjutan.md#a-372)). Pecahan dari [12-warehouse](12-warehouse.md) (berkas itu mendekati batas 450 baris); v0.2: halaman **Isi Bin**, QR bin berisi tautan, label bin berkode pendek (Bagian 4, [A-373](04b-asumsi-lanjutan.md#a-373)–[A-379](04b-asumsi-lanjutan.md#a-379); §6.6–§6.7, TC-WH-61–62); v0.3: Bagian 5 — langkah setup awal *Atur tata letak barang*, saldo awal tanpa kode bin dari tempat simpan ([27](27-pendukung-f1.md) §13.3), kolom Lokasi di Saldo stok ([13](13-stock.md) §6) ([A-380](04b-asumsi-lanjutan.md#a-380)–[A-382](04b-asumsi-lanjutan.md#a-382))
**Modul:** `warehouse`
**Fase:** F1
**Dokumen terkait:** [12-warehouse](12-warehouse.md) · [Aturan Bisnis BR-WH](05-aturan-bisnis.md#br-wh) · [Glosarium](03-glosarium.md) · [Model data gudang](08b-model-data-stok-dokumen.md#area-gudang--lokasi-tenant) · [19-receipt-putaway](19-receipt-putaway.md) · [21-opname-penyesuaian](21-opname-penyesuaian.md) · [22-retur-transfer](22-retur-transfer.md)
**Ketergantungan modul:** `warehouse` (zona, rak, bin, Denah ringan [A-353](04b-asumsi-lanjutan.md#a-353), struktur rak nyata [A-359](04b-asumsi-lanjutan.md#a-359)–[A-364](04b-asumsi-lanjutan.md#a-364)), `master` (item)

---

## 1. Tujuan & lingkup

[F1] Menghubungkan **barang** ke **tempat** di gudang supaya saran bin tidak lagi menebak (A-84): Kepala Gudang menetapkan untuk tiap barang, per gudang, daftar **Tempat Simpan** berurutan — satu bin tertentu, seluruh rak, atau area lantai — dan boleh menandai tempat itu **Khusus Barang Ini** (barang lain ditolak saat ditaruh). Bagian 4 (put-away pindai & Isi Bin) dan Bagian 5 (setup awal) memakai saran dari sini.

Bagian 4 menambah halaman **Isi Bin** (§6.6) dan QR bin bertautan (§6.7); put-away pindai per baris ada di [19](19-receipt-putaway.md) §13.7, pilah retur di [22](22-retur-transfer.md) §13.6. Bagian 5: setup awal & impor saldo awal tanpa kode bin ([27](27-pendukung-f1.md) §13.3), kolom Lokasi di Saldo stok ([13](13-stock.md) §6).

## 2. Aktor & permission

| Aksi | Permission | Catatan |
|---|---|---|
| Lihat kartu Tempat simpan, Barang di sini, Cetak denah | `bin.view` / `warehouse.view` | Dibatasi cakupan gudang (BR-ACC-05) |
| Ubah tempat simpan (kartu item, mode Tata letak, impor) | `bin.manage` | Keputusan #5 — tanpa izin baru |
| Buka Tempat Khusus | `adjustment.approve` | Sama dengan membatalkan label (Kepala Gudang), keputusan #5 |

## 3. Entitas & data

### 3.1 `item_storage_locations` — Tempat Simpan

| Kolom | Tipe | Keterangan |
|---|---|---|
| `item_id` | FK items | |
| `warehouse_id` | FK warehouses | Cakupan gudang (`ScopedToUser`) |
| `bin_id` | FK bins, null | Terisi = **bin tertentu** |
| `rack_id` | FK racks, null | Terisi = **seluruh rak**; rak `is_area` = **area lantai** |
| `sequence` | smallint | Urutan saran (1 = pertama) |
| `is_dedicated` | bool | **Khusus Barang Ini** |
| `updated_by` | FK users, null | |

Unik (`item_id`, `bin_id`) dan (`item_id`, `rack_id`). Jenis dibaca dari kolom yang terisi — **tanpa enum baru**. Baris ini pengaturan: disimpan ulang seluruhnya per (barang, gudang) dengan jejak riwayat pada item ([A-365](04b-asumsi-lanjutan.md#a-365)).

### 3.2 `storage_dedication_overrides` — Buka Tempat Khusus

`bin_id`, `item_id`, `reason` (wajib), `document_type`/`document_id`/`document_number` (put-away, retur, ADJ), `opened_by`, `opened_at`. Hanya ditambah ([A-367](04b-asumsi-lanjutan.md#a-367)). Merujuk bin → ikut `BinUsage::REFERENSI`.

## 4. Mesin status

Tidak ada status baru.

## 5. Aturan bisnis yang berlaku

- **BR-WH-10** Khusus Barang Ini — ditegakkan satu layanan `Warehouse\Support\StoragePolicy` di put-away (`CompletePutaway`), pilah retur (`SortGoodsReturn`, bin penyimpanan), penyesuaian tambah & saldo awal (`AdjustmentLines::normalize`). Opname **tidak** (keputusan #2). Stok tetap hanya lewat StockLedger (P-01).
- Menyimpan tempat: bin penyimpanan aktif di rak (bukan sistem/virtual/tergabung), rak aktif, gudang dalam cakupan, barang aktif, tanpa ganda, maks. 50 per barang per gudang. Tempat yang seluruhnya di dalam tempat khusus barang lain ditolak; menandai Khusus ditolak bila barang lain sudah bertempat di dalamnya (BR-WH-10). Tumpang-tindih sebagian dibiarkan.
- **Saran bin** `StorageLocationPlanner::saranBin(item, gudang, qty)` (keputusan #3, #4, [A-372](04b-asumsi-lanjutan.md#a-372)): tempat menurut `sequence`; seluruh rak = bin berisi barang itu dulu, lalu bin kosong dari L1 ke atas, kiri ke kanan; bin berisi barang lain, nonaktif, beku, tergabung, atau khusus barang lain dilewati; tempat penuh → tempat berikutnya; semuanya penuh → aturan lama A-84 dengan tanda `penuh` (tidak ditolak). `PutawaySuggester::suggest` kini memakainya.
- Bin yang hanya dirujuk Tempat Simpan tetap boleh dihapus (BR-WH-09); tempatnya ikut dilepas ([A-368](04b-asumsi-lanjutan.md#a-368)).

## 6. Layar

### 6.1 Kartu *Tempat simpan* — detail item (`/items/{id}`, tab Ringkasan) — `Warehouse\Livewire\ItemStorageLocations`

Per gudang: nomor urut, kode pendek bin / "Rak D · R01 (seluruh rak)" / "Area D · AB1", badge **Khusus**. `bin.manage`: *Ubah* → daftar berurutan (naik/turun/lepas, centang Khusus), *Tambah tempat* (`<x-pilih>`: rak, area, bin), *Simpan tempat simpan*; galat aturan tampil di kartu. *Atur tempat di gudang…* untuk gudang yang belum punya.

### 6.2 Denah — mode *Tata letak barang* ([A-369](04b-asumsi-lanjutan.md#a-369))

Mode ketiga di samping Lihat dan Atur (juga di HP). Panel **Barang belum punya tempat**: cari (server, maks. 50 per permintaan), centang barang, *Khusus barang ini*, **Taruh di…** lalu klik rak (= seluruh rak), petak bin (bin tergabung → bin utama), atau area lantai; di versi daftar ketuk petak/rak. Panel rak: **Barang di sini** (juga di mode Lihat, hanya-baca) dengan *Lepas*, dan **Tambah barang…** (ke bin terpilih atau seluruh rak). Petak bertempat diberi titik biru (oranye = Khusus), rak bertanda ▣ kode barang. Semua perubahan diurungkan/disimpan lewat *Simpan perubahan* yang sama (op `tempat_barang`, `lepas_barang`).

### 6.3 *Cetak denah* — `/warehouses/{id}/layout/print` ([A-370](04b-asumsi-lanjutan.md#a-370))

Halaman cetak A4 mendatar: tampak atas (zona, rak, area, objek) dengan kode barang per rak, tabel *Barang per rak* (tempat, kode, nama, Khusus), dan **Tips tata letak** (saran statis). Tanpa harga (D-07).

### 6.4 Impor Excel *Tempat simpan barang* — `/imports` ([A-371](04b-asumsi-lanjutan.md#a-371))

Kolom `Kode item | Kode gudang | Tempat | Khusus`. Tempat = kode bin lengkap, kode pendek ("R01 · L2 · 01", berawalan zona bila rak kembar), kode rak, atau kode area. Galat per baris, satu salah = tidak ada yang tersimpan; templat diunduh.

### 6.5 Buka Tempat Khusus (put-away, pilah retur, form penyesuaian)

Bagi pemegang `adjustment.approve`: lipatan *Buka tempat khusus (Kepala Gudang)* berisi satu alasan untuk layar itu; dicatat per bin yang benar-benar dibuka ([A-367](04b-asumsi-lanjutan.md#a-367)).

### 6.6 Isi Bin — `/bins/{kode}` (`bins.show`, [A-374](04b-asumsi-lanjutan.md#a-374))

Tujuan QR label bin. `bin.view` + cakupan gudang (di luar cakupan = tidak ditemukan). Kode pendek besar, kode lengkap, jenis/status, badge **Khusus** pemiliknya; isi saldo > 0: item (→ Kartu stok bila `stock.view`), jumlah + uraian kemasan, lot + kedaluwarsa / serial / potongan, kondisi, masuk terakhir; total vs kapasitas; tempat simpan di bin/rak itu; label kemasan di bin; bin gabungan (bin tergabung menampilkan isi bin utama). Tombol Denah dan Cetak label bin. Tanpa harga; `Support\BinContents` memuat sekaligus.

### 6.7 QR bin & pemindai ([A-373](04b-asumsi-lanjutan.md#a-373), [A-379](04b-asumsi-lanjutan.md#a-379))

QR label bin = tautan `…/bins/<kode lengkap>`; judul label = kode pendek. `BinCode::dariPindai` membaca tautan itu di semua kotak pindai bin (Menunggu dimasukkan, detail PUT, pilah retur, PCK); `BinCode::cocokkan` juga menerima kode pendek ketikan bila unik.

## 7. Kejadian stok & integrasi

Tidak ada kejadian stok baru; tempat simpan tidak menyentuh kartu stok.

## 8. Notifikasi

Tidak ada.

## 9. Laporan & dashboard

Cetak denah (§6.3). Laporan tempat simpan tersendiri: belum.

## 10. Kasus uji (Given / When / Then)

| ID | Given / When / Then |
|---|---|
| TC-WH-50 | Simpan 3 tempat (rak, bin khusus, area) → jenis & urutan benar; ubah urutan tersimpan & tercatat di riwayat; bin area = area; ganda/tempat asing/bin tanpa rak ditolak |
| TC-WH-51 | Pengguna gudang lain tidak bisa menyimpan (BR-GEN-09) dan tidak melihat baris tempat simpan |
| TC-WH-52 | Seluruh rak: bin kosong L1 kiri→kanan; bin berisi barang itu dulu, berisi barang lain dilewati; tempat penuh → aturan lama bertanda `penuh`; put-away memakai saran tempat simpan |
| TC-WH-53 | Bin khusus BAUT: ADJ (+) barang lain & saldo awal ditolak BR-WH-10; rak area khusus menolak juga; ADJ asal opname tetap diposting |
| TC-WH-54 | Put-away ke bin khusus barang lain ditolak; staf beralasan tetap ditolak; Kepala Gudang beralasan → stok masuk, catatan buka (bin, item, alasan, dokumen, pembuka) |
| TC-WH-54b | Pilah retur ke bin khusus barang lain ditolak; Kepala Gudang beralasan → dipilah, catatan bernomor RET |
| TC-WH-55 | Menandai Khusus ditolak bila barang lain sudah bertempat di bin/rak itu; tempat di dalam tempat khusus barang lain ditolak |
| TC-WH-46c | Bin yang hanya dirujuk tempat simpan tetap bisa dihapus; tempatnya dilepas |
| TC-WH-56 | Op `tempat_barang`/`lepas_barang` lewat Simpan perubahan; muatan denah & isi rak membawa barang; satu op salah = tidak ada yang tersimpan |
| TC-WH-57 | `daftarBarang`: hanya barang tanpa tempat, cari, maks. 50 + tanda lebih; pengguna tanpa `bin.manage` 403 |
| TC-WH-58 | Impor: templat, galat per baris semua-atau-tidak, format tempat (pendek, lengkap, rak, area, berzona), Khusus Ya/Tidak |
| TC-WH-59 | Kartu detail item: tambah gudang, tambah tempat, urut, Khusus, simpan; galat aturan di kartu; gudang di luar cakupan tidak terlihat |
| TC-WH-60 | Cetak denah: kode & nama barang, kode pendek, Khusus, tips; tanpa "Rp"; gudang di luar cakupan tidak ditemukan |
| TC-WH-61 | Bin khusus berisi 30 baut (DUS = 12): `/bins/<kode>` (juga huruf kecil) → kode pendek, Khusus, "30 PCS", "2 DUS 6 PCS", masuk terakhir, tanpa "Rp"; driver 403; staf gudang lain & kode asing 404; setelah gabung permanen ke B02, bin tergabung → "digabung ke bin utama" + isi bin utama |
| TC-WH-62 | Tautan bin `…/bins/CKG-D-R01-L1-B03`; `dariPindai` membaca tautan & huruf kecil; `cocokkan` menerima tautan dan kode pendek, kode pendek kembar = tidak ada; label bin memuat kode pendek & kode lengkap |

## 11. Di luar lingkup modul ini

Laporan tempat simpan, PDF denah.

## 12. Definisi selesai

Migrasi tenant `000510`; aksi `SaveItemStorageLocations`, `ImportItemStorageLocations`; `StoragePolicy`, `StorageLocationPlanner`, `FloorPlanPrintData`; uji `TempatSimpanTest`, `TempatSimpanReturTest`, `TempatSimpanLayarTest` hijau; Bagian 4: `BinController::show`, `BinContents`, `BinCode::tautan/dariPindai/cocokkan`, uji `IsiBinTest`; uji penuh hijau.
