<?php

declare(strict_types=1);

namespace App\Domain\Label\Enums;

/**
 * Katalog Status §3 `package_label_status` (bukan status dokumen, A-296):
 * Di gudang → Keluar (dipindai saat barang keluar gudang, A-299) → bisa Di
 * gudang lagi saat diterima gudang tujuan (A-300); Batal = salah cetak/rusak
 * beralasan, atau retur dipilah rusak/waste (A-301).
 */
enum PackageLabelStatus: string
{
    case InStock = 'in_stock';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Di gudang',
            self::Issued => 'Keluar',
            self::Cancelled => 'Batal',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InStock => 'text-bg-success',
            self::Issued => 'text-bg-secondary',
            self::Cancelled => 'text-bg-danger',
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
}
