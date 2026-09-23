<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `vendor_type` (A-52).
 */
enum VendorType: string
{
    case Company = 'company';
    case Shop = 'shop';
    case OnlineMarketplace = 'online_marketplace';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Perusahaan',
            self::Shop => 'Toko',
            self::OnlineMarketplace => 'Toko online',
            self::Individual => 'Perorangan',
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
