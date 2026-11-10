# Spesifikasi Modul — PWA offline penuh (Fase 2b, draf)

**Versi:** 0.3
**Tanggal:** 10 November 2026
**Status:** v0.3: tahap 2 dibangun: tugas gudang offline `/offline/tugas` ([A-439](04c-asumsi-lanjutan.md#a-439)); v0.2: tahap 1 dibangun: hitung opname offline ([A-438](04c-asumsi-lanjutan.md#a-438)); penundaan diganti [A-430](04c-asumsi-lanjutan.md#a-430); draf; **ditunda sampai ada klien trial yang memakai aplikasi** ([A-426](04c-asumsi-lanjutan.md#a-426), Blueprint §19 "offline penuh setelah ledger stabil"). Arah arsitektur disetujui ([AD-16](08-arsitektur.md)); rincian di bawah *Perlu validasi*
**Modul:** `pwa` (lintas modul: Receipt, Putaway, Shipment, Count)
**Fase:** F2b
**Dokumen terkait:** [Blueprint §11](01-blueprint.md#11-pwa--mode-offline) · [AD-12, AD-16](08-arsitektur.md) · [A-42](04-keputusan-dan-asumsi.md#a-42), [A-43](04-keputusan-dan-asumsi.md#a-43), [A-44](04-keputusan-dan-asumsi.md#a-44), [A-49](04-keputusan-dan-asumsi.md#a-49) · [BR-OPN-03](05-aturan-bisnis.md#br-opn)
**Ketergantungan modul:** semua modul dokumen Fase 1; [33-pendukung-f2](33-pendukung-f2.md)

---

## 1. Tujuan & lingkup

Staf gudang dan site tetap bisa bekerja tanpa sinyal: **penerimaan, put-away, picking, konfirmasi muat, bukti terima, hitung opname, foto**. Transaksi disimpan di perangkat lalu dikirim otomatis saat koneksi kembali. Server tetap acuan akhir; transaksi yang bentrok tidak diposting otomatis tetapi masuk **antrean tinjauan**.

Tidak termasuk: membuat dokumen niat (REQ, TRF, PRQ) secara offline; approval offline; layar manajemen (laporan, master, pengaturan) offline.

## 2. Keputusan arsitektur ([AD-16](08-arsitektur.md))

Livewire membutuhkan server untuk setiap aksi, jadi tujuh proses offline memakai **layar ringkas Alpine + IndexedDB** yang di-cache service worker. Layar lain tetap Livewire (AD-12). Logika bisnis tidak diduplikasi di klien: klien hanya merekam niat (pindai, jumlah, foto), server yang memvalidasi dan memposting lewat kelas Action yang sama.

## 3. Komponen

| Komponen | Isi |
|---|---|
| Paket data lokal | master item & bin gudang yang ditugaskan, tugas (PCK, PUT, GRN, sesi hitung, SJ tujuan) yang dibagikan ke perangkat; diperbarui saat online |
| Antrean klien | IndexedDB, satu baris per transaksi: UUID perangkat, jenis, payload, waktu, status kirim |
| `/api/sync` | idempoten berdasarkan UUID (kirim ulang tidak memposting dua kali); token perangkat (`devices`) yang diperbarui otomatis |
| `sync_queue` (server) | jejak setiap transaksi masuk: diterima, diposting, ditinjau, ditolak |
| Antrean tinjauan | transaksi pengurang stok yang bentrok saldo, bin beku ([BR-OPN-03](05-aturan-bisnis.md#br-opn)), atau dokumen yang sudah berubah status; diputus Kepala Gudang |
| Nomor sementara | dokumen yang lahir offline memakai nomor sementara sampai diposting ([A-43](04-keputusan-dan-asumsi.md#a-43)) |

Sinkron berjalan saat koneksi kembali dan saat aplikasi dibuka, tanpa bergantung pada *background sync* (dukungan iOS tidak merata). Antrean ditahan bila langganan ditangguhkan ([A-44](04-keputusan-dan-asumsi.md#a-44)) dan tetap tersimpan bila sesi kedaluwarsa.

## 4. Urutan pembangunan (rencana kerja Tahap 2)

1. Fondasi: IndexedDB, pendaftaran perangkat & token, `/api/sync`, `sync_queue`, nomor sementara.
2. Paket data lokal.
3. Layar offline per proses: hitung opname → bukti terima → put-away → picking → penerimaan → konfirmasi muat → foto.
4. Antrean tinjauan + layar Kepala Gudang.
5. Uji: mode pesawat end-to-end, dua perangkat mengambil stok yang sama, putus di tengah sinkron, kirim ulang tanpa posting ganda.

## 5. Pertanyaan terbuka (*Perlu validasi*, [A-426](04c-asumsi-lanjutan.md#a-426))

- Berapa lama data lokal dan antrean boleh tinggal di perangkat sebelum dipaksa sinkron atau dibuang?
- Foto offline: batas jumlah & ukuran per perangkat.
- Siapa yang memutus antrean tinjauan bila Kepala Gudang tidak ada (cadangan)?
- Perangkat bersama (satu HP dipakai bergantian): wajib keluar per orang atau PIN cepat?

## 6. Kasus uji

TC-PWA-01 (berkas PWA) tetap berlaku; TC-PWA-11–15 di [35 §5](35-pendukung-f2-lanjutan.md#5-kasus-uji).

| ID | Isi | Berkas |
|---|---|---|
| TC-PWA-21 | penerimaan & put-away diposting sekali walau dikirim ulang | `Shared/OfflineTasksTest` |
| TC-PWA-22 | picking selesai; short tanpa alasan → tinjauan, tugas kembali seperti semula | `Shared/OfflineTasksTest` |
| TC-PWA-23 | konfirmasi muat; bukti terima berfoto, foto tidak masuk `sync_queue` | `Shared/OfflineTasksTest` |
| TC-PWA-24 | tanpa izin tidak diposting; dokumen tak dikenal ditolak | `Shared/OfflineTasksTest` |
| TC-PWA-25 | halaman & paket data mengikuti izin | `Shared/OfflineTasksTest` |
