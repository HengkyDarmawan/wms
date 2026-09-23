# Prompt: modul Request (Part 8)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Modul ini **sudah selesai**; prompt disimpan sebagai pola untuk modul berikutnya dan sebagai catatan bagaimana modul ini dibangun.

---

```
Bangun modul `request` sesuai docs/wms/14-request.md. Baca dulu CLAUDE.md dan
urutan bacanya, lalu docs/wms/13-stock.md §13 sebagai pola.

LINGKUP
- Dua tabel dari 08b: material_requests dan material_request_lines.
- Enum material_request_status, request_origin, requester_type, line_ownership,
  fulfillment_source, substitution_response diambil apa adanya dari Katalog
  Status §2.1; jangan menambah nilai.
- Aturan BR-REQ-01 s.d. BR-REQ-15 yang sudah ada di 05-aturan-bisnis.
- Lima layar §6, menu "Permintaan", dan dua halaman portal klien.
- Uji TC-REQ-01 s.d. TC-REQ-26.

YANG HARUS DIPERHATIKAN
1. REQ adalah dokumen NIAT. Ia tidak pernah menyentuh kartu stok. Satu-satunya
   sentuhannya ke gudang adalah reservasi lunak lewat ManageReservation saat
   disetujui, dan pelepasannya saat dibatalkan atau ditutup dengan sisa.
2. Hanya baris bersumber `stock` yang direservasi saat approval. Baris bersumber
   transfer atau pembelian belum punya barang untuk dijanjikan.
3. Klien adalah pihak kedua, bukan sekadar pembaca: ia menambah baris, menolak
   penggantian item, dan meminta pembatalan. Setiap interaksi punya tenggat dan
   harus tercatat.
4. Tambahan klien setelah REQ disetujui TIDAK menempel ke REQ itu — ia melahirkan
   REQ Tambahan dengan nomor dan approval sendiri (BR-REQ-12), supaya yang sudah
   dikerjakan gudang tidak berubah di belakang.
5. Diam sampai tenggat penggantian dianggap SETUJU (BR-REQ-13), bukan gagal. REQ
   tidak boleh tertahan menunggu klien yang tidak membuka portal.
6. Pembatalan baris setelah approval dua langkah: klien meminta, staf
   mengonfirmasi (BR-REQ-15). Satu langkah akan membatalkan barang yang mungkin
   sudah disiapkan.
7. BR-REQ-07 (pemohon bukan approver) ditegakkan di policy DAN di kelas aksi.
8. Baris tidak pernah dihapus (P-03). Yang dilepas dari form ditandai cancelled.
9. Kolom di luar ERD harus dicatat di §13.1, bukan diam-diam ditambahkan.

ATURAN KERJA
- Bahasa Indonesia untuk dokumen dan UI; nama di kode Inggris sesuai glosarium.
- Larangan keras: harga (D-07), hapus fisik data yang sudah dipakai (P-03),
  mengubah stok di luar stock_movement (P-01), status di luar katalog, transisi
  status lewat GET.
- Selesai berarti: php83 artisan test hijau, py -3 docs/diagram/_verify.py OK,
  spesifikasi modul dinaikkan versinya dengan §13 catatan implementasi, dan
  changelog docs/README.md menyebut ID yang berubah.
- Jangan commit ke git kecuali diminta.
```

---

## Hasil

Dikerjakan 24 September 2026. Ringkasannya di [14-request §13](../wms/14-request.md) dan [laporan audit](../00-laporan-audit-2026-09-24.md) §6.

Tiga hal yang muncul saat mengerjakan dan tidak ada di prompt:

- **Baris REQ diberi kolom `status` sendiri**, karena baris bisa dibatalkan sendiri-sendiri sementara dokumennya tetap berjalan.
- **Properti Livewire `$lines` di layar portal bentrok** dengan variabel `lines` yang dikirim `render()`; properti dialog diganti nama menjadi `$barisBaru`.
- **`request.approve` hanya dipegang Admin Company** sampai modul `approval` ada; Kepala Gudang meninjau, bukan menyetujui.
