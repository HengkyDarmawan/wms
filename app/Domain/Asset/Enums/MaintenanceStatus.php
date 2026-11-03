<?php

declare(strict_types=1);

namespace App\Domain\Asset\Enums;

/** Katalog §3 `maintenance_status` — jadwal servis aset (A-423). */
enum MaintenanceStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Sedang diservis',
            self::Done => 'Selesai',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::InProgress => 'warning',
            self::Done => 'success',
        };
    }
}
