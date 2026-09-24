# Prompt: modul Template dokumen & label — menambah dokumen cetak baru

**Versi:** 0.1 · **Tanggal:** 24 September 2026 · **Status:** modul selesai Fase 1; prompt ini untuk menambah template saat modul lain dibangun · **Dokumen terkait:** [18-template-dokumen-label](../wms/18-template-dokumen-label.md)

**Cara pakai:** buka Claude Code di folder proyek, lalu tempel prompt di bawah. Mesin cetak (`DocumentPrinter`, `PrintLabels`, `PdfRenderer`, layout induk, route `/print/{type}/{id}` dan `/labels`) **sudah ada dan teruji**. Prompt ini hanya untuk menambah jenis dokumen atau label dari modul baru (TRF, RET, ISU, AST, WST, PRQ, label serial/aset).

---

```
Tambahkan template cetak <JENIS> untuk modul <MODUL> sesuai
docs/wms/18-template-dokumen-label.md §5 dan §13.2.
Baca dulu CLAUDE.md dan urutan bacanya, lalu 18 §3–§6 dan §13.

LANGKAH
1. Enum App\Domain\Template\Enums\DocumentTemplateType: tambah case bila
   belum ada (nilai = nama di kode Glosarium). AST/WST sudah ada sebagai
   stub; hapus dari isStub() setelah modulnya jadi. Isi defaultPaper() dan
   defaultSignatureBlocks().
2. DocumentPrinter: tambah kasus di find() (query biasa, TANPA
   withoutGlobalScopes, supaya cakupan BR-ACC-05 berlaku), view(), url(),
   dan satu method pengumpul data. `pelaku` diurutkan sesuai kotak tanda
   tangan bawaan (A-125).
3. View resources/views/print/documents/<nilai_enum>.blade.php yang
   @extends('print.layout'). Pakai PrintFormat::qty() dan
   PrintFormat::tracking(). Tanpa flex/grid (dompdf).
4. Tombol: @include('print.partials.button', [...]) di layar detail
   dokumennya, di sebelah tombol Kembali.
5. Katalog §3 `document_template_type` dan Glosarium bila jenisnya baru.
6. Uji di tests/Feature/Template (TC-TPL-nn berikutnya): PDF 200, isi HTML
   memuat nomor dan baris, tanpa kata harga/Rp/total (D-07), izin lihat
   dan cakupan.

YANG HARUS DIPERHATIKAN
1. Tidak ada permission cetak baru: izin cetak = izin `view` dokumen (A-124).
2. Tidak ada harga atau nilai uang di template mana pun (D-07).
3. Mencetak tetap GET dan hanya membaca; jangan mengubah status saat cetak.
4. Label baru: isi barcode/QR di LabelPayload (A-121), muat datanya di
   PrintLabels::load(), izin lihat di PrintLabels::authorize().
5. Catat penyimpangan di 18 §13, naikkan versi, dan tambahkan changelog di
   docs/README.md.
```
