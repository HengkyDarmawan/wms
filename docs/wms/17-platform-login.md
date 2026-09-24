# Spesifikasi Modul — `platform` (Fase 1 awal: Login Super Admin & Gerbang Langganan)

**Versi:** 0.1
**Tanggal:** 24 September 2026
**Status:** selesai Fase 1 sebagian — dokumen ini **mencatat kode yang sudah ada** (dibangun tanpa spesifikasi); modul Platform penuh tetap modul terakhir ([Arsitektur §12](08-arsitektur.md#12-langkah-berikutnya-part-4))
**Modul:** `platform` (`app/Domain/Platform`, `app/Http/Controllers/Platform`)
**Fase:** F1
**Dokumen terkait:** [Blueprint §4.1](01-blueprint.md#41-level-platform-database-pusat) · [Blueprint §14](01-blueprint.md#14-platform--langganan) · [Aturan Bisnis §BR-SUB](05-aturan-bisnis.md#br-sub) · [Arsitektur §3](08-arsitektur.md#3-tenancy--siklus-request) · [Model data pusat](08a-model-data-inti.md#area-database-pusat-platform) · [Katalog Status §3](06-katalog-status-dan-enum.md#3-enum-lain) · [Proses bisnis alur 10](07b-proses-bisnis-pendukung.md#alur-10--siklus-langganan-company-platform) · [Akun uji §1](../00-akun-uji.md#1-platform-database-pusat)
**Ketergantungan modul:** `access` (guard `platform` di `config/auth.php`, `LoginController` tenant yang memakai status langganan, rate limiter `login`).

---

## 1. Tujuan & lingkup

Guard `platform` dan tabel `platform_users` sudah ada sejak modul Access, tetapi tidak punya route masuk, sehingga akun Super Admin dari seeder tidak bisa dipakai. Potongan Fase 1 ini menutup lubang itu dan mencatat gerbang status langganan yang sudah melindungi setiap request tenant.

**Termasuk:** login/logout Super Admin di domain pusat; beranda Super Admin yang hanya **membaca** daftar company dan status langganannya; model & enum database pusat; middleware `EnsureSubscriptionState`; seeder pusat yang aman untuk produksi.

**Tidak termasuk (belum dibangun):** membuat company (`CreateTenant`), mengatur paket & trial, tagihan dan verifikasi pembayaran, perubahan status langganan terjadwal, akses dukungan dari sisi Super Admin, flag fitur. Lihat §13.3.

## 2. Aktor & permission

| Aktor | Guard | Hak di potongan ini |
|---|---|---|
| Super Admin | `platform` (tabel pusat `platform_users`) | masuk/keluar di domain pusat, melihat daftar company |
| User company | `web` (database tenant) | dibatasi `EnsureSubscriptionState` sesuai status langganan company |

Super Admin tidak memakai sistem permission `<modul>.<aksi>` tenant dan **tidak** bisa membuka data operasional company dari beranda ini ([BR-SUB-04](05-aturan-bisnis.md#br-sub), [A-27](04-keputusan-dan-asumsi.md#a-27)). `PlatformUser::hasActiveSupportAccess()` sudah ada, belum dipakai oleh route mana pun.

## 3. Entitas & data

Semua model berada di koneksi `central`. Kolom lengkap: [08a — Area database pusat](08a-model-data-inti.md#area-database-pusat-platform).

| Model | Tabel | Catatan kode |
|---|---|---|
| `Company` | `companies` | Turunan `Stancl\Tenancy` `BaseTenant` + `HasDatabase`; kolom nyata `id, code, name, subdomain, db_name, timezone, status, plan_id`, atribut lain di JSON `data`; relasi `plan`, `subscription` (terbaru, `latestOfMany`), `subscriptions`, `supportAccesses`, `featureFlags`; `host()`, `isFeatureEnabled()` |
| `Subscription` | `subscriptions` | `status` di-cast ke `SubscriptionStatus`; tanggal trial, periode, tenggang, penangguhan, pengakhiran, `purge_after`; `allowsWrite()`, `allowsRead()` |
| `Plan` | `plans` | Paket `standard` dibuat seeder; memiliki kolom `monthly_price` (harga langganan platform, bukan nilai barang WMS) |
| `PlatformUser` | `platform_users` | `Authenticatable`; `password` hashed, `last_login_at` |
| `SupportAccess` | `support_accesses` | Scope `active()`, `isActive()` |
| `FeatureFlag` | `feature_flags` | `enabled`, `config` (array) |

### 3.1 Enum

| Enum | Nilai (label) | Sumber |
|---|---|---|
| `CompanyStatus` | `provisioning` (Disiapkan) · `active` (Aktif) · `suspended` (Ditangguhkan) · `terminated` (Diakhiri) | kolom `companies.status` di [08a](08a-model-data-inti.md#area-database-pusat-platform); **bukan** status langganan |
| `SubscriptionStatus` | `trial` (Trial) · `active` (Aktif) · `past_due` (Jatuh Tempo) · `suspended` (Ditangguhkan) · `terminated` (Diakhiri) | [Katalog §3 `subscription_status`](06-katalog-status-dan-enum.md#3-enum-lain), [BR-SUB-01](05-aturan-bisnis.md#br-sub) |

`SubscriptionStatus::allowsWrite()` benar untuk `trial`, `active`, `past_due`; `allowsRead()` benar selain `terminated`.

## 4. Mesin status

Belum ada transisi status langganan maupun company di kode: tidak ada aksi, job, atau scheduler yang memindahkan `trial → active → past_due → suspended → terminated`. Status hanya dibaca. Transisi mengikuti [BR-SUB-01](05-aturan-bisnis.md#br-sub) saat modul Platform penuh dibangun.

## 5. Aturan bisnis yang berlaku

### 5.1 Gerbang langganan — `app/Http/Middleware/EnsureSubscriptionState.php`

Dipasang pada grup route tenant (`bootstrap/app.php`, setelah `SubstituteBindings`) dan pada route update Livewire (`AppServiceProvider::secureLivewireEndpoint`); alias `subscription`. Status dibaca dari baris `subscriptions` terbaru company (`latest('id')`); **bila tidak ada baris, dianggap `trial`**. Status disimpan di atribut request `subscription_status` untuk spanduk.

| Status | Perilaku kode | BR |
|---|---|---|
| `trial`, `active` | normal | [BR-SUB-01](05-aturan-bisnis.md#br-sub) |
| `past_due` | normal + spanduk kuning (`layouts/partials/subscription-banner.blade.php`) | BR-SUB-01 |
| `suspended` | hanya metode aman (GET/HEAD/OPTIONS); lainnya 403 "Langganan ditangguhkan…". Pengecualian tulis: nama route `billing.payment.store` (route-nya belum ada). Spanduk merah | [BR-SUB-02](05-aturan-bisnis.md#br-sub) |
| `terminated` | hanya user dengan role `company_admin`, hanya metode aman; lainnya 403. `LoginController` tenant juga menolak login user non-Admin. Spanduk gelap | [BR-SUB-03](05-aturan-bisnis.md#br-sub) |

Route autentikasi tenant (`login`, `portal.login`, `two-factor`, `logout`, `password.*`, `invitation.*`) selalu lolos agar user tetap bisa masuk dan keluar.

### 5.2 Aturan lain

| Rujukan | Catatan implementasi |
|---|---|
| [BR-SUB-04](05-aturan-bisnis.md#br-sub) | Beranda Super Admin hanya menampilkan nama, kode, host, status company, dan status langganan; tidak ada tautan ke data tenant |
| [BR-SUB-06](05-aturan-bisnis.md#br-sub) | Akun Super Admin terpisah dari akun company (tabel & guard berbeda) |
| NFR-04 | `POST /admin/login` memakai rate limiter `login` yang sama dengan tenant (per email + IP, default 5/menit) |

## 6. Layar

Semua route di `routes/web.php`, dibatasi ke setiap domain pusat di `config('tenancy.central_domains')`; nama route hanya dipasang pada domain pertama.

| Layar | Route | Middleware | Controller / view | Isi |
|---|---|---|---|---|
| Masuk Super Admin | `GET /admin/login`, `POST /admin/login` (nama `platform.login`, `platform.login.store`) | `guest:platform`; POST + `throttle:login` | `PlatformLoginController@show/store`, view `platform.auth.login` | Email `*`, password `*`, ingat saya. Email tak dikenal dan password salah memberi pesan yang sama ("Email atau password salah."). Berhasil → regenerasi sesi, isi `last_login_at`, arahkan ke `platform.dashboard` |
| Beranda Super Admin | `GET /admin` (`platform.dashboard`) | `auth:platform` | `PlatformDashboardController`, view `platform.dashboard` | Tabel company urut nama: nama + kode, host, status company, status langganan; kosong → "Belum ada company." Tanpa aksi |
| Keluar | `POST /admin/logout` (`platform.logout`) | `auth:platform` | `PlatformLoginController@destroy` | Logout guard `platform`, invalidasi sesi, regenerasi token, ke `platform.login` |

## 7. Kejadian stok & integrasi

Tidak ada.

## 8. Notifikasi

Tidak ada (pengingat tagihan belum dibangun).

## 9. Laporan & dashboard

Hanya beranda §6. Laporan platform belum ada.

## 10. Kasus uji

Uji di `tests/Feature/Platform`:

| ID | Given | When | Then | Rujukan |
|---|---|---|---|---|
| TC-PLT-01 | Database pusat uji | jalankan `ProductionSeeder` | jumlah company tidak berubah; tidak ada company `DEMO` | — |
| TC-PLT-01b | — | jalankan `PlatformSeeder` | paket `standard` dan `superadmin@wms.test` ada | [00-akun-uji §1](../00-akun-uji.md#1-platform-database-pusat) |
| TC-PLT-01c | Super Admin dengan password produksi | jalankan ulang `PlatformSeeder` | password tidak ditimpa | — |
| TC-PLT-02 | Super Admin ada | buka `/admin/login`, masuk, buka `/admin`, keluar | 200; redirect ke `/admin`; `last_login_at` terisi; 200; redirect ke `/admin/login`, guest | — |
| TC-PLT-02b | Super Admin ada | masuk dengan password salah, dan dengan email tak dikenal | keduanya galat dengan pesan identik; guest | — |
| TC-PLT-02c | Belum masuk | buka `/admin` | redirect; guest | — |

Gerbang langganan diuji di modul Access (`tests/Feature/Access`): TC-ACC-19 (`terminated`: hanya Admin Company bisa masuk, tulis 403), TC-ACC-20 (`suspended`: baca 200, tulis 403), TC-ACC-28d (`suspended` menolak aksi Livewire).

## 11. Di luar lingkup modul ini

Semua fitur Platform selain §6 menunggu modul Platform penuh (§13.3). Login SSO dan pemilih company ([BR-SUB-05](05-aturan-bisnis.md#br-sub)) `[F3]`.

## 12. Definisi selesai

- [x] Route login/beranda/logout Super Admin di domain pusat
- [x] Model & enum pusat; gerbang `EnsureSubscriptionState`
- [x] Seeder pusat terpisah produksi/demo
- [x] TC-PLT-01, TC-PLT-02 (beserta variannya) lulus
- [ ] `CreateTenant` dan layar pembuatan company (§13.3)
- [ ] Modul Platform penuh (§13.3)

## 13. Status & catatan implementasi (24 September 2026)

### 13.1 Seeder

| Seeder | Isi | Produksi |
|---|---|---|
| `ProductionSeeder` | Memanggil `PlatformSeeder` saja | aman |
| `PlatformSeeder` | `updateOrCreate` paket `standard` (Standar, kuota WA 1.000, kuota berkas 10.240 MB); membuat Super Admin dari `PLATFORM_ADMIN_EMAIL`/`_NAME`/`_PASSWORD` di `.env` **hanya bila belum ada** — password tidak pernah ditimpa. Tanpa `PLATFORM_ADMIN_PASSWORD`: lokal/testing memakai password dev dari [00-akun-uji §1](../00-akun-uji.md#1-platform-database-pusat), lingkungan lain berhenti dengan galat | aman |
| `PlatformDemoSeeder` | Company `DEMO` (PT Demo Konstruksi, subdomain `demo`, `CompanyStatus::Active`) dan langganan `trial` 14 hari **bila belum ada** — status langganan yang sudah hidup tidak dikembalikan ke trial | menolak berjalan |
| `DatabaseSeeder` | `PlatformSeeder`, lalu `PlatformDemoSeeder` kecuali di produksi | — |

### 13.2 Penyimpangan dan temuan

1. **Docblock `database/seeders/ProductionSeeder.php` salah.** Ia menyatakan data acuan tenant "dibuat otomatis saat company baru lahir lewat `TenantDatabaseSeeder`". Kenyataannya `TenancyServiceProvider` pada `TenantCreated` hanya menjalankan `CreateDatabase` dan `MigrateDatabase` — **tidak ada `SeedDatabase`**. Company baru lahir dengan database kosong tanpa permission, role bawaan, satuan, alasan, tipe gudang. Sampai `CreateTenant` ada, jalankan `php83 artisan tenants:seed --tenants=<id>` secara manual ([00-akun-uji §6](../00-akun-uji.md#6-cara-pakai)). Docblock PHP belum dikoreksi.
2. **`terminated` mengizinkan semua GET untuk Admin Company**, bukan hanya ekspor seperti bunyi [BR-SUB-03](05-aturan-bisnis.md#br-sub). Pembatasan ke layar ekspor belum ada.
3. **`suspended` memblokir seluruh aksi Livewire**, karena endpoint update Livewire selalu POST. Interaksi yang sebenarnya hanya-baca (mis. mengganti penyaring laporan di [16-shared §6.2](16-shared-laporan-berkas.md#62-satu-laporan--get-reportsreport--reportcontrollershow--reportviewer)) ikut ditolak.
4. **`companies.status` tidak diperiksa** oleh middleware mana pun; hanya status langganan yang menentukan akses.
5. **Pengecualian `billing.payment.store`** merujuk route yang belum ada (unggah bukti bayar, [Blueprint §14](01-blueprint.md#14-platform--langganan)).
6. **Login Super Admin tidak mencatat percobaan masuk dan tidak mengunci akun** setelah gagal berulang, tidak memakai 2FA; hanya rate limit. Komentar kode menyebut NFR-02 untuk pesan galat yang seragam, padahal NFR-02 mengatur POST + CSRF.
7. **`TenantDeleted` menjalankan `DeleteDatabase`.** Belum ada jalur yang menghapus company, tetapi bila dipakai ia bertentangan dengan masa simpan 90 hari [BR-SUB-01](05-aturan-bisnis.md#br-sub) dan [P-03](01-blueprint.md#5-prinsip-desain).

### 13.3 Sisa pekerjaan

1. **Aksi `CreateTenant` dan layarnya** ([Arsitektur §3 langkah 5](08-arsitektur.md#3-tenancy--siklus-request)): buat company → buat DB → `tenants:migrate` → `tenants:seed` (`TenantDatabaseSeeder`: `ReferenceSeeder`, `MasterReferenceSeeder`, `WarehouseReferenceSeeder`) → kirim `user_invitations` ke Admin Company. Belum ada aksi maupun layar; company hanya lahir lewat `PlatformDemoSeeder` atau tinker.
2. **Modul Platform penuh**, tetap modul terakhir dalam urutan [Arsitektur §12](08-arsitektur.md#12-langkah-berikutnya-part-4): paket & trial, tagihan, unggah dan verifikasi bukti bayar, transisi status langganan terjadwal, akses dukungan dari sisi Super Admin, flag fitur, dan penyelesaian temuan §13.2.

Prompt lanjutan: [prompts/17-platform-login](../prompts/17-platform-login.md).
