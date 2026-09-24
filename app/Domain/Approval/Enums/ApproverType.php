<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `approver_type` — siapa approver satu lapis (Blueprint
 * §8.1, ERD `approval_steps.approver_type`).
 */
enum ApproverType: string
{
    use HasOptions;

    case User = 'user';
    case Position = 'position';
    case Role = 'role';
    case DirectManager = 'direct_manager';
    case WarehouseHead = 'warehouse_head';
    case ProjectPic = 'project_pic';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User tertentu',
            self::Position => 'Jabatan',
            self::Role => 'Role (dalam cakupan dokumen)',
            self::DirectManager => 'Atasan langsung pemohon',
            self::WarehouseHead => 'Kepala gudang terkait',
            self::ProjectPic => 'PIC proyek',
        };
    }

    /** Jenis yang menunjuk baris lain lewat `approver_ref_id`. */
    public function needsReference(): bool
    {
        return in_array($this, [self::User, self::Position, self::Role], true);
    }
}
