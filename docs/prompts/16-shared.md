# Prompt: modul Shared — laporan yang tersisa

**Versi:** 0.1 · **Tanggal:** 24 September 2026 · **Status:** kerangka sudah ada, laporan modul Stock/Request/Picking-Shipment belum · **Dokumen terkait:** [16-shared-laporan-berkas](../wms/16-shared-laporan-berkas.md)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Kerangka laporan (`Report`, `ReportRegistry`, `ReportExport`, `ReportViewer`, route `/reports`) dan `StoreUpload` **sudah ada dan teruji**; prompt ini hanya untuk melanjutkan laporan yang belum dibangun.

---

```
Lanjutkan modul `shared` sesuai docs/wms/16-shared-laporan-berkas.md §13.4.
Baca dulu CLAUDE.md dan urutan bacanya, lalu 16-shared §3 dan §13.

LINGKUP
- Tambahkan definisi laporan di app/Domain/Shared/Reports/Definitions dan
  daftarkan di ReportRegistry::REPORTS. JANGAN membuat route, controller,
  atau komponen Livewire baru.
- Laporan §9 dari 13-stock (Saldo stok, Kartu stok, Reservasi menggantung,
  Stok di bawah titik pesan ulang), 14-request (Daftar REQ, REQ menunggu
  tinjau, Baris tanpa sumber, Penggantian item menunggu tanggapan), dan
  15-picking-shipment (Daftar pengiriman, Short pick, Posisi barang rusak &
  selisih, Kinerja pengiriman). Kolom dan penyaring diambil dari §9 masing-
  masing; bila menyimpang, catat di §13.1 16-shared.
- Uji lanjutan TC-RPT-02 dst. di tests/Feature/Shared/ReportTest.php.
  Perbarui TC-RPT-01 (jumlah laporan terdaftar).

YANG HARUS DIPERHATIKAN
1. Permission memakai `<modul>.view` yang sudah ada di modul sumbernya;
   jangan membuat permission baru tanpa mencatatnya di spesifikasi modul.
2. Query data utama TANPA withoutGlobalScopes(), supaya cakupan gudang/
   proyek user (BR-ACC-05) berlaku di layar dan di ekspor.
3. Saldo dibaca dari tabel saldo modul Stock; laporan tidak pernah menulis
   stok (P-01) dan tidak memuat nilai uang (D-07).
4. Hindari N+1: hitung agregat sekali (lihat ProjectListReport).
5. Layar memotong 500 baris; ekspor memuat semua. Laporan yang bisa sangat
   besar (kartu stok) wajib punya penyaring rentang tanggal.

OPSIONAL (tanyakan dulu)
- Ekspor PDF (Blueprint §6.9a), 404 untuk kunci laporan tidak dikenal, dan
  penyaring §9 yang hilang (16-shared §13.1).

ATURAN KERJA
- Bahasa Indonesia untuk dokumen dan UI; nama kode Inggris sesuai glosarium.
- Selesai berarti: php83 artisan test hijau, py -3 docs/diagram/_verify.py OK,
  16-shared dinaikkan versinya dengan §13 diperbarui, dan changelog
  docs/README.md menyebut ID yang berubah.
- Jangan commit ke git kecuali diminta.
```
