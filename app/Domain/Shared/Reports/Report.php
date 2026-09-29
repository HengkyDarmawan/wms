<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports;

use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Vendor;
use App\Domain\Shared\Pilihan\Pilihan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Satu definisi laporan.
 *
 * Laporan di Access §9, Master §9, dan Warehouse §9 semuanya berbentuk sama:
 * tabel dengan beberapa penyaring dan tombol ekspor. Mendefinisikannya sebagai
 * data, bukan sebagai layar masing-masing, membuat satu layar dan satu jalur
 * ekspor melayani semuanya.
 */
abstract class Report
{
    /** Kunci pada URL, mis. `user-role-scope`. */
    abstract public function key(): string;

    abstract public function title(): string;

    /** Permission yang harus dipunyai untuk membukanya. */
    abstract public function permission(): string;

    /** Satu kalimat yang menjelaskan isinya. */
    abstract public function description(): string;

    /**
     * Kolom laporan: kunci => judul.
     *
     * @return array<string, string>
     */
    abstract public function columns(): array;

    /**
     * Baris laporan sesuai penyaring yang dipilih.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    abstract public function rows(array $filters): Collection;

    /**
     * Penyaring yang tersedia: kunci => [label, pilihan].
     * Pilihan kosong berarti kotak isian teks.
     *
     * `required` menandai penyaring yang harus diisi dulu (A-330): layar
     * menandainya `*` dan `rows()` mengembalikan koleksi kosong selama belum
     * dipilih — dipakai laporan yang tidak masuk akal tanpa saringannya, mis.
     * rekap per klien.
     *
     * Pilihan yang bisa dicari (A-396): `cari` = daftar `options` dimuat
     * sekaligus sebagai `<x-pilih>`; `server` = daftar besar (proyek, vendor)
     * dicari ke server lewat {@see pilihanPenyaring()}, tanpa `options`.
     *
     * @return array<string, array{label: string, required?: bool, options?: array<string, string>, cari?: bool, server?: bool}>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Daftar pilihan penyaring bertanda `server` (A-396): query yang dulu
     * mengisi `options`, sehingga cakupan pembaca tetap sama. Null = kunci
     * itu tidak dicari ke server.
     */
    public function pilihanPenyaring(string $kunci): ?Pilihan
    {
        return null;
    }

    /** Opsi proyek penyaring: "kode — nama", dicari di kode & nama. */
    protected function pilihanProyekDari(Builder $query): Pilihan
    {
        return Pilihan::dari($query, ['code', 'name'], fn (Project $p) => [
            'value' => (int) $p->id,
            'text' => $p->code.' — '.$p->name,
        ]);
    }

    /** Semua vendor (termasuk nonaktif — laporan riwayat), "kode — nama". */
    protected function pilihanVendorSemua(): Pilihan
    {
        return Pilihan::dari(Vendor::query()->orderBy('code'), ['code', 'name'], fn (Vendor $v) => [
            'value' => (int) $v->id,
            'text' => $v->code.' — '.$v->name,
        ]);
    }

    /** Nama berkas ekspor tanpa ekstensi. */
    public function fileName(): string
    {
        return $this->key().'-'.now()->format('Ymd-His');
    }
}
