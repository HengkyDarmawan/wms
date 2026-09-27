<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `holiday_kind` (A-270).
 */
enum HolidayKind: string
{
    case National = 'national';
    case JointLeave = 'joint_leave';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::National => 'Libur nasional',
            self::JointLeave => 'Cuti bersama',
            self::Company => 'Libur company',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::National => 'text-bg-danger',
            self::JointLeave => 'text-bg-warning',
            self::Company => 'text-bg-info',
        };
    }
}
