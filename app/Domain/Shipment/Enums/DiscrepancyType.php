<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/**
 * A-64 — dua bentuk selisih, dengan nasib berbeda.
 *
 * Kurang berarti barangnya tidak sampai dan kondisinya tetap baik; rusak
 * berarti sampai tetapi tidak bisa dipakai. Keduanya tetap tercatat sebagai
 * stok gudang asal sampai DSC diselesaikan (BR-SJ-10).
 */
enum DiscrepancyType: string
{
    case Missing = 'missing';
    case Damaged = 'damaged';

    public function label(): string
    {
        return $this === self::Missing ? 'Kurang' : 'Rusak';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Missing->value => self::Missing->label(),
            self::Damaged->value => self::Damaged->label(),
        ];
    }

    /** Barang rusak langsung berkondisi `damaged` sejak bukti terima. */
    public function becomesDamagedStock(): bool
    {
        return $this === self::Damaged;
    }
}
