# Prompt: modul Warehouse (Part 6)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Modul ini **sudah selesai**; prompt disimpan sebagai pola untuk modul berikutnya dan sebagai catatan bagaimana modul ini dibangun.

---

```
Bangun modul `warehouse` sesuai docs/wms/12-warehouse.md. Baca dulu CLAUDE.md dan
urutan bacanya, lalu docs/wms/11-master.md §13 sebagai pola: modul ini mengikuti
struktur yang sama persis.

LINGKUP
- Enam tabel dari 08a area "Gudang & lokasi": warehouse_types, warehouses, zones,
  racks, rack_levels, bins. Enum bin_type dan bin_status diambil apa adanya dari
  Katalog Status §3; jangan menambah nilai.
- Aturan BR-WH-01 s.d. BR-WH-07 di 05-aturan-bisnis §13c.
- Empat layar §6 dan menu "Gudang" di sidebar.
- Seeder tipe gudang bawaan dan gudang demo CKG/BKS/KRW1/KRW2 sesuai
  docs/00-akun-uji.md §2.
- Uji TC-WH-01 s.d. TC-WH-20.

YANG HARUS DIPERHATIKAN
1. Modul ini pemakai pertama trait ScopedToUser. Pasang di Warehouse dan Bin,
   lalu pastikan arti "cakupan kosong" konsisten dengan policy dan layar.
2. Bin bawaan dan bin virtual dibuat otomatis setiap kali gudang disimpan
   (BR-WH-02), dan aksinya harus aman dijalankan berulang.
3. Kode bin diturunkan dari hierarki dan terkunci setelah dibuat (BR-WH-01),
   karena kode itu tercetak di label dan tersimpan di riwayat mutasi.
4. Satu bin on_site per PROYEK, bukan per gudang: Blueprint §6.3 menulis
   "satu per proyek" sedangkan A-40 memperbolehkan satu proyek punya beberapa
   Gudang Site.
5. Gudang bertipe site wajib punya proyek; tipe lain wajib tidak punya (BR-WH-04).
6. Hierarki gudang tidak boleh melingkar (BR-WH-05).
7. Setelah modul ini ada, ganti cakupan gudang di form pengguna dari id angka
   menjadi daftar nama, dan hapus konstanta id gudang sementara di DemoSeeder.

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

Dikerjakan 24 September 2026. Ringkasannya di [12-warehouse §13](../wms/12-warehouse.md) dan [laporan audit](../00-laporan-audit-2026-09-24.md) §4.

Dua hal yang tidak ada di prompt tetapi muncul saat mengerjakan, keduanya dicatat sebagai asumsi:

- **`bins.count_flag`** ([A-67](../wms/04-keputusan-dan-asumsi.md#a-67)) — istilahnya sudah ada di Glosarium dan dipakai BR-SJ-02, tetapi kolomnya tidak pernah ada di model data.
- **`bins.freeze_reason`** — alasan pembekuan disimpan bersama binnya, bukan hanya di audit log, supaya layar bisa menampilkannya tanpa membaca log.
