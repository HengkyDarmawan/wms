<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `removal_strategy` (Blueprint §6.6, A-10 bawaan FIFO).
 */
enum RemovalStrategy: string
{
    case Fifo = 'fifo';
    case Fefo = 'fefo';
    case Manual = 'manual';
    case OffcutFirst = 'offcut_first';

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'FIFO (masuk dulu, keluar dulu)',
            self::Fefo => 'FEFO (kedaluwarsa terdekat dulu)',
            self::Manual => 'Manual',
            self::OffcutFirst => 'Sisa potongan dulu',
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

    /** BR-STK-12: FEFO menuntut kedaluwarsa aktif. */
    public function requiresExpiry(): bool
    {
        return $this === self::Fefo;
    }
}
