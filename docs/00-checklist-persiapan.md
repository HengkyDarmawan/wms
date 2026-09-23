# Checklist Persiapan Sebelum Part 4 & Coding

**Versi:** 1.0
**Tanggal:** 23 September 2026
**Status:** aktif; dicentang oleh pemilik produk
**Dokumen terkait:** [README](README.md) · [Keputusan & Asumsi](wms/04-keputusan-dan-asumsi.md) · [Arsitektur §10, §12](wms/08-arsitektur.md#10-lingkungan) · [Blueprint §13, §18](wms/01-blueprint.md#13-autentikasi--sso)

Peta rilis yang disepakati 23 Sep 2026 ([D-29](wms/04-keputusan-dan-asumsi.md#d-29)): **Fase 1** WMS inti → **Fase 1b** Purchasing inti → **Fase 2a** WhatsApp → **Fase 2b** PWA offline penuh → **Fase 3** SSO, Purchasing lengkap, payment gateway, API. Login **lokal dulu** ([D-26](wms/04-keputusan-dan-asumsi.md#d-26)); SSO NXTG menyusul sebagai tombol tambahan di halaman login yang sama.

---

## 1. Keputusan yang harus diambil pemilik produk sebelum Part 4

| ☐ | Keputusan | Untuk apa | Rujukan |
|---|---|---|---|
| ☐ | **Migrasi data prototipe:** impor Excel dari data lama, atau mulai dari saldo awal lewat opname pembukaan | Modul Master & Stock; format template impor | [O-12](wms/04-keputusan-dan-asumsi.md#o-12) |
| ☐ | **Tanda tangan digital:** cukup gambar tanda tangan + jejak audit (foto, GPS, waktu), atau penyedia tersertifikasi | Bukti terima, berita acara | [O-13](wms/04-keputusan-dan-asumsi.md#o-13) |
| ☐ | **OTP bukti terima tanpa akun:** SMS gateway mana selama WhatsApp belum aktif (atau tautan tanpa OTP dengan risiko diterima) | Modul Shipment | [O-15](wms/04-keputusan-dan-asumsi.md#o-15) |
| ☐ | **A-50** jalur ringan transfer dalam proyek (setuju / ubah) | Modul Transfer | [A-50](wms/04-keputusan-dan-asumsi.md#a-50) |
| ☐ | Keterangan temuan prototipe yang masih kosong | Konteks Part 4 | [00-audit](00-audit/README.md) |

## 2. Bahan & akses sebelum coding

| ☐ | Bahan | Untuk apa | Rujukan | Catatan |
|---|---|---|---|---|
| ✔ | **Template NexaDash** | Semua layar back-office | [D-05](wms/04-keputusan-dan-asumsi.md#d-05) | Sudah ada di `template/` (249 halaman statis, Bootstrap 5.3 + jQuery 3.7; `partials/` menjadi layout Blade; jQuery hanya untuk plugin, bukan logika Livewire). Folder ini referensi UI, tidak di-deploy |
| ☐ | Repo git remote (GitHub/GitLab) + akses tim | Kerja bersama, CI | — | Cabang `main` terlindungi; PR per modul |
| ☐ | **Data contoh satu klien pilot** dalam Excel: item (dengan satuan & mode pelacakan), gudang & lokasi, proyek, user & role, vendor | Uji impor Excel dan alur nyata sejak modul Master | Blueprint §6.3a | Cukup 20–50 item, 2 gudang, 1 proyek |
| ☐ | Domain produksi + VPS/hosting + **SSL wildcard** untuk subdomain company | Tenancy per subdomain | [O-05](wms/04-keputusan-dan-asumsi.md#o-05) | Lokal memakai `*.wms.test` (08 §10) |
| ☐ | Penyedia **email transaksional** (undangan, reset password, tagihan) | Modul Access & Platform | [O-14](wms/04-keputusan-dan-asumsi.md#o-14) | SMTP/API + domain terverifikasi (SPF/DKIM) |
| ☐ | Penyimpanan berkas **S3-compatible** (foto, tanda tangan, lampiran) | Semua dokumen | [O-14](wms/04-keputusan-dan-asumsi.md#o-14) | Bucket produksi & staging terpisah |
| ☐ | Server **staging** (spesifikasi = produksi, data anonim) | Uji restore backup & migrasi tenant | 08 §10 | Boleh satu VPS kecil di awal |
| ☐ | Nama produk, logo, harga paket | Landing page | [O-07](wms/04-keputusan-dan-asumsi.md#o-07), [O-08](wms/04-keputusan-dan-asumsi.md#o-08) | Baru dibutuhkan di Part 5 |

## 3. Tidak perlu disiapkan sekarang

| Hal | Fase | Yang cukup dilakukan sekarang |
|---|---|---|
| SSO NXTG | 3 | Beri tahu tim SSO bahwa WMS nanti butuh **satu redirect URI pusat** dan role `KLIEN` ([O-02](wms/04-keputusan-dan-asumsi.md#o-02)); halaman login lokal dibuat sekarang, tombol "Masuk dengan NXTG" ditambahkan kemudian |
| WhatsApp Cloud API | 2a | Tidak perlu daftar sekarang (keputusan 23 Sep 2026); mulai checklist §5 pada hari pertama Fase 2a karena verifikasi Meta memakan waktu |
| RFID, cycle count ABC, maintenance | 2b | — |
| Payment gateway, REST API | 3 | — |

## 4. Halaman login: lokal dulu, SSO menyusul

Pemetaan halaman auth di `template/auth/` ke Fase 1 ([Blueprint §13](wms/01-blueprint.md#13-autentikasi--sso)):

| Halaman template | Dipakai untuk | Fase |
|---|---|---|
| `login.html` | Login lokal per subdomain company (email + password, ingat saya); portal klien memakai halaman yang sama di `/portal` | 1 |
| `forgot-password.html`, `reset-password.html` | Lupa & atur ulang password | 1 |
| `verify-email.html` | Menerima undangan user & mengatur password pertama (`user_invitation`) | 1 |
| `two-factor.html` | 2FA opsional | 1 |
| `register.html`, `lock-screen.html` | Tidak dipakai: company dibuat Super Admin, user lewat undangan | — |
| Tombol "Masuk dengan NXTG" di `login.html` | OAuth2 Authorization Code, callback pusat ([A-28](wms/04-keputusan-dan-asumsi.md#a-28)), pemilih company ([A-48](wms/04-keputusan-dan-asumsi.md#a-48)) | 3 |

## 5. Checklist pendaftaran WhatsApp Cloud API (mulai hari pertama Fase 2a)

| ☐ | Langkah | Catatan |
|---|---|---|
| ☐ | Akun **Meta Business Manager** atas nama perusahaan | Admin = pemilik produk |
| ☐ | **Verifikasi bisnis** Meta: NIB/NPWP/akta, alamat, domain/situs resmi | Bisa berhari-hari sampai berminggu-minggu; mulai paling awal |
| ☐ | **Nomor telepon khusus** yang belum pernah dipakai WhatsApp/WhatsApp Business biasa, bisa menerima SMS/telepon | Satu nomor platform untuk semua company ([A-24](wms/04-keputusan-dan-asumsi.md#a-24)) |
| ☐ | Aplikasi di Meta for Developers + produk WhatsApp; token sistem & webhook HTTPS | Webhook di domain produksi |
| ☐ | Nama tampilan (display name) disetujui | Nama produk ([O-07](wms/04-keputusan-dan-asumsi.md#o-07)) |
| ☐ | Metode pembayaran Meta | Harga template utility ditagih penuh mulai 1 Okt 2026 ([riset §2.6](wms/02-riset-wms-sejenis.md#26-whatsapp-untuk-notifikasi--approval)) |
| ☐ | Template utility bertombol **Setujui / Tolak / Lihat Detail** diajukan & disetujui | [BR-APR-10](wms/05-aturan-bisnis.md#br-apr) |
| ☐ | Keputusan biaya per company & kuota ([O-03](wms/04-keputusan-dan-asumsi.md#o-03), [O-04](wms/04-keputusan-dan-asumsi.md#o-04)) | Alternatif: BSP lokal bila verifikasi mandiri terhambat |

## 6. Urutan mulai

1. **Part 4** — spesifikasi modul dari [template](wms/_template-spesifikasi-modul.md), urutan [08 §12](wms/08-arsitektur.md#12-langkah-berikutnya-part-4): Access → Master → Warehouse → Stock → Request → Picking/Shipment → Receipt/Putaway → Approval → Count/Adjustment → Return/Transfer → Issue → Conversion/Waste → Asset → PurchaseRequest/VendorReturn → Platform; lalu **Purchasing inti** (Fase 1b).
2. **Part 6** — paket prompt Claude Code per modul, dibuat segera setelah spesifikasi modul itu selesai, sehingga coding berjalan bertahap (kerangka Laravel dibuat saat prompt modul Access siap).
3. **Part 5** — landing page dikerjakan paralel saat Fase 1 stabil.

| Fase | Isi | Prasyarat eksternal |
|---|---|---|
| 1 | WMS inti (semua modul Fase 1 Blueprint §18), login lokal, notifikasi in-app & email | §1 dan §2 checklist ini |
| 1b | Purchasing inti: PO dari PRQ, master vendor & harga beli, approval PO berbasis nilai (paket Approval bersama), PO → GRN, tutup PO | Tidak ada |
| 2a | WhatsApp notifikasi & approval | §5 checklist ini |
| 2b | PWA offline penuh + sinkron, auditor eksternal, cycle count, maintenance, RFID | Reader RFID ([O-10](wms/04-keputusan-dan-asumsi.md#o-10)) |
| 3 | SSO NXTG, Purchasing lengkap (evaluasi vendor, penawaran, three-way match), payment gateway, REST API, analitik | Tim SSO ([O-02](wms/04-keputusan-dan-asumsi.md#o-02)) |
