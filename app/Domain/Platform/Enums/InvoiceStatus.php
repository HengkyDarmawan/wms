<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

/** Katalog §3 `subscription_invoice_status` (ERD 08a `subscription_invoices.status`). */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Belum Dibayar',
            self::Paid => 'Lunas',
            self::Overdue => 'Lewat Jatuh Tempo',
            self::Void => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'text-bg-info',
            self::Paid => 'text-bg-success',
            self::Overdue => 'text-bg-danger',
            self::Void => 'text-bg-secondary',
        };
    }

    /** Tagihan yang masih menunggu pembayaran. */
    public function isPayable(): bool
    {
        return $this === self::Open || $this === self::Overdue;
    }
}
