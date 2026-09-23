<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `ownership_model` (D-12). Aset wajib serial (BR-STK-08).
 */
enum OwnershipModel: string
{
    case Consumable = 'consumable';
    case Asset = 'asset';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Consumable => 'Habis pakai / jual putus',
            self::Asset => 'Aset dipinjamkan',
            self::Both => 'Keduanya',
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
