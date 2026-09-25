# Setup Lokal — menjalankan dan mengetes aplikasi

**Versi:** 1.5
**Tanggal:** 24 September 2026
**Status:** aktif — dua profil mesin dev: **rumah** (XAMPP3 + MariaDB 10.4) dan **kantor** (XAMPP + MariaDB 10.4), keduanya [A-76](wms/04-keputusan-dan-asumsi.md#a-76) / [A-162](wms/04-keputusan-dan-asumsi.md#a-162); v1.5: mesin rumah pindah dari Laragon ke `C:\xampp3`
**Dokumen terkait:** [Akun uji](00-akun-uji.md) · [Arsitektur §10](wms/08-arsitektur.md#10-lingkungan) · [README](README.md) · [`../CLAUDE.md`](../CLAUDE.md)

Panduan dari klon bersih sampai bisa login dan mencoba alur REQ → PCK → SJ di browser. Semua akun dan password berasal dari [00-akun-uji](00-akun-uji.md). Semua database berprefiks `wms_`; MySQL/MariaDB lokal dipakai bersama proyek lain, jadi **jangan menyentuh database lain**.

---

## 1. Profil mesin

| Hal | Rumah (XAMPP3) | Kantor (XAMPP) |
|---|---|---|
| Repo | `C:\xampp3\htdocs\wms` | `C:\xampp\htdocs\wms` |
| PHP 8.3.33 | `C:\xampp3\php\php.exe`, sudah `php` di PATH | `C:\xampp\php-8.3.33\php.exe`, sudah paling depan di PATH jadi `php` = 8.3.33. `C:\xampp\php\php.exe` = 7.4, **jangan dipakai** |
| Database | MariaDB 10.4.32 bawaan XAMPP3, `127.0.0.1:3306`, `root` tanpa password | MariaDB 10.4.27 bawaan XAMPP, `127.0.0.1:3306`, `root` tanpa password |
| Web | `php artisan serve` port 8000 (Apache XAMPP3 memegang port 80, tidak diubah) | `php artisan serve` port 8000 (Apache XAMPP tidak diubah) |
| Node · Composer | Node 22 · Composer 2.8 | Node 24 · Composer 2.9 |
| Redis | tidak ada | tidak ada |

Di bawah, `php` berarti PHP 8.3.33 sesuai profil. Laragon (MySQL 8.4, klon lama `C:\laragon\www\wms`) tidak dipakai lagi; MySQL 8.4 tetap standar produksi ([08 §10](wms/08-arsitektur.md#10-lingkungan)).

## 2. Instalasi pertama

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Isi `.env`:

| Kunci | Nilai | Catatan |
|---|---|---|
| `APP_URL` | `http://wms.test:8000` (kedua profil) | |
| `DB_PASSWORD` | sesuai server | kosong di XAMPP |
| `CACHE_STORE` | `array` (atau `redis` bila ada) | **Jangan `database`/`file`:** stancl/tenancy memisahkan cache per company dengan *tag*, dan penyimpan tanpa tag membuat setiap halaman tenant galat *This cache store does not support tagging*. Produksi memakai Redis ([AD-11](wms/08-arsitektur.md#2-keputusan-arsitektur)) |
| `PLATFORM_ADMIN_PASSWORD` | `Wms#2026!Admin` | hanya dev ([00-akun-uji §1](00-akun-uji.md#1-platform-database-pusat)) |

Berkas hosts `C:\Windows\System32\drivers\etc\hosts` (buka Notepad **sebagai Administrator**), perlu di kedua profil:

```
127.0.0.1  wms.test
127.0.0.1  demo.wms.test
127.0.0.1  auth.wms.test
```

Berkas hosts tidak mengenal wildcard: company baru dengan kode lain butuh satu baris lagi (`<kode>.wms.test`).

## 3. Database dan data demo

```bash
# Buat database pusat (sekali). Database tenant dibuat otomatis oleh stancl/tenancy.
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS wms_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate                                                  # pusat
php artisan db:seed --class="Database\Seeders\ProductionSeeder"      # paket + Super Admin
php artisan db:seed --class="Database\Seeders\PlatformDemoSeeder"    # company DEMO + wms_tenant_demo (sudah termigrasi)
php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder"
```

- `--tenants=` menerima **id** company (`companies.id`), bukan kodenya. Company DEMO ber-id 1 pada instalasi bersih; `--tenants=demo` gagal. Tanpa opsi `--tenants`, seeder berjalan di semua company.
- `DemoSeeder` memanggil seeder acuan, master, gudang, akun, organisasi, lalu `StockDemoSeeder` (stok awal dan kendaraan, [A-72](wms/04-keputusan-dan-asumsi.md#a-72)). Semua aman dijalankan ulang; stok tidak berlipat.
- Mulai dari nol: `php artisan migrate:fresh` hanya mengosongkan pusat. Hapus juga `wms_tenant_demo` secara manual sebelum `PlatformDemoSeeder` dijalankan lagi.
- `mysql` ada di `C:\xampp3\mysql\bin\mysql.exe` (rumah) atau `C:\xampp\mysql\bin\mysql.exe` (kantor).

## 4. Menjalankan

| Profil | Perintah | Alamat |
|---|---|---|
| Rumah & kantor | `php artisan serve --host=127.0.0.1 --port=8000` | `http://wms.test:8000` · `http://demo.wms.test:8000` |

Identifikasi company memakai host tanpa port, jadi `:8000` tidak mengganggu. Sesi melekat pada host, jadi login di `demo.wms.test` tidak berlaku di `wms.test`.

| Halaman | Alamat | Akun |
|---|---|---|
| Super Admin | `wms.test:8000/admin/login` | `superadmin@wms.test` / `Wms#2026!Admin` |
| Back-office company | `demo.wms.test:8000/login` | akun [00-akun-uji §3](00-akun-uji.md#3-akun-tenant-demowmstest), password `Demo#2026!` |
| Portal klien | `demo.wms.test:8000/portal/login` | `klien1@klien-satu.test` / `Demo#2026!` |

Surel (undangan, reset password) masuk ke `storage/logs/laravel.log` karena `MAIL_MAILER=log`.

## 5. Skenario uji manual

| No | Akun | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 1 | `admin@demo.wms.test` | Buka **Stok** | Saldo BAUT-M12 1.000 di CKG dan 300 di BKS; SEMEN dua lot; PIPA per potong; dua serial GENSET |
| 2 | `pemohon.prj001@demo.wms.test` | **Permintaan baru**: proyek PRJ-001, baris 50 BAUT-M12, **Simpan & ajukan** | REQ bernomor `REQ/PRJ-001/…`, *Ditinjau* (gudang sumber belum dipilih) |
| 2b | `kagudang.ckg@demo.wms.test` | Buka REQ: gudang sumber CKG, cara *Stok tersedia*, **Kirim ke approval** | *Menunggu Approval* |
| 3 | `kagudang.ckg@demo.wms.test` | **Tugas approval saya** (atau detail REQ) → **Setujui** — aturan demo *REQ lainnya* menunjuk Kepala Gudang CKG ([00-akun-uji §5](00-akun-uji.md#5-aturan-approval-bawaan-demo)) | Reservasi lunak terbentuk; tersedia BAUT-M12 di CKG 950; panel *Riwayat approval* terisi |
| 3b | `admin@demo.wms.test` | **Simulasi approval**: nomor REQ langkah 2 | Aturan *REQ lainnya*, approver Andi Kepala ([BR-APR-11](wms/05-aturan-bisnis.md#br-apr)) |
| 4 | `kagudang.ckg@demo.wms.test` | **Tugas picking**: buat dari REQ, Mulai, catat 50, Selesaikan | PCK *Selesai*; 50 di bin `CKG-STG` |
| 5 | `kagudang.ckg@demo.wms.test` | **Surat jalan → Susun**: gudang CKG, pilih PCK, tujuan proyek PRJ-001, kendaraan sendiri B 9001 XX, driver Gani; lalu **Berangkatkan** | SJ *Dikirim*; 50 di `CKG-TRANSIT`
| 6 | `driver1@demo.wms.test` | Isi bukti terima (baik 48, kurang 2) | SJ *Diterima Sebagian*; DSC terbuka |
| 7 | `klien1@klien-satu.test` | Buka portal | Hanya proyek PRJ-001 yang tampil |
| 8 | `klien2@klien-dua.test` | Buka portal | Daftar kosong ([A-21](wms/04-keputusan-dan-asumsi.md#a-21)) |

Hasil terakhir dijalankan otomatis: [laporan progres](00-laporan-progres-2026-09-24.md) §2. Status dan nama layar mengikuti [Katalog Status](wms/06-katalog-status-dan-enum.md); bila layar berbeda dari tabel ini, spesifikasi modul ([14-request](wms/14-request.md), [15-picking-shipment](wms/15-picking-shipment.md)) yang berlaku.

## 6. Uji otomatis dan pemeriksa dokumen

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS wms_central_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test                    # 513 uji; database tenant uji wms_tenant_test_* dibuat & dihapus sendiri
py -3 docs/diagram/_verify.py       # link, anchor, ID, batas 450 baris
```

`phpunit.xml` memakai server MySQL/MariaDB sungguhan (`root@127.0.0.1`), tidak ada cadangan SQLite. Kalau uji berjalan di port lain, set `DB_PORT` di lingkungan shell.

## 7. Pemecahan masalah

| Gejala | Penyebab & jalan keluar |
|---|---|
| `This cache store does not support tagging` | `CACHE_STORE` bukan `array`/`redis`. Ubah `.env`, lalu `php artisan config:clear` |
| `Tenant could not be identified on domain …` | Baris hosts belum ada atau company belum dibuat |
| `could not be found` saat `tenants:seed --tenants=demo` | Pakai id company (`--tenants=1`) |
| Halaman tanpa gaya | `npm run build` belum dijalankan. Bila sumber halaman menunjuk `/tenancy/assets/build/…`, `asset_helper_tenancy` di `config/tenancy.php` aktif lagi; harus `false` ([16-shared §13](wms/16-shared-laporan-berkas.md)). Setelah memperbaiki, `php artisan config:clear` lalu `Ctrl+F5` |
| Sidebar tidak bisa di-scroll, ikon mata/tombol tema/⌘K tidak merespons | Buka Console browser (F12). Bundel lama: `npm run build` lalu `Ctrl+F5`. `ReferenceError: jQuery is not defined` berarti urutan impor di `resources/js/app.js` rusak — `globals.js` wajib diimpor paling awal |
| Migrasi gagal di MariaDB karena fitur khusus MySQL 8 | Kode tidak boleh diubah untuk MariaDB ([A-76](wms/04-keputusan-dan-asumsi.md#a-76)). Pasang MySQL 8.4 ZIP portable di port 3307 dan set `DB_PORT=3307` |
| `php -v` menunjukkan 7.4 atau 8.5 | PHP yang salah di PATH; pakai path lengkap dari §1 |
