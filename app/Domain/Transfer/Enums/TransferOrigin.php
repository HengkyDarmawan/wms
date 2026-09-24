<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Enums;

/**
 * Katalog Status §3 `transfer_origin` — asal TRF (ERD `transfers.origin`).
 *
 * `backorder` dibuat sistem saat REQ disetujui untuk baris bersumber transfer
 * (BR-REQ-05, A-106); `manual` dibuat Staf/Kepala Gudang.
 */
enum TransferOrigin: string
{
    case Manual = 'manual';
    case Backorder = 'backorder';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Backorder => 'Dari backorder REQ',
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
