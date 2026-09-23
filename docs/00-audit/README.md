# Indeks Audit Prototipe (`warehouse.sipembantu.com`)

**Versi:** 0.1
**Tanggal:** 23 September 2026
**Status:** indeks rujukan; dokumen audit lengkap (`00-audit/01`–`04`) disimpan pemilik produk di luar repo ini

Dokumen di folder `wms/` merujuk ID temuan audit prototipe. Folder ini dibuat agar rujukan itu tidak menggantung. **Kolom Keterangan diisi pemilik produk** dengan satu kalimat per temuan (disalin dari dokumen audit asli); sampai terisi, cukup tahu di mana ID itu dipakai.

Lokasi dokumen asli: `……………………………………` *(isi)*

## Temuan yang dirujuk

| ID | Jenis | Keterangan (isi) | Dirujuk di |
|---|---|---|---|
| BUG-02 | Bug | | [Blueprint P-11](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-07 | Bug | | [Blueprint P-03](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-11 | Bug | | [Blueprint P-03](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-14 | Bug | | [Blueprint P-02](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-15 | Bug | | [Blueprint P-02](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-16 | Bug | | [Blueprint P-06](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-18 | Bug | | [Blueprint P-03](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-20 | Bug — stok disimpan di satu kolom, retur tidak menambah stok | | [Blueprint P-01](../wms/01-blueprint.md#5-prinsip-desain) |
| BUG-22 | Bug | | [Blueprint P-11](../wms/01-blueprint.md#5-prinsip-desain) |
| UX-05 | UX | | [Blueprint P-07](../wms/01-blueprint.md#5-prinsip-desain) |
| UX-11 | UX — filter laporan hanya bulan/tahun | | [Blueprint 6.9a](../wms/01-blueprint.md#69a-laporan-inti-fase-1) |
| UX-12 | UX — tidak ada ekspor Excel/PDF | | [Blueprint 6.9a](../wms/01-blueprint.md#69a-laporan-inti-fase-1) |
| UX-13 | UX | | [Blueprint P-11](../wms/01-blueprint.md#5-prinsip-desain) |
| UX-14 | UX — kategori bisa dihapus saat dipakai | | [Blueprint 6.3a](../wms/01-blueprint.md#63a-master-data-lain) |
| UX-17 | UX — vendor tanpa master | | [Blueprint 6.3a](../wms/01-blueprint.md#63a-master-data-lain) |
| UX-28 | UX | | [Blueprint P-04](../wms/01-blueprint.md#5-prinsip-desain) |
| UX-29 | UX | | [Blueprint P-04](../wms/01-blueprint.md#5-prinsip-desain) |
| SEC-01 | Keamanan — `APP_DEBUG` aktif di produksi | | [Blueprint P-12](../wms/01-blueprint.md#5-prinsip-desain), [O-06](../wms/04-keputusan-dan-asumsi.md#o-06) |
| SEC-02 | Keamanan | | [Blueprint P-12](../wms/01-blueprint.md#5-prinsip-desain) |
| SEC-04 | Keamanan — transisi status lewat GET | | [Blueprint P-04](../wms/01-blueprint.md#5-prinsip-desain) |
| T-12 | Temuan SSO NXTG | | [O-02](../wms/04-keputusan-dan-asumsi.md#o-02) |
| T-13 | Temuan SSO NXTG | | [O-02](../wms/04-keputusan-dan-asumsi.md#o-02) |
| T-14 | Temuan SSO NXTG | | [O-02](../wms/04-keputusan-dan-asumsi.md#o-02) |
| T-17 | Temuan SSO NXTG | | [O-02](../wms/04-keputusan-dan-asumsi.md#o-02) |

Keterangan yang sudah terisi di kolom *Jenis* disimpulkan dari konteks Blueprint v0.2; koreksi bila tidak tepat.

## Konvensi ID (dari dokumen audit asli)

| Prefiks | Arti |
|---|---|
| `BUG-` | Cacat fungsional prototipe |
| `UX-` | Masalah kegunaan / konsistensi |
| `SEC-` | Temuan keamanan prototipe |
| `T-` | Temuan audit SSO NXTG (`00-audit/04`) |
