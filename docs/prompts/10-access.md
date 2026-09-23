# Prompt: bangun modul `access` (Part 6)

**Versi:** 1.0 · **Tanggal:** 23 September 2026 · **Modul:** `access` ([spesifikasi](../wms/10-access.md))

**Cara pakai:** buka Claude Code di root repo (`C:\laragon\www\wms`), tempel prompt di bawah. Prompt ini mengasumsikan spesifikasi modul sudah disetujui; jangan mengubah keputusan desain lewat prompt, ajukan sebagai asumsi baru bila perlu.

---

```
Kamu senior Laravel engineer. Bangun modul `access` untuk WMS sesuai dokumen yang SUDAH ADA di repo ini. Jangan mengarang kebutuhan baru.

BACA DULU (urut):
1. CLAUDE.md — aturan kerja, lingkungan (Laragon, PHP 8.3.33 lewat `php83`, MySQL 8.4).
2. docs/wms/10-access.md — spesifikasi modul ini: lingkup, permission, entitas, layar, kasus uji TC-ACC-01..27.
3. docs/wms/08-arsitektur.md §3 (tenancy & siklus request), §4 (struktur kode), §2 (AD-01, AD-02, AD-06, AD-07), §9 (paket), §10 (lingkungan).
4. docs/wms/08a-model-data-inti.md — area "Database pusat" dan "User, role, cakupan, struktur organisasi".
5. docs/wms/05-aturan-bisnis.md — BR-GEN-05, BR-GEN-09, BR-GEN-11, BR-ACC-01..06, BR-SUB-02..06, BR-PRJ-07, BR-APR-03.
6. docs/wms/03-glosarium.md — nama tabel/model wajib sama persis.
7. docs/00-akun-uji.md — isi seeder demo (jangan mengarang akun lain).

STACK & PAKET (kunci versi, jangan naikkan sendiri):
- Laravel 13, PHP 8.3.33 (ZTS). Semua perintah memakai `php83` = C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe; `php` di PATH (8.5) DILARANG.
- stancl/tenancy ^3.10 (multi-database, identifikasi subdomain) — AD-01.
- spatie/laravel-permission ^8.3 — AD-06. Cakupan role x scope TIDAK memakai paket: tabel `role_assignments` sendiri + global scope.
- spatie/laravel-activitylog ^4.12 — AD-07. (5.x butuh PHP 8.4, tidak dipakai.)
- livewire/livewire ^4.4 + Alpine; Blade; aset dari template/ (NexaDash, Bootstrap 5.3). jQuery hanya untuk plugin (DataTables/Select2), bukan logika.

ATURAN KODE:
- Struktur per domain: app/Domain/<Modul>/{Models,Enums,Actions,Policies,Livewire,Events,Jobs}. Model tanpa logika bisnis; satu aksi = satu kelas Action yang namanya mengikuti permission (`user.invite` -> Domain\Access\Actions\InviteUser).
- Migrasi dipisah: database/migrations/central dan database/migrations/tenant. Kolom persis seperti 08a + kolom *(impl.)* di 10-access §3.
- Enum PHP 8.3 untuk semua nilai enum; nilai HARUS dari docs/wms/06-katalog-status-dan-enum.md. Dilarang menambah nilai.
- Semua teks UI Bahasa Indonesia lewat lang/id/*.php. Field wajib ditandai `*` (BR-GEN-11).
- Tidak ada harga/nilai uang di kode WMS (D-07). Tidak ada hapus fisik data terpakai (P-03).
- Middleware urutan: tenancy subdomain -> status langganan -> auth -> portal klien -> ScopedToUser.

YANG DIBANGUN:
1. Kerangka aplikasi: composer.json, koneksi `central` + `tenant`, bootstrap tenancy, .env.example, Vite + aset template.
2. Migrasi central: companies, plans, subscriptions, platform_users, sso_identities (stub), feature_flags, support_accesses, tenant_migration_runs. Tenant: users, roles, permissions, role_permissions, role_assignments, org_units, positions, user_invitations, devices, password_histories, activity_log, sessions.
3. Model + factory + policy untuk semua tabel tenant di atas.
4. Layar dari docs/wms/10-access.md §6, memakai layout Blade hasil konversi template/partials + template/auth.
5. Seeder: PlatformSeeder (Super Admin + company DEMO), ReferenceSeeder (role bawaan + permission semua modul yang sudah dikenal), DemoSeeder (persis docs/00-akun-uji.md).
6. Test Feature untuk SEMUA TC-ACC-01..27, satu metode per TC, nama metode menyebut ID-nya.

SELESAI BILA:
- `php83 artisan migrate --database=central` dan `tenants:migrate` jalan bersih di MySQL 8.4.
- `php83 artisan test` hijau; setiap TC-ACC punya test.
- Login demo bekerja di demo.wms.test dan /portal sesuai TC-ACC-17/18.
- docs/wms/10-access.md diperbarui bila implementasi menyimpang (naikkan versi + changelog README).

JANGAN: commit ke git tanpa diminta; mengubah dokumen keputusan (D-xx); menambah status/istilah di luar katalog & glosarium; memakai `php` PATH.
```

---

## Catatan pelaksana

- Paket `spatie/laravel-activitylog` dipasang pada **^4.12** karena 5.x menuntut PHP 8.4 sedangkan proyek memakai PHP 8.3.33 ([08 §9](../wms/08-arsitektur.md#9-verifikasi-paket-laravel-13-php-83--23-sep-2026)).
- Nama database: pusat `wms_central`, tenant `wms_tenant_<kode company>`, uji `wms_central_test` / `wms_tenant_test_*`. MySQL lokal dipakai bersama proyek lain — jangan menyentuh database di luar prefiks `wms_`.
- Setelah modul ini selesai, prompt modul berikutnya (`master`) dibuat dengan pola yang sama.
