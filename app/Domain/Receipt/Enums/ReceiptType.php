<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Enums;

/**
 * Katalog Status §3 `receipt_type` — sumber barang masuk (ERD `goods_receipts`).
 *
 * GRN adalah satu-satunya dokumen masuk (A-33). `return` adalah titik sambung
 * modul Retur yang belum ada; memilihnya ditolak sampai modul itu lahir
 * (BR-GEN-10).
 */
enum ReceiptType: string
{
    case Vendor = 'vendor';
    case Transfer = 'transfer';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Vendor => 'Dari vendor',
            self::Transfer => 'Transfer masuk',
            self::Return => 'Retur dari proyek',
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
