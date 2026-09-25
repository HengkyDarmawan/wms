# Spesifikasi Modul — Landing page produk

**Versi:** 0.1
**Tanggal:** 25 September 2026
**Status:** selesai Fase 1 (Part 5); keputusan yang tidak tertulis di dokumen dicatat sebagai [A-220](04-keputusan-dan-asumsi.md#a-220)–[A-225](04-keputusan-dan-asumsi.md#a-225) (*Perlu validasi*)
**Modul:** `central` (landing page di domain pusat)
**Fase:** F1
**Dokumen terkait:** [Blueprint §1, §2, §4, §14, §17, §18](01-blueprint.md) · [Riset §2.7](02-riset-wms-sejenis.md#27-pasar-indonesia) · [D-02](04-keputusan-dan-asumsi.md#d-02), [D-06](04-keputusan-dan-asumsi.md#d-06), [D-07](04-keputusan-dan-asumsi.md#d-07), [D-26](04-keputusan-dan-asumsi.md#d-26) · [O-07](04-keputusan-dan-asumsi.md#o-07), [O-08](04-keputusan-dan-asumsi.md#o-08), [O-11](04-keputusan-dan-asumsi.md#o-11) · [Glosarium](03-glosarium.md)
**Ketergantungan modul:** [Platform](17-platform-login.md) (tabel `plans`, pembuatan company [A-176](04-keputusan-dan-asumsi.md#a-176)), login tenant & portal klien ([Access](10-access.md)).

---

## 1. Tujuan & lingkup

Landing page memperkenalkan produk kepada calon pelanggan — perusahaan penyedia material, mesin, dan alat proyek — dengan posisi **"WMS khusus material proyek"** ([Riset §2.7](02-riset-wms-sejenis.md#27-pasar-indonesia)): konversi material berukuran/potongan, stok per proyek & Gudang Site, pemakaian material, aset dipinjamkan, dan portal klien. Halaman juga menjadi pintu bagi pengguna yang sudah berlangganan untuk menuju halaman masuk di subdomain company-nya.

Gaya visual mengikuti [D-06](04-keputusan-dan-asumsi.md#d-06) (terinspirasi indonesia.travel): hero penuh layar, tipografi besar & tebal, ruang lega, kartu bergambar membulat, rel kartu mendatar, aksen hangat, navigasi lengket tembus pandang.

**Tidak termasuk:** pendaftaran mandiri / trial mandiri (company dibuat Super Admin, [A-176](04-keputusan-dan-asumsi.md#a-176), [A-220](04-keputusan-dan-asumsi.md#a-220)), formulir prospek yang disimpan di database, blog, halaman kebijakan privasi & ketentuan layanan ([O-11](04-keputusan-dan-asumsi.md#o-11), [A-225](04-keputusan-dan-asumsi.md#a-225)), dan tautan ke login Super Admin.

## 2. Aktor & permission

| Role | Permission | Cakupan |
|---|---|---|
| Pengunjung publik | — (tanpa login) | domain pusat saja (`wms.test`, alias `www.`/`auth.`/`127.0.0.1`/`localhost`) |
| Pengguna company / klien | — | memakai formulir *Masuk ke company Anda*, lalu login di subdomain company ([D-26](04-keputusan-dan-asumsi.md#d-26)) |

Tidak ada permission baru. Halaman tidak membaca sesi login apa pun.

## 3. Entitas & data

Landing hanya **membaca** database pusat; tidak ada tabel baru dan tidak ada penulisan.

| Sumber | Kolom dipakai | Keterangan |
|---|---|---|
| `plans` (pusat) | `code`, `name`, `monthly_price`, `trial_days`, `storage_quota_mb`, `is_active` | hanya `is_active = true`, urut harga lalu nama ([A-222](04-keputusan-dan-asumsi.md#a-222)). Harga = harga langganan platform ([D-02](04-keputusan-dan-asumsi.md#d-02)), bukan nilai barang ([D-07](04-keputusan-dan-asumsi.md#d-07)) |
| `config/wms.php` → `wms.sales_email` | env `WMS_SALES_EMAIL`, bawaan `halo@wms.test` | tujuan tombol *Minta demo* ([A-220](04-keputusan-dan-asumsi.md#a-220)) |
| `config('app.name')`, `public/img/logo.svg` | — | nama & logo sementara sampai [O-07](04-keputusan-dan-asumsi.md#o-07) |
| `config('tenancy.central_domains.0')` | — | domain dasar untuk alamat company |

## 4. Mesin status

Tidak ada. Landing tidak punya dokumen atau status.

## 5. Aturan bisnis yang berlaku

| Aturan | Catatan implementasi |
|---|---|
| [D-07](04-keputusan-dan-asumsi.md#d-07) | Tidak ada harga/nilai barang. Satu-satunya angka rupiah adalah harga paket langganan dari `plans.monthly_price`. FAQ menjelaskan bahwa WMS hanya kuantitas |
| [D-02](04-keputusan-dan-asumsi.md#d-02) | Paket ditampilkan "flat per company", tanpa batas user/gudang/proyek |
| [D-06](04-keputusan-dan-asumsi.md#d-06) | Ilustrasi dibuat sendiri (SVG di `public/img/landing/` dan SVG inline); tidak ada gambar dari luar ([A-223](04-keputusan-dan-asumsi.md#a-223)) |
| [BR-GEN-10](05-aturan-bisnis.md#br-gen) | Fitur `[F2]` hanya disebut "menyusul" (approval WhatsApp, offline penuh); tidak diiklankan sebagai tersedia |
| [NFR-02](01-blueprint.md#16-kebutuhan-non-fungsional) | `GET /masuk` hanya mengalihkan, tidak mengubah data ([A-221](04-keputusan-dan-asumsi.md#a-221)) |
| [NFR-04](01-blueprint.md#16-kebutuhan-non-fungsional) | `GET /masuk` dibatasi `throttle:60,1` |
| [NFR-08](01-blueprint.md#16-kebutuhan-non-fungsional) | Semua teks lewat `__()` agar siap diterjemahkan |
| [NFR-09](01-blueprint.md#16-kebutuhan-non-fungsional), [NFR-10](01-blueprint.md#16-kebutuhan-non-fungsional) | Responsif sampai lebar 390 px tanpa scroll mendatar; skip link, landmark (`header`, `nav`, `main`, `footer`), `aria-labelledby` per bagian, teks alternatif ilustrasi, fokus terlihat, `prefers-reduced-motion` mematikan animasi & scroll halus |
| Blueprint §17 | Blade + Alpine.js; semua aset di-bundle Vite tanpa CDN ([A-224](04-keputusan-dan-asumsi.md#a-224)) |

## 6. Layar

### 6.1 Landing — `GET /` (domain pusat) — `Central\LandingController@show`, view `central.landing`

Layout sendiri `layouts/landing` dengan bundel `resources/css/landing.css` + `resources/js/landing.js` (Bootstrap CSS, Bootstrap Icons, Inter lewat `@fonts`, Alpine.js). **Tidak** memuat `app.js`: tanpa jQuery, tanpa shell NexaDash, tanpa Livewire, tanpa manifest & service worker PWA ([A-224](04-keputusan-dan-asumsi.md#a-224)).

| Urutan | Bagian (anchor) | Isi | Partial `central/landing/` |
|---|---|---|---|
| 1 | Navigasi | logo + nama, tautan Fitur · Cara kerja · Untuk siapa · Paket · FAQ, tombol tema (`#lpThemeToggle`), *Masuk* (→ `#masuk`), *Minta demo* (mailto). Transparan di atas hero, tembus pandang (blur) setelah digulir; di HP menu lipat Alpine, `Esc` menutup | `nav` |
| 2 | Hero (`#hero`) | h1 "Stok yang bisa dipercaya, dari gudang sampai site.", ringkasan produk, *Minta demo*, *Lihat cara kerja*, tiga poin (flat per company, user & gudang tanpa batas, database terpisah); ilustrasi `img/landing/hero.svg` gudang → truk → site | `hero` |
| 3 | Sasaran (`#tujuan`) | empat target Blueprint §2 ditulis sebagai **sasaran**, bukan klaim ([A-223](04-keputusan-dan-asumsi.md#a-223)) | `goals` |
| 4 | Fitur (`#fitur`) | rel kartu mendatar (scroll-snap, tombol geser kiri/kanan, bisa difokus & digulir keyboard): Konversi & potongan · Stok per proyek & Gudang Site · Pemakaian material · Aset dipinjamkan · Portal klien · Approval berlapis · Stock opname hitung buta · PWA & scan kamera | `features` |
| 5 | Sorotan konversi (`#konversi`) | diagram SVG inline: batang 6,00 m → 2 × 2,50 m + offcut 1,00 m → 0,90 m + waste 0,10 m; butir offcut/waste/silsilah/laporan waste | `conversion` |
| 6 | Cara kerja (`#cara-kerja`) | enam langkah: Permintaan Material `REQ` → Approval → Tugas Picking `PCK` → Surat Jalan `SJ` → Bukti Terima → Pemakaian Material `ISU` | `steps` |
| 7 | Untuk siapa (`#untuk-siapa`) | enam role bawaan (Kepala Gudang, Staf Gudang, Driver, Pemohon Internal, Klien, Manajemen) dengan kanal kerjanya (Blueprint §4.2) | `roles` |
| 8 | Paket (`#paket`) | kartu per paket aktif (§6.1a); tanpa paket → kartu "Paket sedang disiapkan" + *Hubungi kami* | `plans` |
| 9 | Masuk (`#masuk`) | formulir *Masuk ke company Anda* (§6.2) | `enter` |
| 10 | FAQ (`#faq`) | akordeon Alpine (satu terbuka, `aria-expanded`/`aria-controls`); tanpa JS semua jawaban terbaca. Termasuk "Apakah ada harga barang di WMS?" → tidak ([D-07](04-keputusan-dan-asumsi.md#d-07)) | `faq` |
| 11 | Ajakan (`#demo`) | "Siap merapikan stok proyek Anda?" + *Minta demo* + alamat email | `cta` |
| 12 | Kaki halaman | nama & tagline, tautan bagian, kontak, hak cipta; tanpa tautan kebijakan sampai [O-11](04-keputusan-dan-asumsi.md#o-11) | `footer` |

**Tema:** kunci `localStorage` `nx-theme` sama dengan aplikasi; tanpa pilihan tersimpan mengikuti `prefers-color-scheme`. Skrip kecil di `<head>` memasang `data-bs-theme` sebelum paint. Hero dan pita ajakan selalu gelap/berwarna di kedua tema.

**Keadaan error:** bila database pusat tidak bisa dibaca, error dilaporkan (`report()`) dan halaman tetap tampil dengan kartu "Paket sedang disiapkan" ([A-222](04-keputusan-dan-asumsi.md#a-222)).

#### 6.1a Kartu paket

| Elemen | Aturan |
|---|---|
| Nama | `plans.name` |
| Harga | `monthly_price > 0` → "Rp 1.500.000 / bulan per company" (pemisah ribuan titik); `= 0` → "Hubungi kami" ([O-08](04-keputusan-dan-asumsi.md#o-08)) |
| Butir | Semua fitur WMS di setiap paket · User, gudang, dan proyek tanpa batas · Portal klien & PWA · Penyimpanan berkas *n* GB (bila `storage_quota_mb`) · Trial *n* hari (bila `trial_days > 0`). Kuota WhatsApp tidak ditampilkan sampai `[F2]` |
| Tombol | mailto `wms.sales_email` dengan subjek "Paket <nama> — <app>"; label *Minta demo* (berharga) / *Hubungi kami* (harga 0) |

### 6.2 Masuk ke company — `GET /masuk?company=&as=` — `Central\LandingController@enter`

| Field | Tipe | Wajib | Validasi | Keterangan |
|---|---|---|---|---|
| `company` | teks + akhiran `.<domain pusat>` | `*` | dipangkas & huruf kecil; 1–63 karakter huruf kecil, angka, atau tanda hubung, tidak diawali/diakhiri tanda hubung; bukan subdomain cadangan `CreateCompany::RESERVED_SUBDOMAINS` | sama dengan aturan subdomain [A-176](04-keputusan-dan-asumsi.md#a-176) |
| `as` | radio | `*` | `team` \| `portal` | *Tim company* → `/login`; *Klien (portal)* → `/portal/login` ([A-48](04-keputusan-dan-asumsi.md#a-48)) |

- Sah → `302` ke `<skema>://<company>.<domain pusat utama>[:port]/login` (atau `/portal/login`); skema & port mengikuti permintaan. Keberadaan company **tidak** diperiksa ([A-221](04-keputusan-dan-asumsi.md#a-221)).
- Tidak sah → `302` ke `/#masuk` dengan pesan galat & isian lama.
- Alpine menampilkan pratinjau alamat tujuan (`aria-live`) dan menahan kirim bila format salah; tanpa JS formulir tetap berjalan (validasi server).

## 7. Kejadian stok & integrasi

Tidak ada. Landing tidak menyentuh database tenant maupun `stock_movement`.

## 8. Notifikasi

Tidak ada. *Minta demo* membuka aplikasi email pengunjung (mailto); tidak ada email yang dikirim server ([A-220](04-keputusan-dan-asumsi.md#a-220)).

## 9. Laporan & dashboard

Tidak ada. Analitik kunjungan tidak dipasang di Fase 1 ([A-225](04-keputusan-dan-asumsi.md#a-225)).

## 10. Kasus uji (Given / When / Then)

Uji fitur: `tests/Feature/Platform/LandingPageTest.php`. Uji peramban: `tests/e2e/ui-check.mjs` bagian 7a.

| ID | Given | When | Then | Aturan |
|---|---|---|---|---|
| TC-LND-01 | domain pusat | `GET /` | 200; nama produk, h1 hero, judul bagian Fitur, Konversi, Cara kerja, Untuk siapa, Paket, Masuk, FAQ; tautan mailto `wms.sales_email` | §6.1 |
| TC-LND-02 | paket aktif harga 0, paket aktif Rp 1.500.000 trial 30 hari, paket nonaktif | `GET /` | harga 0 → "Hubungi kami"; "Rp 1.500.000"; "Trial 30 hari"; paket nonaktif tidak tampil | [A-222](04-keputusan-dan-asumsi.md#a-222), [D-07](04-keputusan-dan-asumsi.md#d-07) |
| TC-LND-03 | — | `GET /` | tidak ada `manifest.webmanifest`, `serviceWorker`, `sw.js`, `livewire`, `wire:`, `/admin/login` | [A-224](04-keputusan-dan-asumsi.md#a-224), [A-221](04-keputusan-dan-asumsi.md#a-221) |
| TC-LND-04 | — | `GET /masuk?company=Demo&as=team`, `…demo&as=portal`, `…belum-ada-123` | 302 ke `http://demo.<domain>/login`, `…/portal/login`, dan `http://belum-ada-123.<domain>/login` (tanpa cek keberadaan) | [A-221](04-keputusan-dan-asumsi.md#a-221) |
| TC-LND-05 | — | `GET /masuk` dengan `''`, `www`, `admin`, `nama_salah`, `-awal`, `akhir-`, `a.b`, `evil.com/x`; lalu `as=admin` | 302 ke `…#masuk` dengan galat `company` / `as`; tidak pernah mengalihkan ke host lain | [A-176](04-keputusan-dan-asumsi.md#a-176) |
| TC-LND-06 | — | periksa route `central.enter` | hanya `GET`/`HEAD` | [NFR-02](01-blueprint.md#16-kebutuhan-non-fungsional) |
| E2E-LND | Chrome headless, server lokal | buka `/` pada 1366 px, ganti tema, lalu 390 × 800 | h1 hero ada, 0 error console, tidak ada service worker, tema berganti, `scrollWidth ≤ clientWidth` dan `innerWidth ≤ 390` | NFR-09, NFR-10 |

## 11. Di luar lingkup modul ini

- Pendaftaran & trial mandiri, pembayaran online — company dibuat Super Admin ([A-176](04-keputusan-dan-asumsi.md#a-176)); payment gateway `[F3]` (Blueprint §14).
- Nama produk, logo, dan harga final — menunggu [O-07](04-keputusan-dan-asumsi.md#o-07) dan [O-08](04-keputusan-dan-asumsi.md#o-08); landing memakai `app.name`, `logo.svg`, dan isi tabel `plans` sehingga cukup diganti tanpa mengubah kode.
- Kebijakan privasi & ketentuan layanan publik — [O-11](04-keputusan-dan-asumsi.md#o-11).
- Pemilih company untuk SSO ([A-48](04-keputusan-dan-asumsi.md#a-48)) — `[F3]`, modul Access.
- Versi bahasa Inggris — teks sudah lewat `__()`, berkas terjemahan menyusul.

## 12. Definisi selesai

- [x] Route `central.home` & `central.enter` (nama hanya di domain pusat pertama, domain lain alias)
- [x] Bundel Vite terpisah `landing.css`/`landing.js` tanpa CDN; `alpinejs` sebagai dependensi npm
- [x] Semua TC ditulis (`LandingPageTest`) dan cek E2E ditambahkan
- [x] Tidak ada harga barang; harga hanya paket langganan
- [x] Responsif 390 px, tema terang/gelap, aksesibilitas dasar
- [x] Dokumen ini dibuat; asumsi [A-220](04-keputusan-dan-asumsi.md#a-220)–[A-225](04-keputusan-dan-asumsi.md#a-225) dicatat

## 13. Catatan implementasi (25 September 2026)

| Berkas | Isi |
|---|---|
| `routes/web.php` | `GET /` → `LandingController@show`; `GET /masuk` → `@enter` (`throttle:60,1`) |
| `app/Http/Controllers/Central/LandingController.php` | memuat paket, email penjualan, domain & port; validasi dan pengalihan `/masuk` |
| `config/wms.php` | `sales_email` (env `WMS_SALES_EMAIL`) |
| `resources/views/layouts/landing.blade.php` | kepala halaman, meta OG, skrip tema, `@fonts`, `@vite` landing |
| `resources/views/central/landing.blade.php` + `central/landing/*.blade.php` | 12 partial sesuai §6.1 |
| `resources/css/landing.css`, `resources/js/landing.js` | token warna terang/gelap, komponen landing; komponen Alpine `landingNav`, `rail`, `companyEnter` |
| `public/img/landing/hero.svg` | ilustrasi hero buatan sendiri |
| `resources/views/central/welcome.blade.php` | dihapus (diganti landing) |
