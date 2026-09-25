<?php

declare(strict_types=1);

namespace App\Domain\Asset\Enums;

/**
 * Status AST — Katalog Status §2.11. **Tidak boleh ditambah.**
 *
 * `checked_out` (otomatis, SJ aset `delivered`) → `returned` (otomatis, GRN
 * retur berisi aset `received`) → `inspected` (`asset.inspect`, grade + foto).
 */
enum AssetHandoverStatus: string
{
    case CheckedOut = 'checked_out';
    case Returned = 'returned';
    case Inspected = 'inspected';

    public function label(): string
    {
        return match ($this) {
            self::CheckedOut => 'Dipinjam',
            self::Returned => 'Dikembalikan',
            self::Inspected => 'Diperiksa',
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

    public function badge(): string
    {
        return match ($this) {
            self::CheckedOut => 'text-bg-primary',
            self::Returned => 'text-bg-warning',
            self::Inspected => 'text-bg-success',
        };
    }
}
