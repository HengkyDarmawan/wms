<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Konteks master Alasan (ERD 08a `reason_codes.context`, BR-GEN-02).
 */
enum ReasonContext: string
{
    case Reject = 'reject';
    case Cancel = 'cancel';
    case Adjustment = 'adjustment';
    case Waste = 'waste';
    case Damage = 'damage';
    case ShortPick = 'short_pick';
    case Discrepancy = 'discrepancy';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Reject => 'Penolakan',
            self::Cancel => 'Pembatalan',
            self::Adjustment => 'Penyesuaian stok',
            self::Waste => 'Waste',
            self::Damage => 'Kerusakan',
            self::ShortPick => 'Kekurangan pick',
            self::Discrepancy => 'Selisih pengiriman',
            self::Lost => 'Kehilangan',
        };
    }

    /** @return array<string, string> nilai => label, untuk isian select. */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }
}
