<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Satuan meter pemakaian aset (A-66, BR-AST-08).
 */
enum MeterUnit: string
{
    case Hour = 'hour';
    case Km = 'km';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Hour => 'Jam mesin',
            self::Km => 'Kilometer',
            self::None => 'Tanpa meter',
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
