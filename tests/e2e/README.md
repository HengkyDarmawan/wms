# Uji browser (E2E) — Chrome headless lewat CDP

Tanpa paket tambahan: Node 22+ (fetch + WebSocket bawaan) dan Google Chrome. Server aplikasi harus menyala dan data demo sudah di-seed ([docs/00-setup-lokal.md](../../docs/00-setup-lokal.md)).

| Skrip | Isi |
|---|---|
| `ui-check.mjs` | Login admin demo, tombol mata, scroll sidebar, toggle sidebar/tema, Ctrl+K, dropdown, sidebar HP, lalu membuka **semua tautan menu** dan melapor error console |
| `alur-req-sj.mjs` | Skenario [00-setup-lokal §5](../../docs/00-setup-lokal.md#5-skenario-uji-manual): REQ → tinjau → approval Kepala Gudang CKG → PCK → SJ → bukti terima 48/2 → DSC → portal klien. Mengubah data demo (membuat dokumen baru); jalankan pada data demo yang baru di-seed |

Variabel lingkungan:

| Variabel | Bawaan | Keterangan |
|---|---|---|
| `WMS_BASE` | `http://demo.wms.test:8000` | Laragon: `http://demo.wms.test` |
| `CHROME_PATH` | `C:/Program Files/Google/Chrome/Application/chrome.exe` | |
| `MYSQL_BIN` | `mysql` | XAMPP: `C:/xampp/mysql/bin/mysql.exe` (hanya `alur-req-sj.mjs`) |
| `DB_PASSWORD` | kosong | password `root` bila ada |
| `WMS_TENANT_DB` | `wms_tenant_demo` | |

```bash
WMS_BASE=http://demo.wms.test node tests/e2e/ui-check.mjs
WMS_BASE=http://demo.wms.test node tests/e2e/alur-req-sj.mjs
```

Tangkapan layar (`*.png`) dan `e2e-hasil.json` ditulis ke folder kerja saat ini dan diabaikan git.
