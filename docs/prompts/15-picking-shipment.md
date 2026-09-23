# Prompt: modul Picking & Shipment (Part 9)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Modul ini **sudah selesai**; prompt disimpan sebagai pola untuk modul berikutnya dan sebagai catatan bagaimana modul ini dibangun.

---

```
Bangun modul `picking` dan `shipment` sesuai docs/wms/15-picking-shipment.md.
Baca dulu CLAUDE.md dan urutan bacanya, lalu docs/wms/14-request.md §13 sebagai
pola.

LINGKUP
- Sepuluh tabel dari 08b: pick_tasks, pick_task_lines, shipments,
  shipment_lines, proofs_of_delivery, proof_of_delivery_lines,
  proof_of_delivery_units, delivery_tokens, delivery_discrepancies,
  delivery_discrepancy_lines.
- Status PCK, SJ, dan DSC diambil apa adanya dari Katalog Status §2.2–§2.4;
  jangan menambah nilai.
- Aturan BR-SJ-01 s.d. BR-SJ-10 yang sudah ada di 05-aturan-bisnis.
- Enam layar §6 dan menu "Pengiriman".
- Uji TC-PCK, TC-SJ, dan TC-DSC.

YANG HARUS DIPERHATIKAN
1. Kurang dan rusak TIDAK hilang dari pembukuan. Keduanya tetap tercatat
   sebagai stok gudang asal di bin Dalam Perjalanan sampai DSC diselesaikan
   (BR-SJ-10). Barang tidak menguap karena penerima menolaknya.
2. Alokasi keras MENGGANTIKAN reservasi lunak REQ, tidak menumpuk di atasnya.
   Lepas yang lunak sebelum membuat yang keras, atau stok terhitung dua kali.
3. Efek `delivered` berbeda per tujuan (BR-SJ-04): ke gudang → tetap Dalam
   Perjalanan sampai GRN tujuan; jual putus → keluar ledger; aset → bin on_site.
4. Bukti terima menuntut SELURUH baris terisi dan baik+rusak+kurang = dikirim.
   Foto wajib bila ada yang rusak.
5. Barang rusak berubah kondisi TANPA berpindah bin. Buku besar perlu
   mendukung bin asal = bin tujuan bila kondisinya berbeda.
6. Sebagian kejadian terbit tanpa pergerakan stok (stock_transferred saat
   diterima gudang tujuan). Tetap lewat outbox, jangan lewat jalur lain.
7. SJ yang sudah `shipped` TIDAK bisa dibatalkan. Koreksi lewat DSC atau retur.
8. Short pick: alasan wajib, bin ditandai count_flag, sisa jadi backorder REQ.
9. Disposisi DSC dan keputusan klien dipisah — keduanya tidak selalu searah.
10. Satu SJ boleh memuat beberapa PCK selama gudang asal dan tujuannya sama.

ATURAN KERJA
- Bahasa Indonesia untuk dokumen dan UI; nama di kode Inggris sesuai glosarium.
- Larangan keras: harga (D-07), hapus fisik data yang sudah dipakai (P-03),
  mengubah stok di luar stock_movement (P-01), status di luar katalog, transisi
  status lewat GET.
- Nama indeks MySQL dibatasi 64 karakter; beri nama pendek untuk tabel panjang.
- Selesai berarti: php83 artisan test hijau, py -3 docs/diagram/_verify.py OK,
  spesifikasi modul dinaikkan versinya dengan §13 catatan implementasi, dan
  changelog docs/README.md menyebut ID yang berubah.
- Jangan commit ke git kecuali diminta.
```

---

## Hasil

Dikerjakan 24 September 2026. Ringkasannya di [15-picking-shipment §13](../wms/15-picking-shipment.md) dan [laporan audit](../00-laporan-audit-2026-09-24.md) §7.

Tiga hal yang muncul saat mengerjakan dan tidak ada di prompt:

- **Buku besar diperluas dua kali**: `MovementRequest::$fromStockStatus` untuk perubahan kondisi di bin yang sama, dan `StockLedger::emitEvent()` untuk kejadian tanpa pergerakan. Keduanya akan dipakai lagi oleh QC di modul `receipt`.
- **Nama indeks MySQL melebihi 64 karakter** dua kali (`proof_of_delivery_lines` dan `delivery_discrepancy_lines`); keduanya diberi nama pendek.
- **Akun klien tidak pernah mencapai route internal** — middleware area memulangkannya ke portal sebelum policy dipanggil, jadi uji cakupan klien memeriksa policy dan redirect, bukan 403.
