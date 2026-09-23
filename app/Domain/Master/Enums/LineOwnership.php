<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `line_ownership` (A-38): pilihan Beli/Pinjam per baris permintaan.
 */
enum LineOwnership: string
{
    case Buy = 'buy';
    case Loan = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Beli',
            self::Loan => 'Pinjam',
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
