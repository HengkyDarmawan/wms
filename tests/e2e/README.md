# Uji browser (E2E) — Chrome headless lewat CDP

Tanpa paket tambahan: Node 22+ (fetch + WebSocket bawaan) dan Google Chrome. Server aplikasi harus menyala dan data demo sudah di-seed ([docs/00-setup-lokal.md](../../docs/00-setup-lokal.md)).

| Skrip | Isi |
|---|---|
| `ui-check.mjs` | Login admin demo, tombol mata, scroll sidebar, toggle sidebar/tema, Ctrl+K, dropdown, sidebar HP, lalu membuka **semua tautan menu** dan melapor error console; terakhir login Super Admin di domain pusat dan membuka layar Company, Pembayaran, Paket, Keamanan akun (`WMS_CENTRAL`, bawaan `WMS_BASE` tanpa `demo.`) |
| `alur-req-sj.mjs` | Skenario [00-setup-lokal §5](../../docs/00-setup-lokal.md#5-skenario-uji-manual): REQ → tinjau → approval Kepala Gudang CKG → PCK → SJ → bukti terima 48/2 → DSC → portal klien. Mengubah data demo (membuat dokumen baru); jalankan pada data demo yang baru di-seed |
| `alur-pendukung.mjs` | Pendukung F1 ([27-pendukung-f1](../../docs/wms/27-pendukung-f1.md)): pemohon mengonfirmasi terima SJ dari `alur-req-sj`, notifikasi & lonceng, Beranda antrean pekerjaan, laporan + ekspor PDF/Excel, wizard setup, templat impor, preferensi notifikasi, berkas PWA, lalu **P7** PRQ manual → catatan pemesanan → GRN vendor merujuk pesanan → put-away → PRQ Dipenuhi. Jalankan **setelah** `alur-req-sj.mjs` |

Variabel lingkungan:

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `WMS_BASE` | `http://demo.wms.test:8000` | cocok untuk kedua profil (`php artisan serve` port 8000) |
| `CHROME_PATH` | `C:/Program Files/Google/Chrome/Application/chrome.exe` | |
| `MYSQL_BIN` | `mysql` | rumah: `C:/xampp3/mysql/bin/mysql.exe` · kantor: `C:/xampp/mysql/bin/mysql.exe` (`alur-req-sj.mjs`, `alur-pendukung.mjs`) |
| `DB_PASSWORD` | kosong | password `root` bila ada |
| `WMS_TENANT_DB` | `wms_tenant_demo` | |

```bash
node tests/e2e/ui-check.mjs
MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe node tests/e2e/alur-req-sj.mjs
MYSQL_BIN=C:/xampp3/mysql/bin/mysql.exe node tests/e2e/alur-pendukung.mjs
```

Tangkapan layar (`*.png`) dan `e2e-hasil.json` ditulis ke folder kerja saat ini dan diabaikan git.

Data demo segar untuk skenario: `php artisan tenants:migrate-fresh --tenants=1` lalu `php artisan tenants:seed --tenants=1 --class="Database\Seeders\Tenant\DemoSeeder"` (hanya database `wms_tenant_demo`).
