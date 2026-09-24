<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Enums;

use App\Domain\Stock\Enums\StockStatus;

/**
 * Katalog §3 `conversion_output_kind` (ERD 08c `conversion_outputs.output_kind`).
 *
 * - `output` — hasil konversi (boleh item lain), masuk bin penyimpanan;
 * - `offcut` — sisa ≥ `min_offcut_length`, potongan baru item sama (BR-CNV-03);
 * - `waste` — sisa tidak layak pakai, masuk bin Waste berkondisi Rusak;
 * - `kerf` — rugi potong, tidak menggerakkan stok.
 */
enum ConversionOutputKind: string
{
    case Output = 'output';
    case Offcut = 'offcut';
    case Waste = 'waste';
    case Kerf = 'kerf';

    public function label(): string
    {
        return match ($this) {
            self::Output => 'Output',
            self::Offcut => 'Offcut',
            self::Waste => 'Waste',
            self::Kerf => 'Kerf / Rugi Potong',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }

    /** Kerf hilang karena proses potong: tidak ada pergerakan kartu stok. */
    public function movesStock(): bool
    {
        return $this !== self::Kerf;
    }

    /** Sisa (offcut, waste, kerf) selalu dari item input induknya. */
    public function isRemainder(): bool
    {
        return $this !== self::Output;
    }

    public function stockStatus(): ?StockStatus
    {
        return match ($this) {
            self::Output, self::Offcut => StockStatus::Available,
            self::Waste => StockStatus::Damaged,
            self::Kerf => null,
        };
    }
}
