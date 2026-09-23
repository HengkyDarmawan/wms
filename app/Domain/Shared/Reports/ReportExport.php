<?php

declare(strict_types=1);

namespace App\Domain\Shared\Reports;

use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Satu kelas ekspor untuk seluruh laporan (AD-09).
 *
 * Nilai diambil apa adanya dari definisi laporan; tidak ada nilai uang di
 * mana pun karena WMS memang tidak menyimpannya (D-07).
 */
class ReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(
        private readonly Report $report,
        private readonly array $filters = [],
    ) {}

    /** @return Enumerable<int, array<int, mixed>> */
    public function collection(): Enumerable
    {
        $kolom = array_keys($this->report->columns());

        return $this->report->rows($this->filters)->map(
            fn (array $baris) => array_map(fn (string $k) => $baris[$k] ?? null, $kolom),
        );
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_values($this->report->columns());
    }

    /** @return array<int|string, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
