<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enums;

/**
 * BR-STK-04 — dua tingkat reservasi.
 *
 * Lunak dibuat saat dokumen disetujui dan hanya menyebut item dan gudang.
 * Keras dibuat saat picking dan sudah menunjuk bin, lot, serial, atau potongan.
 */
enum ReservationLevel: string
{
    case Soft = 'soft';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Soft => 'Lunak (item & gudang)',
            self::Hard => 'Keras (alokasi bin)',
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

    public function requiresBin(): bool
    {
        return $this === self::Hard;
    }
}
