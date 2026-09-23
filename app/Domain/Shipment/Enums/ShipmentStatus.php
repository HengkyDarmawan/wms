<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/**
 * Status SJ — Katalog Status §2.3. **Tidak boleh ditambah.**
 *
 * Setelah `shipped`, SJ tidak bisa dibatalkan: barangnya sudah di jalan.
 * Koreksinya lewat DSC atau retur.
 */
enum ShipmentStatus: string
{
    case Prepared = 'prepared';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Prepared => 'Disiapkan',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Diterima',
            self::PartiallyDelivered => 'Diterima Sebagian',
            self::Cancelled => 'Dibatalkan',
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

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::PartiallyDelivered, self::Cancelled], true);
    }

    /** Hanya SJ yang belum berangkat yang bisa dibatalkan. */
    public function isCancellable(): bool
    {
        return $this === self::Prepared;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Prepared => 'text-bg-secondary',
            self::Shipped => 'text-bg-primary',
            self::Delivered => 'text-bg-success',
            self::PartiallyDelivered => 'text-bg-warning',
            self::Cancelled => 'text-bg-danger',
        };
    }
}
