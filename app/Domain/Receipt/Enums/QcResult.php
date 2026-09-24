<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Enums;

use App\Domain\Stock\Enums\StockStatus;

/**
 * Katalog Status §3 `qc_result` — hasil QC per baris GRN (BR-GRN-02).
 */
enum QcResult: string
{
    case Passed = 'passed';
    case Quarantined = 'quarantined';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Lolos',
            self::Quarantined => 'Karantina',
            self::Rejected => 'Ditolak',
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

    /**
     * Kondisi stok yang mewakili hasil ini (A-78): lolos menjadi Tersedia,
     * karantina tetap Karantina, ditolak menjadi Rusak — satu-satunya kondisi
     * katalog yang menyatakan "tidak boleh dipakai".
     */
    public function stockStatus(): StockStatus
    {
        return match ($this) {
            self::Passed => StockStatus::Available,
            self::Quarantined => StockStatus::Quarantine,
            self::Rejected => StockStatus::Damaged,
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Passed => 'text-bg-success',
            self::Quarantined => 'text-bg-warning',
            self::Rejected => 'text-bg-danger',
        };
    }
}
