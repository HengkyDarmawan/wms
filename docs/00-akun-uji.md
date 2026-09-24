# Akun Uji & Data Demo (dev / demo / staging)

**Versi:** 1.6
**Tanggal:** 24 September 2026
**Status:** aktif — **hanya untuk dev, demo, dan staging**. Produksi tidak memakai berkas ini: Super Admin produksi dibuat dari `.env` saat instalasi, semua user lain lewat undangan dan mengatur password sendiri ([Blueprint §13](wms/01-blueprint.md#13-autentikasi--sso), [NFR-02](wms/01-blueprint.md#16-kebutuhan-non-fungsional)).
**Dokumen terkait:** [Blueprint §4](wms/01-blueprint.md#4-pengguna--peran) · [BR-GEN-09](wms/05-aturan-bisnis.md#br-gen) · [Model data pusat & akses](wms/08a-model-data-inti.md#area-user-role-cakupan-struktur-organisasi-tenant) · [Spesifikasi modul Access](wms/10-access.md)

Berkas ini adalah **satu-satunya sumber** daftar akun seed. Seeder `DemoSeeder` (modul Access) harus sama persis dengan tabel di bawah; bila akun berubah, ubah berkas ini dulu lalu seeder-nya. Password di sini tidak boleh dipakai di produksi.

---

## 1. Platform (database pusat)

| Email | Nama | Peran | Password dev | Catatan |
|---|---|---|---|---|
| `superadmin@wms.test` | Super Admin Platform | Super Admin ([Blueprint §4.1](wms/01-blueprint.md#41-level-platform-database-pusat)) | `Wms#2026!Admin` | Membuat company, verifikasi bayar; tidak bisa membuka data company tanpa akses dukungan ([A-27](wms/04-keputusan-dan-asumsi.md#a-27)) |

## 2. Company demo

| Atribut | Nilai |
|---|---|
| Kode / nama | `DEMO` / PT Demo Konstruksi |
| Subdomain | `demo.wms.test` (Laragon auto virtual host; di XAMPP `demo.wms.test:8000` lewat berkas hosts, lihat [00-setup-lokal](00-setup-lokal.md)); portal klien `demo.wms.test/portal` |
| Zona waktu · paket · langganan | Asia/Jakarta · `standard` · trial 14 hari ([A-11](wms/04-keputusan-dan-asumsi.md#a-11)) |
| Gudang | `CKG` Gudang Utama Cakung · `BKS` Gudang Cabang Bekasi (induk: CKG) · `KRW1` Gudang Site "Pipa Karawang STA 0+000" · `KRW2` Gudang Site "Pipa Karawang STA 2+500" (keduanya milik proyek `PRJ-001`, [A-40](wms/04-keputusan-dan-asumsi.md#a-40)) |
| Zona & bin demo | Tiap gudang mendapat enam bin bawaan otomatis ([BR-WH-02](wms/05-aturan-bisnis.md#br-wh)); CKG punya zona A & B, gudang lain satu zona, masing-masing satu rak dua level dengan dua bin per level (45 bin termasuk bin bawaan). Satu bin On-site untuk `PRJ-001` ([BR-WH-03](wms/05-aturan-bisnis.md#br-wh)) |
| Proyek | `PRJ-001` Pipa Karawang (klien PT Klien Satu, aktif) · `PRJ-INT` Proyek Internal ([A-06](wms/04-keputusan-dan-asumsi.md#a-06)) · `PRJ-002` Gudang Cikarang milik CV Klien Dua (sengaja masih kosong) |
| Klien | PT Klien Satu (`KL1`) · CV Klien Dua (`KL2`) |
| Vendor | PT Baja Prima (`company`, vendor tetap pipa) · Toko Besi Jaya (`shop`) · Tokopedia Toko Alat (`online_marketplace`) |
| Kategori item | `MATERIAL` Material · `PIPA` Pipa (anak Material) · `FASTENER` Baut & mur (anak Material) · `ALAT` Alat kerja |
| Item contoh | `PIPA-PVC-4` Pipa PVC 4 inci (per potong, bisa dipotong) · `BAUT-M12` Baut M12 (tanpa pelacakan) · `SEMEN-PCC-50` Semen PCC (lot, kedaluwarsa, FEFO) · `GENSET-5KVA` Genset 5 kVA (serial, aset) — satu item per mode pelacakan |
| Ekspedisi | JNE Trucking · Indah Cargo |
| Kendaraan | `B 9001 XX` (driver bawaan Gani) · `B 9002 XX` (driver bawaan Hadi), jenis Pikap ([A-57](wms/04-keputusan-dan-asumsi.md#a-57)) |
| Stok awal | Lewat `StockDemoSeeder` ([A-72](wms/04-keputusan-dan-asumsi.md#a-72)), tercatat di kartu stok dengan catatan *Stok awal demo (seeder dev)*: `BAUT-M12` 1.000 di `CKG-A-R01-L1-B01` dan 300 di `BKS-A-R01-L1-B01` · `SEMEN-PCC-50` lot `LOT-SMN-2609` 2.000 kg (kedaluwarsa +6 bulan) dan `LOT-SMN-2610` 1.500 kg di `CKG-A-R01-L1-B02`, 500 kg di `BKS-A-R01-L1-B02` · `PIPA-PVC-4` 10 batang 6 m + sisa 2,5 m dan 1,2 m di `CKG-B-R01-L1-B01`, 4 batang di `BKS-A-R01-L2-B01` · `GENSET-5KVA` serial `GNS-5K-0001`, `GNS-5K-0002` di `CKG-B-R01-L2-B01`. Gudang Site tanpa stok |

## 3. Akun tenant (`demo.wms.test`)

Password dev semua akun: **`Demo#2026!`**. Nomor WA dummy dipakai untuk uji notifikasi Fase 2a. Cakupan = penugasan role × scope ([BR-GEN-09](wms/05-aturan-bisnis.md#br-gen)).

| Email | Nama | Role bawaan | Cakupan | Catatan |
|---|---|---|---|---|
| `admin@demo.wms.test` | Rina Admin | Admin Company | semua | Pemberi akses dukungan; Admin Company terakhir tidak bisa dinonaktifkan ([BR-ACC-02](wms/05-aturan-bisnis.md#br-acc)) |
| `manajemen@demo.wms.test` | Budi Direktur | Manajemen | semua | Approver tingkat atas; atasan Kepala Gudang |
| `kagudang.ckg@demo.wms.test` | Andi Kepala | Kepala Gudang **dan** Staf Gudang | Kepala Gudang: gudang CKG · Staf Gudang: gudang BKS | Contoh multi-role beda gudang ([A-04](wms/04-keputusan-dan-asumsi.md#a-04)) |
| `kagudang.bks@demo.wms.test` | Sari Kepala | Kepala Gudang | gudang BKS | Approver sesi opname BKS |
| `staf1.ckg@demo.wms.test` | Dedi Staf | Staf Gudang | gudang CKG | Penghitung pertama (opname) |
| `staf2.ckg@demo.wms.test` | Eko Staf | Staf Gudang | gudang CKG | Penghitung ulang, orang berbeda ([BR-OPN-05](wms/05-aturan-bisnis.md#br-opn)) |
| `staf.krw@demo.wms.test` | Fajar Site | Staf Gudang | gudang KRW1, KRW2 | PIC titik site; menerima transfer dalam proyek ([A-50](wms/04-keputusan-dan-asumsi.md#a-50)) |
| `driver1@demo.wms.test` | Gani Driver | Driver | semua | Kendaraan B 9001 XX |
| `driver2@demo.wms.test` | Hadi Driver | Driver | semua | Kendaraan B 9002 XX |
| `pemohon.prj001@demo.wms.test` | Indra Engineer | Pemohon Internal | proyek PRJ-001 | Juga PIC proyek PRJ-001; atasan = Budi Direktur |
| `pr@demo.wms.test` | Joko Purchasing | Penindak Lanjut PR | semua | Catatan pemesanan, vendor sementara ([A-51](wms/04-keputusan-dan-asumsi.md#a-51), [A-53](wms/04-keputusan-dan-asumsi.md#a-53)) |
| `auditor@demo.wms.test` | Kartika Auditor | Auditor Internal | semua | Read-only mutasi; boleh sesi opname & pemeriksaan mendadak ([BR-OPN-08](wms/05-aturan-bisnis.md#br-opn), [BR-OPN-10](wms/05-aturan-bisnis.md#br-opn)) |
| `klien1@klien-satu.test` | Lina (PT Klien Satu) | Klien | klien KL1, proyek PRJ-001 | Login di `/portal`; role Klien tidak digabung role internal ([BR-ACC-03](wms/05-aturan-bisnis.md#br-acc)) |
| `klien2@klien-dua.test` | Maman (CV Klien Dua) | Klien | klien KL2, proyek PRJ-002 | Proyeknya masih kosong: portal menampilkan daftar kosong, tidak melihat proyek klien lain ([A-21](wms/04-keputusan-dan-asumsi.md#a-21)) |

Nomor WA dummy: `+6281200000001` … `+6281200000014` berurutan sesuai tabel.

> **Cakupan klien pada dua akun portal ditulis sebagai "klien KL1/KL2", tetapi `scope_type` hanya mengenal `all`, `warehouse`, dan `project`.** Keterkaitan klien diwakili kolom `users.client_id`, bukan cakupan role; seeder mengikuti kode, bukan kalimat di tabel.

## 4. Struktur organisasi demo

| Unit | Jabatan (level) | Pemegang | Atasan langsung |
|---|---|---|---|
| Direksi | Direktur (1) | Budi Direktur, Rina Admin | — |
| Operasional | Kepala Gudang (2) | Andi Kepala, Sari Kepala | Budi Direktur |
| Operasional | Staf Gudang (3) | Dedi, Eko, Fajar | Kepala gudang masing-masing (Fajar → Andi) |
| Operasional | Driver (3) | Gani, Hadi | Andi Kepala |
| Proyek | Engineer / PIC (2) | Indra Engineer | Budi Direktur |
| Keuangan | Purchasing (2) | Joko Purchasing | Budi Direktur |
| Audit | Auditor Internal (2) | Kartika Auditor | Budi Direktur |

`manager_id` diisi sesuai kolom terakhir agar approver "atasan langsung pemohon" dan SoD ([BR-APR-03](wms/05-aturan-bisnis.md#br-apr)) bisa diuji.

## 5. Aturan approval bawaan demo

> **Sudah diseed** (sejak 24 Sep 2026) oleh `ApprovalDemoSeeder`, dipanggil `DemoSeeder`, untuk jenis dokumen yang sudah tersambung ke mesin approval ([20-approval](wms/20-approval.md)): REQ, RTV, dan — sejak modul Count/Adjustment ([21-opname-penyesuaian](wms/21-opname-penyesuaian.md)) — ADJ dan OPN (enam aturan). Baris PRQ masih rencana dan diseed bersama modulnya. TRF dan RET sudah tersambung ([22-retur-transfer](wms/22-retur-transfer.md)) tetapi **tidak punya aturan demo**: tanpa aturan keduanya disetujui otomatis ([A-08](wms/04-keputusan-dan-asumsi.md#a-08)), yang juga jalur ringan transfer dalam proyek antar KRW1 dan KRW2 ([A-50](wms/04-keputusan-dan-asumsi.md#a-50)). Ubah aturan dari layar *Aturan approval* (Admin Company); coba dulu di *Simulasi approval*.

| Dokumen | Aturan (prioritas) | Kondisi | Lapis | Diseed | Rujukan |
|---|---|---|---|---|---|
| REQ | REQ besar atau aset (10) | **salah satu**: ≥ 21 baris, **atau** barang berkepemilikan aset ("kategori Aset", [A-87](wms/04-keputusan-dan-asumsi.md#a-87)) | 1. Kepala gudang sumber (semua harus setuju) → 2. Manajemen | ✔ | [BR-APR-07](wms/05-aturan-bisnis.md#br-apr) |
| REQ | REQ lainnya (100) | — | 1. Kepala gudang sumber | ✔ | [A-08](wms/04-keputusan-dan-asumsi.md#a-08) |
| RTV | RTV — kepala gudang (100) | — | 1. Kepala gudang (cukup salah satu) | ✔ | [A-93](wms/04-keputusan-dan-asumsi.md#a-93) |
| ADJ manual | ADJ di atas 100 unit (10) | jumlah satuan dasar per baris > 100 (`line_qty_min` 100,0001, [A-105](wms/04-keputusan-dan-asumsi.md#a-105)) | 1. Kepala gudang (cukup salah satu) → 2. Manajemen | ✔ | [A-09](wms/04-keputusan-dan-asumsi.md#a-09) |
| ADJ manual | ADJ lainnya (100) | — | 1. Kepala gudang (cukup salah satu). Tanpa aturan pun tetap satu lapis Kepala Gudang (lapis minimum) | ✔ | [A-09](wms/04-keputusan-dan-asumsi.md#a-09) |
| PRQ | — | jenis vendor `online_marketplace` | 1. Kepala gudang tujuan → 2. Manajemen | menunggu modul PRQ | [A-52](wms/04-keputusan-dan-asumsi.md#a-52) |
| OPN | OPN tahunan & pemeriksaan mendadak (10) | jenis `annual` / `spot_check` | 1. Auditor Internal (cukup salah satu) | ✔ | [BR-OPN-09](wms/05-aturan-bisnis.md#br-opn) |
| OPN | — (tanpa aturan) | jenis `monthly` / `adhoc` | lapis minimum: Kepala gudang cakupan; sesi audit (dibuat Auditor) ke Auditor Internal; cadangan Manajemen | bawaan kode | [A-96](wms/04-keputusan-dan-asumsi.md#a-96) |
| ISU pembalik | — (tanpa aturan) | pembalikan pemakaian material ([BR-GEN-04](wms/05-aturan-bisnis.md#br-gen)) | lapis minimum: Kepala gudang Gudang Site; tanpa Kepala Gudang site → cadangan Manajemen (di demo: Budi Direktur) | bawaan kode | [A-150](wms/04-keputusan-dan-asumsi.md#a-150) |

Contoh uji: Indra (pemohon PRJ-001) mengajukan REQ Genset dari CKG → tugas ke Andi (Kepala Gudang CKG) lalu Budi (Manajemen); REQ Baut dari CKG → Andi saja. Sari (Kepala Gudang BKS) tidak mendapat tugas REQ dari CKG. Opname: Andi membuat sesi tahunan CKG dengan tim Dedi & Eko → selisih sedang dihitung ulang oleh orang lain → Andi merekonsiliasi → tugas ke Kartika (Auditor Internal); Andi, Dedi, dan Sari tidak bisa menyetujui (BR-OPN-09). ADJ: Dedi mengajukan −150 Baut CKG → Andi lalu Budi; +20 → Andi saja.

## 6. Cara pakai

Langkah lengkap dari klon bersih, termasuk berkas hosts dan profil XAMPP: [00-setup-lokal](00-setup-lokal.md). `php` = PHP 8.3.33 (`php83` di Laragon, lihat [CLAUDE.md](../CLAUDE.md)).

```
# Super Admin §1 + paket langganan (aman di produksi)
php artisan db:seed --class="Database\Seeders\ProductionSeeder"

# Company demo §2 di database pusat (menolak berjalan di produksi)
php artisan db:seed --class="Database\Seeders\PlatformDemoSeeder"

# Akun §3, organisasi §4, master, gudang, stok awal, kendaraan §2, aturan approval §5 di database tenant.
# --tenants menerima id company (DEMO = 1 pada instalasi bersih), bukan kodenya.
php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder"
```

Seeder produksi (`ProductionSeeder`) hanya membuat paket langganan dan Super Admin dari `.env` di database pusat; tidak pernah menjalankan `DemoSeeder`. Data acuan tenant (permission, role bawaan, satuan, alasan, tipe gudang) **belum** dibuat otomatis saat company lahir: `TenancyServiceProvider` hanya membuat dan memigrasi database tenant. Sampai aksi `CreateTenant` ada ([17-platform-login §13](wms/17-platform-login.md)), company non-demo perlu `php artisan tenants:seed --tenants=<id>` (tanpa `--class`, menjalankan `TenantDatabaseSeeder`).
