<?php

declare(strict_types=1);

namespace App\Domain\Platform\Enums;

/** Katalog §3 `subscription_payment_status` (ERD 08a `subscription_payments.status`). */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-warning',
            self::Verified => 'text-bg-success',
            self::Rejected => 'text-bg-danger',
        };
    }
}
