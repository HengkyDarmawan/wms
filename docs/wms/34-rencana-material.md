# Spesifikasi Modul — Rencana Kebutuhan Material Proyek (BoQ)

**Versi:** 0.1
**Tanggal:** 4 November 2026
**Status:** lingkup disetujui ([A-62](04-keputusan-dan-asumsi.md#a-62), 23 Sep 2026); rincian pembangunan [A-428](04c-asumsi-lanjutan.md#a-428) *Setuju*, [A-429](04c-asumsi-lanjutan.md#a-429) *Perlu validasi*
**Modul:** `master` (rencana), `shared` (laporan Material per Proyek), `approval` (kondisi "melebihi rencana")
**Fase:** F2 (daftar tunggu Fase 2, setelah [33-pendukung-f2](33-pendukung-f2.md))
**Dokumen terkait:** [BR-PRJ-09](05-aturan-bisnis.md#br-prj) · [11-master](11-master.md) · [23-pemakaian §9](23-pemakaian.md) · [20-approval](20-approval.md) · [Model data 08a `project_material_plans`](08a-model-data-inti.md) · [Glosarium *Rencana Kebutuhan Material*](03-glosarium.md)
**Ketergantungan modul:** Master (proyek, item), Request (baris REQ), Issue (`ProjectMaterialSummary`), Approval

---

## 1. Tujuan & lingkup

Kontraktor punya **BoQ kuantitas**: berapa banyak tiap material yang direncanakan untuk satu proyek. WMS sudah tahu berapa yang diminta, dikirim, dan dipakai; modul ini menambahkan **rencana** sebagai pembanding, supaya terlihat material mana yang mendekati atau melewati rencana sebelum terlambat. Tanpa nilai uang ([D-07](04-keputusan-dan-asumsi.md#d-07)).

Termasuk: unggah rencana dari Excel per proyek (satu unggahan = satu **versi** baru untuk seluruh proyek), riwayat versi, tab **Rencana material** di hub proyek (rencana vs diminta/terkirim/terpakai), kolom *Rencana* di laporan Material per Proyek, dan kondisi approval REQ **Melebihi rencana**.

Tidak termasuk: unggah oleh klien lewat portal (ditunda, [A-429](04c-asumsi-lanjutan.md#a-429)); ubah baris satu per satu di layar (cukup unggah versi baru); rencana per tahap/minggu; satuan selain satuan dasar di berkas.

## 2. Aktor & permission

| Role | Permission | Cakupan |
|---|---|---|
| Admin Company, Manajemen, Pemohon Internal | `project.plan` *(baru)*: unggah versi rencana | proyek yang ditugaskan |
| Semua pemegang `project.view` | lihat tab Rencana material & riwayat versi | proyek dalam cakupan |
| Pemegang `approval_rule.manage` | kondisi *Melebihi rencana* di aturan REQ | semua |

## 3. Entitas & data

### 3.1 `project_material_plans` (stub Fase 1, kolom baru)

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `project_id`, `item_id` | bigint FK | — | |
| `version` | int | — | nomor versi per proyek; unggahan baru = maks + 1 |
| `planned_qty_base` | decimal(18,4) | — | > 0, satuan dasar item |
| `is_current` | bool | — | hanya baris versi terbaru yang `true` |
| `import_batch_id` | bigint | ya | stub, tidak dipakai |
| `created_by` *(baru)* | bigint FK `users` | ya | pengunggah |
| `source_file` *(baru)* | varchar(150) | ya | nama berkas unggahan |

Unik (`project_id`, `item_id`, `version`). Versi lama tidak dihapus (P-03).

## 4. Mesin status

Tidak ada status. Satu aksi: `ImportProjectMaterialPlan` (`project.plan`) — semua baris valid atau tidak ada yang disimpan; versi lama `is_current = false`, versi baru `true`, dalam satu transaksi.

## 5. Aturan bisnis yang berlaku

| BR | Catatan implementasi |
|---|---|
| [BR-PRJ-09](05-aturan-bisnis.md#br-prj) | dibangun: versi, unggah Excel, kolom *Rencana*, kondisi approval |
| BR-GEN-09 | pengunggah harus boleh mengakses proyek itu |
| BR-GEN-11 | kode item harus ada, jumlah > 0, kode tidak boleh ganda dalam satu berkas; galat per baris, semua-atau-tidak |
| P-03 | versi lama tetap tersimpan, dapat dilihat di riwayat |

**Melebihi rencana** (kondisi `exceeds_plan`, REQ): REQ dianggap melebihi bila, untuk minimal satu item yang **punya** rencana di versi berlaku, jumlah *diminta* proyek itu (REQ lain yang masih berlaku + REQ ini) lebih besar dari rencana. Item tanpa rencana tidak dihitung melebihi ([A-429](04c-asumsi-lanjutan.md#a-429)).

## 6. Layar

### 6.1 Hub proyek — tab **Rencana material**

- Kepala: "Versi n · diunggah {nama} {tanggal} · {berkas}" atau keadaan kosong "Belum ada rencana material untuk proyek ini. Unduh templat, isi kode item dan jumlah, lalu unggah."
- Tabel per item: Kode · Nama · Satuan · **Rencana** · Diminta · Terkirim · Terpakai · **Sisa rencana** (rencana − diminta). Baris yang diminta > rencana ditandai *Melebihi*; item yang diminta tanpa rencana tampil dengan Rencana "-".
- Tombol (pemegang `project.plan`): **Unduh templat** (berisi rencana berlaku bila ada) dan **Unggah versi baru** (berkas Excel `*`). Galat unggah ditampilkan per baris.
- **Riwayat versi**: nomor versi, jumlah item, pengunggah, tanggal, berkas.

### 6.2 Laporan Material per Proyek

Kolom baru *Rencana* (versi berlaku) di depan *Diminta*; item yang direncanakan tetapi belum ada pergerakan ikut tampil.

### 6.3 Aturan approval REQ

Kondisi *Melebihi rencana material* (Ya/Tidak) di mode sederhana maupun lanjutan (isian kondisi dipakai bersama), seperti *Permintaan dari klien*; kalimat ringkasan "yang melebihi rencana material". Simulasi approval punya centang yang sama.

## 7. Kejadian stok & integrasi

Tidak ada (rencana tidak menggerakkan stok).

## 8. Notifikasi

Tidak ada di versi ini.

## 9. Laporan & dashboard

§6.2.

## 10. Kasus uji (Given / When / Then)

| ID | Given | When | Then | BR |
|---|---|---|---|---|
| TC-BOQ-01 | proyek tanpa rencana | unggah 2 baris valid | versi 1, 2 baris `is_current` | BR-PRJ-09 |
| TC-BOQ-02 | versi 1 ada | unggah lagi | versi 2 berlaku, versi 1 `is_current = false` tetapi tetap ada | P-03 |
| TC-BOQ-03 | berkas dengan kode tak dikenal / jumlah 0 / kode ganda | unggah | ditolak, galat per baris, tidak ada yang tersimpan | BR-GEN-11 |
| TC-BOQ-04 | pengguna tanpa `project.plan` / proyek di luar cakupan | unggah | ditolak | BR-GEN-09 |
| TC-BOQ-05 | rencana 100, REQ lain 60 | REQ baru 50 dievaluasi aturan `exceeds_plan` | cocok (110 > 100); REQ 30 tidak cocok | BR-PRJ-09 |
| TC-BOQ-06 | item tanpa rencana | REQ 1000 | tidak dianggap melebihi | A-429 |
| TC-BOQ-07 | rencana item X tanpa pergerakan | laporan Material per Proyek | baris X tampil, Rencana terisi | BR-PRJ-09 |
| TC-BOQ-08 | tab Rencana material | dibuka | angka rencana/diminta/sisa benar, tanda *Melebihi* | BR-PRJ-09 |

## 11. Di luar lingkup modul ini

Unggah oleh klien di portal; perbandingan per tahap waktu; nilai uang (Akuntansi).

## 12. Definisi selesai

- [x] Migrasi kolom + izin `project.plan` untuk company lama (`000580`, menambah, tanpa `sync`)
- [x] `ImportProjectMaterialPlan`, `ProjectPlan` (rencana berlaku & cek melebihi)
- [x] Tab hub proyek (`ProjectMaterialPlanTab`), kolom laporan, kondisi approval + simulasi
- [ ] TC-BOQ-01–08 lulus; uji browser desktop & HP
- [x] BR-PRJ-09, glosarium, 11/20/23 diperbarui; versi naik + changelog
