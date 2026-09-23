<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `asset_state` (BR-AST-01). Diubah oleh modul Asset, bukan Master.
 */
enum AssetState: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case InTransit = 'in_transit';
    case OnLoan = 'on_loan';
    case Returned = 'returned';
    case Inspection = 'inspection';
    case Maintenance = 'maintenance';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Reserved => 'Dicadangkan',
            self::InTransit => 'Dalam Perjalanan',
            self::OnLoan => 'Dipinjam',
            self::Returned => 'Dikembalikan',
            self::Inspection => 'Pemeriksaan',
            self::Maintenance => 'Maintenance',
            self::Damaged => 'Rusak',
            self::Lost => 'Hilang',
            self::WrittenOff => 'Dihapuskan',
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
