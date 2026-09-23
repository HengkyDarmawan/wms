# Akun Uji & Data Demo (dev / demo / staging)

**Versi:** 1.1
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
| Subdomain | `demo.wms.test` (Laragon auto virtual host); portal klien `demo.wms.test/portal` |
| Zona waktu · paket · langganan | Asia/Jakarta · `standard` · trial 14 hari ([A-11](wms/04-keputusan-dan-asumsi.md#a-11)) |
| Gudang | `CKG` Gudang Utama Cakung · `BKS` Gudang Cabang Bekasi (induk: CKG) · `KRW1` Gudang Site "Pipa Karawang STA 0+000" · `KRW2` Gudang Site "Pipa Karawang STA 2+500" (keduanya milik proyek `PRJ-001`, [A-40](wms/04-keputusan-dan-asumsi.md#a-40)) |
| Zona & bin demo | Tiap gudang mendapat enam bin bawaan otomatis ([BR-WH-02](wms/05-aturan-bisnis.md#br-wh)); CKG punya zona A & B, gudang lain satu zona, masing-masing satu rak dua level dan dua bin. Satu bin On-site untuk `PRJ-001` ([BR-WH-03](wms/05-aturan-bisnis.md#br-wh)) |
| Proyek | `PRJ-001` Pipa Karawang (klien PT Klien Satu, aktif) · `PRJ-INT` Proyek Internal ([A-06](wms/04-keputusan-dan-asumsi.md#a-06)) · `PRJ-002` milik CV Klien Dua (sengaja masih kosong) |
| Klien | PT Klien Satu (`KL1`) · CV Klien Dua (`KL2`) |
| Vendor | PT Baja Prima (`company`, vendor tetap pipa) · Toko Besi Jaya (`shop`) · Tokopedia Toko Alat (`online_marketplace`) |
| Kategori item | `MATERIAL` Material · `PIPA` Pipa (anak Material) · `FASTENER` Baut & mur (anak Material) · `ALAT` Alat kerja |
| Item contoh | `PIPA-PVC-4` Pipa PVC 4 inci (per potong, bisa dipotong) · `BAUT-M12` Baut M12 (tanpa pelacakan) · `SEMEN-PCC-50` Semen PCC (lot, kedaluwarsa, FEFO) · `GENSET-5KVA` Genset 5 kVA (serial, aset) — satu item per mode pelacakan |
| Ekspedisi | JNE Trucking · Indah Cargo |

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
| Direksi | Direktur (1) | Budi Direktur | — |
| Operasional | Kepala Gudang (2) | Andi Kepala, Sari Kepala | Budi Direktur |
| Operasional | Staf Gudang (3) | Dedi, Eko, Fajar | Kepala gudang masing-masing (Fajar → Andi) |
| Operasional | Driver (3) | Gani, Hadi | Andi Kepala |
| Proyek | Engineer / PIC (2) | Indra Engineer | Budi Direktur |
| Keuangan | Purchasing (2) | Joko Purchasing | Budi Direktur |
| Audit | Auditor Internal (2) | Kartika Auditor | Budi Direktur |

`manager_id` diisi sesuai kolom terakhir agar approver "atasan langsung pemohon" dan SoD ([BR-APR-03](wms/05-aturan-bisnis.md#br-apr)) bisa diuji.

## 5. Aturan approval bawaan demo

> **Belum diseed.** Tabel di bawah adalah rencana; aturan approval baru lahir di modul `approval`. `DemoSeeder` saat ini **tidak** membuatnya. Diperbarui setelah modul itu dibangun.

| Dokumen | Kondisi | Lapis | Rujukan |
|---|---|---|---|
| REQ | > 20 baris **atau** kategori "Aset" | 1. Kepala gudang sumber → 2. Manajemen | [BR-APR-07](wms/05-aturan-bisnis.md#br-apr) |
| REQ | lainnya | 1. Kepala gudang sumber | [A-08](wms/04-keputusan-dan-asumsi.md#a-08) |
| ADJ manual | selalu | 1. Kepala gudang → 2. Manajemen bila > 100 unit | [A-09](wms/04-keputusan-dan-asumsi.md#a-09) |
| PRQ | jenis vendor `online_marketplace` | 1. Kepala gudang tujuan → 2. Manajemen | [A-52](wms/04-keputusan-dan-asumsi.md#a-52) |
| OPN | jenis `annual` / `spot_check` | 1. Auditor Internal | [BR-OPN-09](wms/05-aturan-bisnis.md#br-opn) |

## 6. Cara pakai

```
# Super Admin §1 + paket langganan (aman di produksi)
php83 artisan db:seed --class="Database\Seeders\ProductionSeeder"

# Company demo §2 di database pusat (menolak berjalan di produksi)
php83 artisan db:seed --class="Database\Seeders\PlatformDemoSeeder"

# Akun §3, struktur organisasi §4, master §2, gudang §2 di database tenant
php83 artisan tenants:seed --tenants=demo --class="Database\Seeders\Tenant\DemoSeeder"
```

`php83` = `C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe` (lihat [CLAUDE.md](../CLAUDE.md)). Seeder produksi (`ProductionSeeder`) hanya membuat Super Admin dari `.env` dan data referensi (role bawaan, satuan, tipe gudang, alasan, template); tidak pernah menjalankan `DemoSeeder`.
