<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Enums;

/**
 * Katalog §3 `conversion_type` (ERD 08c `conversions.conversion_type`,
 * Blueprint §6.7 "pola yang sama untuk rakit, bongkar, dan ganti kemasan").
 *
 * Neraca ukuran (BR-CNV-02) wajib untuk `cut` dan `repack` — semua baris satu
 * satuan dasar; rakit/bongkar mengubah bentuk antar satuan sehingga rasionya
 * menunggu resep konversi `[F2]` (A-156).
 */
enum ConversionType: string
{
    case Cut = 'cut';
    case Assemble = 'assemble';
    case Disassemble = 'disassemble';
    case Repack = 'repack';

    public function label(): string
    {
        return match ($this) {
            self::Cut => 'Potong',
            self::Assemble => 'Rakit',
            self::Disassemble => 'Bongkar',
            self::Repack => 'Ganti kemasan',
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

    /** BR-CNV-02 berlaku penuh (A-156). */
    public function requiresSizeBalance(): bool
    {
        return $this === self::Cut || $this === self::Repack;
    }
}
