# Prompt: modul Stock (Part 7)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Modul ini **sudah selesai**; prompt disimpan sebagai pola untuk modul berikutnya dan sebagai catatan bagaimana modul ini dibangun.

---

```
Bangun modul `stock` sesuai docs/wms/13-stock.md. Baca dulu CLAUDE.md dan urutan
bacanya, lalu docs/wms/12-warehouse.md §13 sebagai pola: modul ini mengikuti
struktur yang sama persis.

LINGKUP
- Tabel dari 08b area "Stok": stock_movements, stock_balances, stock_reservations,
  stock_events, document_sequences. Enum stock_status, reservation_level,
  reservation_status, dan stock_event_type diambil apa adanya dari Katalog Status;
  jangan menambah nilai.
- Aturan BR-LED-01 s.d. BR-LED-06 di 05-aturan-bisnis, di samping BR-STK yang
  sudah ada.
- Lima layar §6 dan menu "Stok" di sidebar.
- Uji TC-STK-01 s.d. TC-STK-33.

YANG HARUS DIPERHATIKAN
1. StockLedger adalah SATU-SATUNYA pintu tulis saldo (P-01, AD-04). Tidak ada
   jalan lain, termasuk untuk seeder dan uji.
2. Kartu stok append-only. Tegakkan dua lapis: booted() di model DAN trigger
   BEFORE UPDATE / BEFORE DELETE di database. Koreksi hanya lewat baris pembalik.
3. Penunjuk baris pembalik harus ikut saat INSERT, bukan ditulis setelahnya —
   trigger di atas akan menolaknya.
4. Arah pergerakan ditentukan pasangan bin, bukan tanda bilangan. Jumlah selalu
   positif.
5. Kunci baris saldo dengan lockForUpdate dan urutan kunci TETAP (id menaik),
   supaya dua transfer berlawanan arah tidak saling mengunci (NFR-13).
6. Kunci unik saldo menyentuh tiga kolom yang boleh kosong. MySQL memperlakukan
   tiap NULL sebagai nilai berbeda, jadi pakai kolom turunan COALESCE(x, 0).
7. Outbox kejadian ditulis di dalam transaksi yang sama dengan kartu stok
   (BR-LED-06). Kalau kejadiannya gagal, pergerakannya ikut batal.
8. TIDAK ADA permission stock.post. Memposting stok adalah akibat dokumen;
   izinnya melekat pada aksi dokumen masing-masing.
9. Reservasi menggantung DITANDAI, tidak dilepas otomatis (BR-STK-16).
   Pelepasan manual selalu menuntut Alasan (BR-GEN-11).
10. Tutup BR-GEN-04 yang tertunda dari modul Warehouse: gudang, bin, dan item
    bersaldo atau berreservasi aktif tidak boleh dinonaktifkan.

ATURAN KERJA
- Bahasa Indonesia untuk dokumen dan UI; nama di kode Inggris sesuai glosarium.
- Larangan keras: harga (D-07), hapus fisik data yang sudah dipakai (P-03),
  mengubah stok di luar stock_movement (P-01), status di luar katalog, transisi
  status lewat GET.
- Kolom yang tidak ada di ERD harus dicatat sebagai asumsi baru A-xx berstatus
  "Perlu validasi", bukan diam-diam ditambahkan.
- Selesai berarti: php83 artisan test hijau, py -3 docs/diagram/_verify.py OK,
  spesifikasi modul dinaikkan versinya dengan §13 catatan implementasi, dan
  changelog docs/README.md menyebut ID yang berubah.
- Jangan commit ke git kecuali diminta.
```

---

## Hasil

Dikerjakan 24 September 2026. Ringkasannya di [13-stock §13](../wms/13-stock.md) dan [laporan audit](../00-laporan-audit-2026-09-24.md) §4.

Tiga hal yang tidak ada di prompt tetapi muncul saat mengerjakan:

- **Kolom turunan `lot_key`, `serial_key`, `piece_key`** — satu-satunya cara membuat kunci unik saldo benar-benar berlaku di MySQL ketika tiga kolomnya boleh kosong.
- **`MovementRequest::$reversesMovementId`** — dibuat khusus agar penunjuk baris pembalik ikut saat INSERT, karena trigger append-only menolak UPDATE menyusul.
- **Ambang reservasi menggantung** disimpan sebagai pengaturan company `reservation_stale_days` ([A-68](../wms/04-keputusan-dan-asumsi.md#a-68)), bawaan 7 hari.
