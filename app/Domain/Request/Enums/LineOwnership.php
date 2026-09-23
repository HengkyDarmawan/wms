<?php

declare(strict_types=1);

namespace App\Domain\Request\Enums;

/** A-38 dan BR-REQ-06: barang dibeli habis atau dipinjamkan lalu kembali. */
enum LineOwnership: string
{
    case Buy = 'buy';
    case Loan = 'loan';

    public function label(): string
    {
        return $this === self::Buy ? 'Beli' : 'Pinjam';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Buy->value => self::Buy->label(),
            self::Loan->value => self::Loan->label(),
        ];
    }

    /** BR-REQ-06: hanya barang berserial yang bisa dipinjamkan dan ditagih balik. */
    public function requiresSerial(): bool
    {
        return $this === self::Loan;
    }
}
