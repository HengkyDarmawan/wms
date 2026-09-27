# Prompt: melanjutkan WMS di rumah (XAMPP3)

**Versi:** 2.15
**Tanggal:** 28 September 2026
**Status:** aktif — v2.13 memecah pekerjaan penutupan **Fase 1** menjadi **dua prompt** (§2 Prompt 1: siapkan, verifikasi, rapikan, commit; §3 Prompt 2: keputusan asumsi Fase 1 dan tutup Fase 1). Fase 2a WhatsApp sudah dibangun ([31-whatsapp](../wms/31-whatsapp.md)) dan **tidak** termasuk dua prompt ini (§4). v2.15: A-282 (form item ringkas) ditambah; v2.14: A-281 (denah berisi petak bin, 28 Sep) ditambah ke daftar keputusan Fase 1; jatah nomor digeser. v2.12: prompt tunggal pasca Fase 2a. Riwayat v2.1–v2.12 di git; arah sebaliknya: [00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md) (arsip)
**Dokumen terkait:** [README](../README.md) · [Laporan progres](../00-laporan-progres-2026-09-24.md) · [Setup lokal](../00-setup-lokal.md) · [Tinjauan asumsi](../00-tinjauan-asumsi-2026-09-25.md) · [Keputusan & Asumsi](../wms/04-keputusan-dan-asumsi.md) · [Asumsi lanjutan](../wms/04b-asumsi-lanjutan.md) · [`../../CLAUDE.md`](../../CLAUDE.md)

Cara pakai: nyalakan MariaDB dari XAMPP3 Control Panel, buka Claude Code di `C:\xampp3\htdocs\wms`, tempel **Prompt 1 (§2)** utuh. Setelah Claude Code melaporkan Prompt 1 selesai (uji hijau, sudah di-commit & di-push), tempel **Prompt 2 (§3)** — boleh di sesi yang sama atau sesi baru. Bagian §1, §4, §5 untuk dibaca manusia.

---

## 1. Keadaan saat ini (27 Sep 2026)

| Hal | Keadaan |
|---|---|
| Git | `main` di GitHub masih `b8a4aaa` (26 Sep sore). **Semua pekerjaan 26 Sep malam – 27 Sep ada di working tree mesin rumah dan belum di-commit** (putaran asumsi 1–9, "Masuk sebagai", label, riwayat cetak & segel, PPN PO, bonus vendor, approver sedivisi, kalender libur, sisa kecil A-271–A-273, Fase 2a WhatsApp A-274–A-280). Prompt 1 meng-commit & mem-push-nya |
| Uji | **707 hijau / 8.447 asersi** di sandbox cloud (PHP 8.4 + MariaDB 10.11); **belum** dijalankan di XAMPP3 |
| Migrasi tenant baru sejak `b8a4aaa` | `000290`–`000360` (label, riwayat cetak & segel, PPN PO, bonus GRN, approver sedivisi, kalender libur, OTP otomatis, kolom WhatsApp). Paket npm baru: `interactjs`, `@zxing/browser`, `@zxing/library` |
| Kode Fase 1 | Semua butir [Blueprint §18](../wms/01-blueprint.md#18-peta-modul--fase-rilis) Fase 1 & 1b selesai, termasuk sisa kecil. Sengaja tidak dibangun: cross-dock penuh ([A-83](../wms/04-keputusan-dan-asumsi.md#a-83)), isi balik `from_stock_status` ([A-194](../wms/04-keputusan-dan-asumsi.md#a-194)) |
| Yang membuat Fase 1 belum "tutup" | (1) belum diverifikasi & di-commit di mesin rumah; (2) utang dokumen: beberapa §13 *Sisa pekerjaan* masih menyebut hal yang sudah selesai/diputus (daftar di Prompt 1 langkah 2); (3) **6 asumsi Fase 1 menunggu keputusan pemilik:** [A-260](../wms/04b-asumsi-lanjutan.md#a-260) ("Masuk sebagai"), [A-271](../wms/04b-asumsi-lanjutan.md#a-271) (denah), [A-272](../wms/04b-asumsi-lanjutan.md#a-272) (impor gudang), [A-273](../wms/04b-asumsi-lanjutan.md#a-273) ⚠ (OTP otomatis), [A-281](../wms/04b-asumsi-lanjutan.md#a-281) (denah berisi petak bin), [A-282](../wms/04b-asumsi-lanjutan.md#a-282) (form item ringkas) |
| Isu pemilik di luar kode | O-07/O-08 (brand & harga), O-11 (naskah ketentuan), O-15 (penyedia OTP/SMS) — tidak menghalangi penutupan Fase 1 |

## 2. Prompt 1 — siapkan, verifikasi, rapikan, commit

```
Lanjutkan pekerjaan WMS di mesin rumah (XAMPP3 di C:\xampp3, repo C:\xampp3\htdocs\wms:
PHP 8.3.33 = `php` dari C:\xampp3\php\php.exe; MariaDB 10.4.32, mysql di
C:\xampp3\mysql\bin\mysql.exe, root tanpa password; web `php artisan serve --host=127.0.0.1 --port=8000`;
Node 22, Composer 2.8, `py -3`. Laragon TIDAK dipakai).
Ini PROMPT 1 dari 2 untuk menutup Fase 1. Tujuannya: lingkungan siap, semua uji hijau di mesin ini,
utang dokumen Fase 1 dibereskan, lalu semuanya di-commit dan di-push. JANGAN membangun fitur baru.
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/prompts/00-lanjutkan-di-rumah.md (§1–§2),
docs/00-laporan-progres-2026-09-24.md (§1, §3, §4, §5.17–§5.18) dan 5 blok teratas changelog docs/README.md.
Periksa `git status` — banyak perubahan belum di-commit (lihat §1 prompt ini).

## Langkah 1 — siapkan lingkungan & verifikasi
1. Pastikan MariaDB XAMPP3 berjalan. `composer install`, `npm install`, `npm run build`.
2. `.env`: `APP_URL=http://wms.test:8000`, `CACHE_STORE=array`, DB `127.0.0.1:3306` root tanpa password,
   `WMS_MESSAGING_DRIVER=log`, `WMS_WA_DRIVER=log` (docs/00-setup-lokal.md §4).
3. php.ini XAMPP3: `upload_max_filesize` ≥ 20M, `post_max_size` ≥ 25M (A-257). TANYAKAN saya sebelum mengubah.
4. `php artisan migrate` (pusat) lalu `php artisan tenants:migrate` (tenant sampai 000360).
5. Demo segar: `php artisan tenants:migrate-fresh --tenants=1` lalu
   `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder" --force`.
6. `php artisan test` — semua hijau, jumlah ≥ 711.
7. `py -3 docs/diagram/_verify.py` → HASIL: OK.
8. `php artisan serve --host=127.0.0.1 --port=8000` di latar, lalu dengan
   `MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe`: `node tests/e2e/ui-check.mjs` (0 error console),
   `node tests/e2e/alur-req-sj.mjs` (10/10), `node tests/e2e/alur-pendukung.mjs` (9/9) — berurutan pada demo
   segar; periksa baris LULUS/GAGAL.
9. `php artisan stock:reconcile --tenants=1` → "saldo cocok dengan kartu stok".
Bila ada yang gagal: cari akar masalahnya (perbedaan PHP 8.3/MariaDB 10.4 vs sandbox, Windows, path),
perbaiki KODE-nya — jangan melemahkan uji — lalu ulangi dari langkah yang gagal.
Laporkan hasil langkah 1 sebelum lanjut.

## Langkah 2 — bereskan utang dokumen Fase 1
Periksa tiap butir terhadap kode dan status asumsi terbaru; perbarui teks yang basi (coret ~~…~~ + "selesai/diputus
<tanggal>, rujukan"), naikkan Versi berkas, catat di changelog README dengan ID:
a. 10-access §13.4 no. 6 — A-74 dan A-75 sudah *Setuju* (26 Sep 2026).
b. 15-picking-shipment §13 no. 6 — OTP otomatis sudah ada (A-273, dan lewat WhatsApp A-279).
c. 17-platform-login §13.3 no. 2 — A-200 *Setuju* (2FA Super Admin tetap opsional), jadi bukan sisa.
d. 19-receipt-putaway §8 "Belum dibangun (modul notifikasi belum ada)" — cek NotificationEvents: bila kejadian
   GRN menunggu QC / PUT menunggu / RTV menunggu approval / RTV dikirim sudah ada, perbarui teksnya; bila BELUM,
   jangan dibangun di sini — masukkan ke daftar langkah 3.
e. 20-approval §3.6 `approval_tokens` ("belum ada kode yang menulisnya") — kini ditulis ApprovalWhatsApp (Fase 2a).
f. 23-pemakaian §13.3 no. 3 — A-117 *Setuju*; 24-konversi-waste §13.4 no. 3 — A-162 *Setuju* (MySQL 8.4 CI
   belum diperlukan).
g. Laporan progres §3: TC yang ada di kode uji tetapi tidak ada di tabel §10 spesifikasinya (mis. TC-SJ-15–17,
   TC-ACC-29/30) — tambahkan barisnya. Cari juga TC lain yang sama dengan skrip kecil (grep ID di tests/ vs docs/).
h. Laporan progres §5.2 dan §5.3 — beri status terkini tiap temuan (selesai + rujukan, atau masih terbuka).
Cari juga §13 *Sisa pekerjaan* lain di docs/wms/1*.md–2*.md yang basi dengan cara yang sama.

## Langkah 3 — daftar sisa Fase 1 yang sungguh terbuka (JANGAN dibangun)
Susun daftar singkat hal Fase 1 yang masih terbuka setelah langkah 2, mis.:
- 17-platform-login §13.3 no. 3: hapus database company setelah `purge_after` sebagai tindakan terpisah tercatat;
- 20-approval §13.4 no. 4: approver di luar cakupan dokumen memutus dari kotak tugas tanpa membuka dokumen;
- butir 2d bila notifikasi GRN/PUT/RTV belum ada.
Tulis daftar ini di laporan progres §5.19 "Penutupan Fase 1 — daftar terbuka" (arti, usulan, perkiraan besar kecil).
Prompt 2 akan menanyakannya ke saya.

## Langkah 4 — commit & push (INI PERMINTAAN SAYA)
Setelah langkah 1 hijau semua dan langkah 2–3 selesai: pastikan `.env`, `storage/`, `node_modules/`, `public/build/`
tidak ikut (cek .gitignore). Buat commit yang rapi (boleh beberapa commit per tema: pekerjaan 26–27 Sep, Fase 2a,
utang dokumen), lalu `git push origin main`. Laporkan hash commit.

## Cara kerja
- Larangan keras CLAUDE.md: tanpa harga/uang di luar app/Domain/Purchasing, tanpa hapus fisik, stok hanya lewat
  StockLedger::post()/reverse(), status hanya dari Katalog, tidak ada transisi lewat GET.
- Pint HANYA pada berkas yang diubah. Jangan jalankan dua suite uji bersamaan; E2E jangan bersamaan dengan suite.
- Jangan menyentuh database di luar prefiks `wms_`. Setiap dokumen ≤ 450 baris.
- Laporan akhir: jumlah uji, hasil E2E, berkas dokumen yang diubah, daftar langkah 3, hash commit.
```

## 3. Prompt 2 — keputusan asumsi Fase 1 & tutup Fase 1

```
Lanjutkan WMS di mesin rumah (XAMPP3, repo C:\xampp3\htdocs\wms; lingkungan sama seperti Prompt 1).
Ini PROMPT 2 dari 2 untuk menutup Fase 1. Prompt 1 sudah selesai: uji hijau, utang dokumen beres, sudah di-push.
Baca CLAUDE.md, docs/prompts/00-lanjutkan-di-rumah.md (§1, §3), laporan progres §5.17–§5.19,
docs/00-tinjauan-asumsi-2026-09-25.md (§2.31, §2.33, §2.34 dan baris A-260), docs/wms/04b-asumsi-lanjutan.md (A-260, A-271–A-273, A-281, A-282).
Cek `git status` bersih dan `git log -1` sama dengan origin/main.

## Langkah 1 — minta keputusan saya (TUNGGU jawaban)
Sajikan SATU PER SATU, bahasa sehari-hari, tanpa istilah kode: A-260 ("Masuk sebagai"), A-271 (tambah
zona/rak/level/bin dari denah), A-272 (impor gudang dari Excel), A-273 ⚠ (OTP bukti terima otomatis), A-281 (denah: rak berisi petak bin per level), A-282 (form item: bagian opsional dalam tab), lalu tiap butir
daftar terbuka laporan progres §5.19. Untuk tiap butir: artinya + contoh di lapangan, akibat bila ditolak,
rekomendasimu (Setuju / Ubah / Tunda ke Fase 2). Pakai pertanyaan pilihan ganda. Jangan lanjut sebelum saya jawab.

## Langkah 2 — jalankan keputusan
- *Setuju*: isi kolom *Keputusan* di tinjauan asumsi dan *Validasi* di 04b ("Setuju (<tanggal>)").
- *Ubah*: buat asumsi baru berikutnya (mulai A-283, cek dulu nomor terakhir), ubah kode + uji (TC berikutnya: cek
  nomor terakhir per modul di dokumen) + dokumen modul, lalu isi *Diganti oleh*.
- *Tunda ke Fase 2*: tandai butir dengan `[F2]` di spesifikasinya dan catat alasannya.
- Butir daftar terbuka yang saya setujui untuk dibangun: kerjakan dengan pola modul (Action per permission →
  rute/Livewire → uji TC → dokumen §13 → changelog).

## Langkah 3 — verifikasi & tutup Fase 1
1. `php artisan test` (semua hijau), `py -3 docs/diagram/_verify.py` OK, tiga skrip E2E pada demo segar lulus,
   `php artisan stock:reconcile --tenants=1` cocok.
2. Laporan progres: §1 → "**Fase 1 ditutup <tanggal>**" + angka uji terakhir; §5.19 diisi keputusan; §4 tanpa 🟡
   kecuali butir yang memang [F3] (endpoint masuk Purchasing).
3. Tinjauan asumsi: yang menunggu hanya A-274–A-280 (WhatsApp, Fase 2a).
4. Prompt ini (docs/prompts/00-lanjutkan-di-rumah.md) ditulis ulang ke v2.14: keadaan "Fase 1 ditutup", pekerjaan
   berikutnya = keputusan A-274–A-280, sisa Fase 2a (31-whatsapp §13.2: pendaftaran Meta, template, uji nomor
   sungguhan), lalu Fase 2b PWA offline (spesifikasi baru docs/wms/32-…).
5. README: naikkan Versi + changelog dengan ID yang berubah.
6. Commit & push (INI PERMINTAAN SAYA). Laporkan hash commit.

## Cara kerja
- Larangan keras CLAUDE.md berlaku (tanpa harga di luar Purchasing, tanpa hapus fisik, stok lewat StockLedger,
  status dari Katalog, tanpa transisi lewat GET). Pint hanya berkas yang diubah. Database hanya prefiks `wms_`.
- Keputusan yang tidak tertulis: pilih yang paling sesuai dokumen, catat sebagai asumsi "Perlu validasi".
- Laporan akhir singkat: keputusan per butir, uji & E2E, dokumen yang berubah, hash commit, apa yang menunggu saya.
```

## 4. Sesudah Fase 1 (bukan bagian dua prompt di atas)

- **Fase 2a WhatsApp** — kode selesai ([31-whatsapp](../wms/31-whatsapp.md) §13). Sisa: keputusan [A-274](../wms/04b-asumsi-lanjutan.md#a-274)–[A-280](../wms/04b-asumsi-lanjutan.md#a-280), pendaftaran Meta & 4 template ([checklist §5](../00-checklist-persiapan.md#5-checklist-pendaftaran-whatsapp-cloud-api-mulai-hari-pertama-fase-2a)), `.env` produksi, uji nomor sungguhan, O-03/O-04.
- **Fase 2b** — PWA offline penuh & sinkron (Blueprint §11, [D-29](../wms/04-keputusan-dan-asumsi.md#d-29)), lalu [F2] lain; **Akuntansi** dan **Fase 3** hanya bila diminta.
- Jatah nomor saat v2.15 ditulis: asumsi **A-283**; spesifikasi baru **32-…**; TC berikutnya TC-WA-12, TC-WH-30, TC-SJ-21, TC-ACC-39, TC-APR-24, TC-MST-30, TC-REQ-36 (cek ulang di dokumen — dokumen yang menang).

## 5. Catatan untuk manusia

- Profil mesin rumah di [00-setup-lokal §1](../00-setup-lokal.md#1-profil-mesin). Nyalakan MariaDB dari XAMPP3 Control Panel sebelum mulai.
- Prompt 1 memakan waktu terlama di langkah 1 (instal paket, migrasi, uji ±707, tiga skrip E2E). Prompt 2 berhenti di langkah 1 menunggu jawaban Anda.
- Coba di browser demo: gudang → *Denah gudang* → *Atur denah* → *Tambah zona & rak*; `/imports` → kartu *Gudang*; `/settings/company` → saklar *OTP bukti terima otomatis* → detail SJ terkirim → *Terbitkan tautan penerima* → kode di `storage/logs/laravel.log`.
- Coba WhatsApp tanpa akun Meta: [setup lokal — WhatsApp tanpa akun Meta](../00-setup-lokal.md#whatsapp-tanpa-akun-meta-fase-2a).
- Sebelum ke kantor: perbarui prompt arah rumah → kantor ([00-lanjutkan-di-kantor.md](00-lanjutkan-di-kantor.md)).
