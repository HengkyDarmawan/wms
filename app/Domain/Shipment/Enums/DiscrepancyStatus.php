<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Enums;

/** Status DSC — Katalog Status §2.4. Hanya dua; tidak boleh ditambah. */
enum DiscrepancyStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return $this === self::Open ? 'Terbuka' : 'Diselesaikan';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Open->value => self::Open->label(),
            self::Resolved->value => self::Resolved->label(),
        ];
    }

    public function badge(): string
    {
        return $this === self::Open ? 'text-bg-danger' : 'text-bg-success';
    }
}
