<?php

declare(strict_types=1);

namespace App\Domain\Stock\Enums;

/**
 * Katalog Status §3 `stock_status` — **kondisi** stok, bukan lokasinya.
 *
 * Lokasi selalu berupa bin, termasuk bin virtual Dalam Perjalanan dan On-site
 * Proyek (BR-STK-02, A-29). "Dicadangkan" juga bukan kondisi melainkan baris
 * di tabel reservasi (BR-STK-03).
 */
enum StockStatus: string
{
    case Available = 'available';
    case Quarantine = 'quarantine';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Quarantine => 'Karantina',
            self::Damaged => 'Rusak',
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

    public function badge(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Quarantine => 'warning',
            self::Damaged => 'danger',
        };
    }

    /** Hanya stok Tersedia yang bisa dijanjikan ke dokumen (BR-STK-03). */
    public function isReservable(): bool
    {
        return $this === self::Available;
    }
}
