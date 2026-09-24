<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Enums;

/**
 * Asal ADJ (ERD 08c `stock_adjustments.origin`), didaftarkan di Katalog §3
 * sebagai `adjustment_origin`.
 *
 * `discrepancy` dan `asset_lost` adalah titik sambung: DSC `adjusted` di Fase 1
 * memposting sendiri dengan kejadian `delivery_discrepancy` (A-98), dan
 * write-off aset menunggu modul Aset (BR-GEN-10).
 */
enum AdjustmentOrigin: string
{
    case Manual = 'manual';
    case Count = 'count';
    case Discrepancy = 'discrepancy';
    case AssetLost = 'asset_lost';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Count => 'Hasil opname',
            self::Discrepancy => 'Selisih pengiriman',
            self::AssetLost => 'Aset hilang',
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
}
