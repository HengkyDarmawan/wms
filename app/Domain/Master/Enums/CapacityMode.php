<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Perilaku kapasitas bin per kategori penyimpanan (A-37, BR-STK-07).
 */
enum CapacityMode: string
{
    case Warn = 'warn';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::Warn => 'Peringatan',
            self::Block => 'Blokir',
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
