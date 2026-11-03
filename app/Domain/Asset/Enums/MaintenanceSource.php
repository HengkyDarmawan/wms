<?php

declare(strict_types=1);

namespace App\Domain\Asset\Enums;

/** Katalog §3 `maintenance_source` — asal jadwal servis (A-423). */
enum MaintenanceSource: string
{
    case Manual = 'manual';
    case Interval = 'interval';
    case Inspection = 'inspection';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Interval => 'Berkala',
            self::Inspection => 'Dari pemeriksaan',
        };
    }
}
