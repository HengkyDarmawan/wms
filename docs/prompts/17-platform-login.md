# Prompt: modul Platform — `CreateTenant`

**Versi:** 0.1 · **Tanggal:** 24 September 2026 · **Status:** login Super Admin dan gerbang langganan sudah ada; `CreateTenant` belum · **Dokumen terkait:** [17-platform-login](../wms/17-platform-login.md) · [Arsitektur §3](../wms/08-arsitektur.md#3-tenancy--siklus-request)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Login/logout Super Admin (`/admin/login`, `/admin`, `/admin/logout`), model pusat, `EnsureSubscriptionState`, dan seeder pusat **sudah ada dan teruji** (TC-PLT-01, TC-PLT-02); prompt ini hanya untuk langkah berikutnya. Modul Platform penuh tetap modul terakhir ([Arsitektur §12](../wms/08-arsitektur.md#12-langkah-berikutnya-part-4)).

---

```
Bangun aksi `CreateTenant` dan layar pembuatan company sesuai
docs/wms/17-platform-login.md §13.3 no. 1 dan docs/wms/08-arsitektur.md §3
langkah 5. Baca dulu CLAUDE.md dan urutan bacanya, lalu 17-platform-login §13.

LINGKUP
- app/Domain/Platform/Actions/CreateTenant: buat `companies` (status
  `provisioning`) + `subscriptions` trial (durasi dari paket, default 14 hari,
  A-11) → database tenant dibuat & dimigrasi oleh TenancyServiceProvider →
  jalankan TenantDatabaseSeeder → status company `active` → undangan
  `user_invitations` ke Admin Company pertama.
- Layar Super Admin di domain pusat, guard `platform`: form company (kode,
  nama, subdomain, zona waktu, paket, email Admin Company) dan tombol di
  beranda /admin.
- Uji TC-PLT-03 dst. di tests/Feature/Platform.

YANG HARUS DIPERHATIKAN
1. TenancyServiceProvider saat ini TIDAK menyeed data acuan (17-platform-login
   §13.2 no. 1). Tambahkan seed di CreateTenant atau di JobPipeline, lalu
   koreksi docblock database/seeders/ProductionSeeder.php dan 00-akun-uji §6.
2. Kegagalan di tengah jalan tidak boleh meninggalkan company `active` tanpa
   database lengkap; biarkan `provisioning` dan laporkan galatnya.
3. Subdomain unik dan valid (A-01); kode company unik (dipakai di nomor
   dokumen).
4. Super Admin tetap tidak membuka data operasional company (BR-SUB-04);
   undangan dikirim, bukan password dibuatkan.
5. Jangan menambah status company/langganan di luar Katalog §3 dan 08a.
6. Jangan menyentuh database di luar prefiks `wms_`.

ATURAN KERJA
- Bahasa Indonesia untuk dokumen dan UI; nama kode Inggris sesuai glosarium.
- Transisi status hanya lewat POST; tidak ada hapus fisik company (P-03).
- Selesai berarti: php83 artisan test hijau, py -3 docs/diagram/_verify.py OK,
  17-platform-login dinaikkan versinya dengan §13 diperbarui, dan changelog
  docs/README.md menyebut ID yang berubah.
- Jangan commit ke git kecuali diminta.
```
