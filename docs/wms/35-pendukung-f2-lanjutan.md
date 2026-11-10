# Spesifikasi Modul — Pendukung Fase 2 (lanjutan, data dummy)

**Versi:** 0.1
**Tanggal:** 4 November 2026
**Status:** dibangun 4 Nov 2026 atas permintaan pemilik produk "jalankan Fase 2 pakai data dummy" ([A-430](04c-asumsi-lanjutan.md#a-430)); rincian [A-431](04c-asumsi-lanjutan.md#a-431)–[A-438](04c-asumsi-lanjutan.md#a-438)
**Modul:** `conversion`, `asset`, `master`, `shared`, `whatsapp`, `template`, `label`, `pwa`
**Fase:** F2 (sisa daftar tunggu + RFID + PWA offline tahap pertama)
**Dokumen terkait:** [33-pendukung-f2](33-pendukung-f2.md) · [34-rencana-material](34-rencana-material.md) · [32-pwa-offline](32-pwa-offline.md) · [31-whatsapp](31-whatsapp.md) · [18-template-dokumen-label](18-template-dokumen-label.md) · [24-konversi-waste](24-konversi-waste.md) · [25-aset](25-aset.md)
**Ketergantungan modul:** semua modul Fase 1 & 1b

---

## 1. Tujuan & lingkup

Menyalakan sisa Fase 2 tanpa menunggu pihak luar. Yang bergantung pada pihak luar (Meta, reader RFID, klien trial) dijalankan dengan **simulasi dan data dummy**, siap disambungkan begitu syaratnya ada. `Fase2DemoSeeder` mengisi contoh di company DEMO.

| # | Fitur | Inti | Pengganti pihak luar |
|---|---|---|---|
| 1 | Resep konversi (BR-CNV-06) | Templat pola potong / hasil; tombol *Terapkan* di form CNV; tidak mengunci hasil; `conversions.recipe_id` | — |
| 2 | Pemicu servis dari meter (BR-AST-07) | Jadwal berinterval jam/km; meter dari pemeriksaan melewati batas → jadwal dimajukan ke hari ini; jadwal berikutnya dari meter saat selesai | — |
| 3 | Rencana material oleh klien (A-429) | Portal `/portal/projects/{id}/plan`, komponen sama dengan hub proyek; izin `project.plan` untuk Klien | — |
| 4 | Dashboard konsolidasi opname & waste | `/dashboards/opname-waste`: akurasi per gudang × bulan (angka = laporan Tren akurasi), akar masalah teratas, BA waste per gudang, isi bin Waste belum di-BA, item waste terbanyak | — |
| 5 | WhatsApp mode demo | `/settings/whatsapp-simulator`: pesan driver `log` tampil seperti chat; tombol Setujui/Tolak ditekan seolah dari HP lewat jalur inbound yang sama | akun Meta (O-03) |
| 6 | Editor teks template dokumen | Teks HTML aman di atas/bawah isi bawaan, variabel `{nomor_dokumen}` dkk., `{isi_bawaan}`, pratinjau per jenis, kembali ke bawaan | — |
| 7 | RFID keyboard wedge | `/rfid-tags`: EPC → item/serial/potongan/bin; diterjemahkan di `ScanCode` & `BinCode` sehingga semua layar pindai menerima tag; uji pindai | reader (O-10): EPC diketik/dipindai |
| 8 | PWA offline tahap 1 | Fondasi AD-16 + **hitung opname offline**: `/offline/hitung`, `/offline/hitung/data`, `POST /offline/sync` idempoten, `sync_queue`, antrean tinjauan `/offline/tinjauan`, service worker menyimpan halaman `/offline/*` | klien trial: diuji dengan mode pesawat Chrome |

Tidak termasuk (berikutnya): proses offline lain (bukti terima, put-away, picking, penerimaan, konfirmasi muat, foto), nomor dokumen sementara (A-43), API reader RFID tetap, editor template visual (WYSIWYG).

## 2. Aktor & permission

| Izin | Untuk | Role bawaan |
|---|---|---|
| `conversion.recipe` *(baru)* | kelola resep | Admin Company, Kepala Gudang |
| `project.plan` | + Klien di portal | Admin Company, Manajemen, Pemohon Internal, Klien |
| `rfid.manage` *(baru)* | kelola tag RFID (fitur `rfid` menyala) | Admin Company, Kepala Gudang |
| `count.record` / `count.approve` | hitung offline / antrean tinjauan | seperti opname |
| `company_setting.manage` | kotak pesan WhatsApp simulasi (driver `log`, bukan produksi) | Admin Company |
| `count.view` atau `waste.view` | dashboard konsolidasi | seperti laporan |

Izin baru dipasang ke company lama lewat migrasi yang menambah, tanpa `sync` (`000590`, `000610`, `000620`).

## 3. Entitas & data

| Tabel | Perubahan |
|---|---|
| `conversion_recipes` | dipakai: `definition` {conversion_type, input_item_id, potong[], hasil[]}, `created_by` baru |
| `maintenance_schedules` | `interval_meter`, `due_meter`, `meter_done` baru |
| `rfid_tags` *(baru)* | `epc` UK (heksadesimal 8–64), `target_type` (`rfid_target`), `target_id`, `is_active`, `notes`, `created_by` |
| `sync_queue` *(baru)* | `uuid` UK, `user_id`, `kind`, `payload`, `recorded_at`, `status` (`sync_status`), `message`, `decided_by/at` |
| `document_templates.body_html` | diisi editor (sudah disaring); `version` naik setiap simpan |
| `wa_message_logs.payload.pesan` | isi pesan & tombol, **hanya** driver `log` |

## 4. Aturan & keamanan

- Teks template tidak pernah diproses sebagai Blade/PHP; HTML disaring ke tag aman tanpa atribut selain `colspan`/`rowspan`/`style` (tanpa `url(`); nilai variabel di-escape.
- Payload tombol WhatsApp (token sekali pakai) hanya disimpan untuk driver `log`; simulator mati di produksi.
- EPC < 8 digit atau non-heksadesimal tidak dianggap tag (kode biasa tidak tertukar).
- Sinkron offline: server acuan akhir; bentrok aturan → `review` (BR-OPN-03), tidak diposting otomatis; langganan ditangguhkan → POST ditolak, antrean tetap di perangkat (A-44).
- Hitung buta tetap: paket data offline tidak memuat angka sistem.

## 5. Kasus uji

| ID | Isi | Berkas |
|---|---|---|
| TC-RCP-01–05 | resep: simpan, validasi & izin, terapkan di form + `recipe_id`, nonaktif, layar | `Conversion/ConversionRecipeTest` |
| TC-F2-13–14 | meter memajukan jadwal, jadwal berikutnya dari meter; aset tak bermeter | `Asset/AssetMaintenanceTest` |
| TC-BOQ-09 | klien unggah lewat portal, klien lain ditolak | `Master/ProjectMaterialPlanTest` |
| TC-DSH-01–03 | akurasi dari sesi direkonsiliasi, keadaan kosong, akses | `Shared/ConsolidationDashboardTest` |
| TC-WA-12–14 | simulator: tombol Setujui memutus approval; hanya driver log & admin | `WhatsApp/WhatsAppSimulatorTest` |
| TC-TPL-30–34 | sanitasi, variabel asing, escape & `{isi_bawaan}`, cetak, layar & pratinjau | `Template/DocumentTemplateEditorTest` |
| TC-RFID-01–04 | EPC barang & bin, nonaktif & kode pendek, validasi & izin, layar bergantung fitur | `Label/RfidTagTest` |
| TC-PWA-11–15 | sinkron idempoten, bentrok → tinjauan → diputus, data rusak ditolak, endpoint hitung buta | `Shared/OfflineSyncTest` |

## 6. Data dummy (`Fase2DemoSeeder`)

Fitur `whatsapp` & `rfid` menyala; Kepala Gudang CKG bernomor WhatsApp dummy terverifikasi dan kejadian approval dikirim instan; GNS-5K-0001 bermeter jam (120) dengan jadwal servis tiap 90 hari atau 250 jam; resep `PIPA6-4X150`; rencana material PRJ-001 (BAUT-M12 500, PIPA-PVC-4 120, SEMEN-PCC-50 2000); tiga tag RFID `E2003412000000000001–3` (barang, bin, serial); teks template Surat Jalan contoh. Driver WhatsApp lokal: `WMS_WA_DRIVER=log`.

## 7. Definisi selesai

- [x] Kode, migrasi `000590`–`000630`, izin, menu, data dummy
- [x] Uji di §5 lulus; uji browser desktop & HP; uji offline (mode pesawat)
- [x] Katalog, glosarium, aturan, 18/24/25/31/32 diperbarui; versi naik + changelog
