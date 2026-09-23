# Prompt: bangun modul `master` (Part 6)

**Versi:** 1.0 · **Tanggal:** 23 September 2026 · **Modul:** `master` ([spesifikasi](../wms/11-master.md))

**Cara pakai:** buka Claude Code di root repo, tempel prompt di bawah. Modul `access` harus sudah jalan.

---

```
Kamu senior Laravel engineer. Bangun modul `master` untuk WMS sesuai dokumen yang SUDAH ADA di repo ini. Jangan mengarang kebutuhan baru.

BACA DULU (urut):
1. CLAUDE.md — aturan kerja & lingkungan (Laragon, php83, MySQL 8.4).
2. docs/wms/11-master.md — spesifikasi modul ini: entitas, aturan, layar, TC-MST-01..23.
3. docs/wms/10-access.md §13 — pola yang sudah dipakai modul pertama (domain, Livewire, uji). Ikuti pola itu.
4. docs/wms/08a-model-data-inti.md — area "Master data (tenant)".
5. docs/wms/05-aturan-bisnis.md — BR-MST-01..05, BR-STK-08..12, BR-CNV-03, BR-REQ-03/11, BR-PRJ-01..04, BR-GEN-11, P-03.
6. docs/wms/06-katalog-status-dan-enum.md — enum item_status, ownership_model, tracking_mode, removal_strategy, project_status, vendor_type, vendor_status. JANGAN menambah nilai.
7. docs/wms/03-glosarium.md — nama tabel/model wajib sama persis.
8. docs/00-akun-uji.md — data demo (klien, proyek, vendor) harus persis.

YANG DIBANGUN:
1. Migrasi tenant `database/migrations/tenant/..._create_master_tables.php`: clients, projects, vendors, item_vendors, vehicles, carriers, reason_codes, storage_categories, uom_categories, uoms, item_categories, items, item_uom_conversions, lots, serials, pieces, company_settings, feature_settings, project_material_plans (stub F2). Tambahkan FK users.client_id -> clients yang ditunda di modul Access.
2. app/Domain/Master/{Models,Enums,Actions,Policies,Livewire} mengikuti AD-02. Satu aksi = satu kelas Action bernama sesuai permission.
3. Validasi matriks kombinasi pelacakan (BR §15) di satu tempat yang bisa diuji: tracking_mode x removal_strategy x has_expiry x ownership_model.
4. Tujuh layar 11-master §6 (Livewire + Blade NexaDash) plus menu "Master data" di sidebar sesuai permission.
5. Seeder: ReferenceSeeder tenant ditambah kategori satuan + satuan standar (pcs, set, mm, cm, m, g, kg, ton, ml, L, m3, m2), alasan baku per konteks, kategori penyimpanan standar; DemoSeeder ditambah klien, proyek, vendor sesuai docs/00-akun-uji.md.
6. Modul Access: ganti input id cakupan gudang/proyek menjadi pilihan nama proyek (gudang menyusul di modul warehouse).
7. Test Feature untuk SEMUA TC-MST-01..23, nama metode menyebut ID-nya.

ATURAN KODE:
- Master tidak pernah dihapus (P-03); nonaktif ditolak bila masih dipakai data aktif (BR-MST-05).
- Kode master disimpan huruf besar, unik, tidak bisa diubah setelah dibuat (BR-MST-01).
- Tidak ada harga/nilai uang (D-07).
- Field wajib ditandai `*` di form (BR-GEN-11); menonaktifkan memakai Alasan `*` + Keterangan opsional.
- Semua teks UI Bahasa Indonesia lewat lang/id.
- Enum PHP 8.3; nilai hanya dari Katalog Status.

SELESAI BILA:
- `php83 artisan tenants:migrate` bersih; `php83 artisan test` hijau; semua TC-MST punya test.
- Layar bisa dibuka dengan akun admin@demo.wms.test dan menampilkan data demo.
- docs/wms/11-master.md diperbarui bila implementasi menyimpang (naikkan versi + changelog docs/README.md).

JANGAN: commit ke git tanpa diminta; mengubah keputusan D-xx; menambah status/istilah di luar katalog & glosarium; memakai `php` PATH.
```

---

## Catatan pelaksana

- `projects.site_warehouse_id` di ERD **tidak dibuat**: [A-40](../wms/04-keputusan-dan-asumsi.md#a-40) menjadikan relasinya satu-ke-banyak lewat `warehouses.project_id` (modul `warehouse`).
- `lots`, `serials`, `pieces` dibuat di modul ini tetapi barisnya lahir dari transaksi; di layar hanya tampil baca-saja.
- Setelah modul ini, urutan berikutnya adalah `warehouse` ([Arsitektur §12](../wms/08-arsitektur.md#12-langkah-berikutnya-part-4)).
