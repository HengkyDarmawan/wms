<?php

declare(strict_types=1);

namespace App\Domain\Waste\Enums;

/**
 * Katalog §3 `waste_disposition` (ERD 08c `waste_disposals.disposition`).
 * `reused` mengembalikan barang ke stok bin penyimpanan berkondisi Tersedia;
 * dua lainnya mengeluarkannya dari stok (Katalog §2.14).
 */
enum WasteDisposition: string
{
    case Disposed = 'disposed';
    case SoldScrap = 'sold_scrap';
    case Reused = 'reused';

    public function label(): string
    {
        return match ($this) {
            self::Disposed => 'Dibuang',
            self::SoldScrap => 'Dijual scrap',
            self::Reused => 'Dipakai ulang',
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

    public function returnsToStock(): bool
    {
        return $this === self::Reused;
    }
}
