<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Enums;

/** Katalog Status §3 `bin_merge_type` — sifat Gabung Bin (K-B, A-359). */
enum BinMergeType: string
{
    /** Untuk barang besar sesaat; pisah otomatis saat bin utama kosong (A-360). */
    case Temporary = 'temporary';

    /** Bentuk rak yang memang lebar/tinggi; hanya dipisah manual dengan alasan. */
    case Permanent = 'permanent';

    public function label(): string
    {
        return match ($this) {
            self::Temporary => 'Sementara',
            self::Permanent => 'Permanen',
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
