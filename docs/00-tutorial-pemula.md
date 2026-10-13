# Tutorial pemula — memakai WMS dari nol, langkah demi langkah

**Versi:** 1.1
**Tanggal:** 13 Oktober 2026
**Status:** aktif — panduan awam untuk Admin Company; pasangan tombol **Tunjukkan caranya** di layar ([A-414](wms/04c-asumsi-lanjutan.md#a-414))
**Dokumen terkait:** [Akun uji](00-akun-uji.md) · [Setup lokal](00-setup-lokal.md) · [Glosarium](wms/03-glosarium.md) · [Blueprint §4.2](wms/01-blueprint.md)

Panduan ini menggantikan urutan tutorial sebelumnya. Setiap langkah punya **Dianggap selesai bila…**: kalau tandanya sudah muncul, lanjut ke langkah berikutnya. **Impor Excel tidak wajib.** Semua data bisa diisi satu per satu; impor hanya jalan pintas bila datanya banyak.

Di layar yang punya tombol **Tunjukkan caranya** (ikon rambu), klik tombol itu. Layar akan diredupkan dan kotak penjelasan menunjuk isian satu per satu. Klik **Lanjut →** untuk pindah, **Selesai** atau Esc untuk menutup.

---

## Tahap 0 — Masuk

1. Buka `http://localhost:8000` (mode satu company, [A-417](wms/04c-asumsi-lanjutan.md#a-417)) — atau `http://demo.wms.test:8000` bila masih per subdomain — lalu klik **Masuk** dan masuk sebagai Admin Company (akun di [00-akun-uji](00-akun-uji.md)). Di server demo, tombol **Coba demo** langsung memasukkan Anda tanpa password.
2. Menu kiri: grup halaman yang sedang dibuka selalu terbuka. Grup lain bisa dibuka atau ditutup dengan klik judulnya.
3. Tombol bulan/matahari di kanan atas mengganti mode gelap/terang.

**Dianggap selesai bila:** halaman Beranda tampil dengan nama Anda di kanan atas.

## Tahap 1 — Setup awal

> **Perusahaan Anda vs Klien.** *Perusahaan Anda* = pemilik gudang dan pengguna WMS ini; namanya diatur di **Pengaturan → Pengaturan company** ([A-419](wms/04c-asumsi-lanjutan.md#a-419)). *Klien* = perusahaan pemberi kerja / pemilik proyek, dicatat di **Master data → Klien**; klien tidak memakai WMS kecuali diberi akun portal.

Menu: **Pengaturan → Setup awal**. Klik **Tunjukkan caranya** untuk penjelasan layar ini.

1. Centang persetujuan ketentuan layanan, lalu klik **Setujui**.
2. Daftar langkah di layar ini adalah peta panduan ini. Tombol **Kerjakan** membuka layar yang tepat.

**Dianggap selesai bila:** langkah 1 tercentang hijau dan angka di kanan atas menjadi 1/9.

## Tahap 2 — Gudang

Menu: **Gudang & stok → Daftar gudang**. Ada **Tunjukkan caranya**.

1. Klik **Tambah gudang**.
2. Isi **Kode** (singkat, mis. `CKG`), **Nama** (mis. Gudang Cikarang), dan **Tipe** (Gudang utama atau cabang). Gudang Site tidak dibuat di sini; Gudang Site lahir dari proyek.
3. Kepala gudang boleh dikosongkan dulu (diisi setelah Tahap 7).
4. Klik **Simpan**.

**Dianggap selesai bila:** gudang baru muncul di tabel.

## Tahap 3 — Zona, rak, dan bin

Klik nama gudang di tabel, lalu buka tab **Zona & rak**. Ada **Tunjukkan caranya**.

Susunannya: **Zona** (area) → **Rak** → **Tingkat** (L1 paling bawah) → **Bin** (petak tempat barang).

1. Klik **Tambah zona**, isi kode `A` dan nama mis. Material besi, lalu Simpan. Isian popup tidak hilang walau popup tertutup.
2. Di bawah zona, klik **Tambah rak di zona A**. Isi kode `R01`, arah, **jumlah tingkat** (mis. 3), dan **bin per tingkat** (mis. 4). Simpan → sistem membuat 12 bin otomatis.
3. Klik **Lihat bin** pada rak untuk melihat petaknya. Dua bin bisa digabung untuk barang besar, dan bisa dipisah lagi.
4. Panah naik/turun mengubah urutan. **Ubah** mengganti nama/kode. **Hapus** hanya bisa bila belum pernah dipakai; selain itu **Nonaktifkan**.

**Dianggap selesai bila:** rak menampilkan jumlah bin (mis. "3 tingkat · 12 bin"), dan langkah *Susun lokasi rak & bin* di Setup awal tercentang.

## Tahap 4 — Satuan dan kategori item

**Satuan** (menu: **Master data → Satuan**). Satuan umum sudah disiapkan otomatis: PCS, BATANG, LBR, SAK, DUS, M, CM, KG, L, M2, dan lain-lain. Biasanya **tidak perlu menambah apa pun**. Bila perlu satuan baru, tulis sebagai kalimat, mis. "1 KM = 1000 M". Ada **Tunjukkan caranya**.

**Kategori item** (menu: **Master data → Kategori item**). Kategori item boleh dibuat, boleh juga dilewati. Contoh: Pipa, Semen, Besi, Alat. Klik tambah, isi kode dan nama, lalu Simpan.

**Dianggap selesai bila:** kategori yang Anda buat muncul di daftar (atau Anda memutuskan tanpa kategori).

## Tahap 5 — Item (barang)

Menu: **Master data → Item → Item baru**. Ada **Tunjukkan caranya**.

Isi 4 hal wajib:

| Isian | Contoh | Penjelasan |
|---|---|---|
| Kode item | `PIPA-PVC4` | Singkat dan unik; tercetak di label |
| Nama item | Pipa PVC 4 inci AW | Nama yang mudah dikenali staf |
| Satuan dasar | `BATANG` atau `M` | Satuan saat stok dihitung dan dikeluarkan |
| Jenis barang | Barang biasa | Barang biasa · Barang berkedaluwarsa · Alat bernomor seri |

Lalu klik **Simpan item**.

Pilihan untuk pipa:
- **Selalu keluar utuh per batang** → satuan dasar `BATANG`. Di tab *Stok & pembelian otomatis*, isi **Ukuran per 1 BATANG** = `6` `M` (hanya informasi).
- **Sering dipotong** → satuan dasar `M`. Di tab **Kemasan** tulis `1 BATANG = 6 M` dan centang **Panjang nominal batang utuh**. Centang ini hanya muncul bila saklar **Per potong** sudah dinyalakan di **Pengaturan → Pengaturan company**. Pemotongan dicatat lewat **Di proyek → Konversi material**.

Tab **Stok & pembelian otomatis** (opsional):
- **Titik pesan ulang** dan **Isi sampai (stok ideal)** mengatur pembelian otomatis. Contoh: titik 20, isi sampai 100 → bila stok tinggal 10, sistem menyiapkan draf pembelian 90.

Tab **Kemasan** (opsional): mis. 1 DUS = 12 PCS untuk barang ini.

**Dianggap selesai bila:** muncul tulisan **Item disimpan** di halaman detail item, dan langkah *Daftarkan item* di Setup awal tercentang.

## Tahap 6 — Tempat simpan barang (opsional)

Di halaman detail item, kartu **Tempat simpan** → pilih gudang → **Pilih di denah…** → klik rak di gambar, ketuk petak bin → **Tambah** → **Simpan**. Dipakai untuk saran bin saat barang masuk.

**Dianggap selesai bila:** kartu Tempat simpan menampilkan bin/rak pilihan dan denah kecilnya menyorot tempat itu.

## Tahap 7 — Akun Kepala Gudang dan Staf Gudang

Menu: **Pengaturan → Pengguna**. Akun dasar per jabatan sudah dibuat tetapi **nonaktif**.

1. Buka akun Kepala Gudang → **Ubah**: ganti nama dan email ke orang sebenarnya → Simpan.
2. Di detail akunnya klik **Aktifkan & buatkan password** ([A-420](wms/04c-asumsi-lanjutan.md#a-420)). Password tampil sekali — klik **Salin**.
3. Ulangi untuk Staf Gudang.
4. Kembali ke gudang (Tahap 2), isi **Kepala gudang** dengan akun tadi.

**Dianggap selesai bila:** kedua akun berstatus Aktif dan bisa masuk di jendela penyamaran (incognito).

## Tahap 8 — Vendor

Menu: **Master data → Vendor**. Klik tambah, isi kode, nama, dan jenis vendor. Kontak dan telepon boleh diisi. Lalu Simpan.

**Dianggap selesai bila:** vendor muncul di daftar.

## Tahap 9 — Stok pertama

Pilih **salah satu**:

**A. Barang sudah ada di gudang (saldo awal).** Buka **Opname & penyesuaian → Penyesuaian stok** dengan alasan **Saldo awal**. Tombol *Kerjakan* di Setup awal langsung membukanya.
1. Pilih gudang.
2. Isi baris barang, jumlah, dan bin.
3. Klik **Ajukan penyesuaian**. Kepala Gudang menyetujuinya di **Tugas approval saya**.

**B. Barang baru datang dari vendor.** Buka **Barang masuk → Penerimaan barang** → tambah. Ada **Tunjukkan caranya**.
1. Sumber **Vendor**, gudang penerima, vendor, lalu baris barang dan jumlahnya. Klik **Simpan draf**.
2. Di halaman detail klik **Terima & selesaikan** ([A-420](wms/04c-asumsi-lanjutan.md#a-420)). Bila diminta jumlah dus, isi lalu **Selesaikan & buat label**. Barang yang perlu QC berhenti di *Diterima* — isi hasil QC dulu, lalu **Selesaikan**.
3. Buka **Barang masuk → Tugas put-away**, buka tugasnya, pindai atau pilih bin, lalu klik **Selesaikan put-away**.

**Dianggap selesai bila:** di **Gudang & stok → Saldo stok** barang tampil dengan jumlah dan lokasi bin yang benar.

## Tahap 10 — Klien dan proyek

1. **Master data → Klien**: tambah klien (perusahaan pemberi kerja) dan PIC-nya.
2. **Proyek → Proyek**: tambah proyek, pilih klien. Gudang Site proyek dibuat dari sini bila perlu.

**Dianggap selesai bila:** proyek muncul di daftar, dan langkah *Daftarkan klien & proyek* di Setup awal tercentang.

## Tahap 11 — Barang keluar ke proyek

1. **Barang keluar → Permintaan material** → buat permintaan untuk proyek, isi barang dan jumlahnya, lalu **Kirim ke persetujuan**.
2. Penyetuju membuka **Tugas approval saya** → **Setujui**.
3. Tugas picking dibuat **otomatis** begitu disetujui (saklar *Picking otomatis* di Pengaturan company, [A-420](wms/04c-asumsi-lanjutan.md#a-420)); bila stok belum cukup, klik **Buat tugas picking** di detail permintaan nanti. Staf membuka **Barang keluar → Tugas picking** → **Mulai picking** → **Selesaikan picking**.
4. **Barang keluar → Surat jalan** → buat dari hasil picking → **Kirim**.

**Dianggap selesai bila:** surat jalan berstatus terkirim dan saldo stok gudang berkurang sesuai jumlah yang dikirim.

## Tahap 12 — Menutup setup

Kembali ke **Pengaturan → Setup awal**. Bila semua langkah wajib tercentang, klik **Tandai setup selesai**.

**Dianggap selesai bila:** lencana di kanan atas berwarna hijau.

---

## Bila ada kendala

| Gejala | Coba ini |
|---|---|
| Tulisan di layar belum berubah setelah pembaruan | Tekan Ctrl+F5 (muat ulang penuh) |
| Halaman terasa lambat di komputer kantor | Pastikan OPcache aktif ([00-setup-lokal §4](00-setup-lokal.md#4-menjalankan)) |
| Tombol tidak bisa diklik / pesan "tidak berwenang" | Akun belum punya role atau cakupan gudang; cek di **Pengaturan → Pengguna** |
| Tidak menemukan menu | Ketik namanya di kotak **Cari menu…** di atas menu kiri |
