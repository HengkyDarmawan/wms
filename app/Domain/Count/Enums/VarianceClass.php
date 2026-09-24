<?php

declare(strict_types=1);

namespace App\Domain\Count\Enums;

/**
 * Katalog Status §3 `variance_class` (BR-OPN-04, A-42). Baris tanpa selisih
 * tidak punya kelas (kolom kosong).
 */
enum VarianceClass: string
{
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Kecil',
            self::Moderate => 'Sedang',
            self::Major => 'Besar',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Minor => 'text-bg-secondary',
            self::Moderate => 'text-bg-warning',
            self::Major => 'text-bg-danger',
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
