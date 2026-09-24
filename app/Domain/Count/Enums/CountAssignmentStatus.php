<?php

declare(strict_types=1);

namespace App\Domain\Count\Enums;

/**
 * Status penugasan penghitung (ERD 08c `count_assignments.status`), didaftarkan
 * di Katalog §3 sebagai `count_assignment_status` — bukan status dokumen.
 */
enum CountAssignmentStatus: string
{
    case Pending = 'pending';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum dihitung',
            self::Done => 'Selesai',
        };
    }

    public function badge(): string
    {
        return $this === self::Done ? 'text-bg-success' : 'text-bg-secondary';
    }
}
