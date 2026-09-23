<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** BR-SJ-04 — apa yang terjadi pada kepemilikan barang saat diterima. */
enum OwnershipEffect: string
{
    case Sold = 'sold';
    case Transfer = 'transfer';
    case Loan = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::Sold => 'Jual putus',
            self::Transfer => 'Transfer',
            self::Loan => 'Pinjam',
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

    /** Jual putus mengeluarkan barang dari ledger; sisanya tetap tercatat. */
    public function leavesLedger(): bool
    {
        return $this === self::Sold;
    }
}
