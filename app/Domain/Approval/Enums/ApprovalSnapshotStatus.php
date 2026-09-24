<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `approval_snapshot_status` (ERD 08c). Bukan status
 * dokumen: dokumen tetap memakai `pending_approval`/`approved`/`rejected`
 * dari mesin statusnya sendiri.
 */
enum ApprovalSnapshotStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'text-bg-warning',
            self::Approved => 'text-bg-success',
            self::Rejected, self::Cancelled => 'text-bg-danger',
        };
    }
}
