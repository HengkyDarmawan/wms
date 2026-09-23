<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports;

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
     * @return array<string, array{label: string, options?: array<string, string>}>
     */
    public function filters(): array
    {
        return [];
    }

    /** Nama berkas ekspor tanpa ekstensi. */
    public function fileName(): string
    {
        return $this->key().'-'.now()->format('Ymd-His');
    }
}
