<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Kategori satuan bawaan (Blueprint §6.5). Konversi hanya sah di dalam satu kategori.
 */
enum UomCategoryCode: string
{
    case Count = 'count';
    case Length = 'length';
    case Weight = 'weight';
    case Volume = 'volume';
    case Area = 'area';

    public function label(): string
    {
        return match ($this) {
            self::Count => 'Jumlah',
            self::Length => 'Panjang',
            self::Weight => 'Berat',
            self::Volume => 'Volume',
            self::Area => 'Luas',
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
