<?php

declare(strict_types=1);

namespace App\Domain\Return\Enums;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Stock\Enums\StockStatus;

/**
 * Katalog Status §3 `return_sorting` — hasil pemilahan retur (BR-RET-04).
 */
enum ReturnSorting: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Offcut = 'offcut';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Layak',
            self::Damaged => 'Rusak',
            self::Offcut => 'Offcut',
            self::Waste => 'Waste',
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

    /** Kondisi stok hasil pilah: layak & offcut Tersedia; rusak & waste Rusak (A-113). */
    public function stockStatus(): StockStatus
    {
        return match ($this) {
            self::Good, self::Offcut => StockStatus::Available,
            self::Damaged, self::Waste => StockStatus::Damaged,
        };
    }

    /** Alasan wajib (BR-GEN-11): rusak = konteks kerusakan, waste = konteks waste. */
    public function reasonContext(): ?ReasonContext
    {
        return match ($this) {
            self::Damaged => ReasonContext::Damage,
            self::Waste => ReasonContext::Waste,
            default => null,
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Good => 'text-bg-success',
            self::Offcut => 'text-bg-info',
            self::Damaged => 'text-bg-danger',
            self::Waste => 'text-bg-dark',
        };
    }
}
