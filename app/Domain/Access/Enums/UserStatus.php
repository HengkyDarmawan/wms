<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Status user adalah nilai TURUNAN (10-access §3), bukan kolom enum di tabel:
 * dihitung dari is_active, locked_until, dan undangan yang belum diterima.
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Diundang',
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Locked => 'Terkunci',
        };
    }

    /** Warna badge NexaDash. */
    public function badge(): string
    {
        return match ($this) {
            self::Invited => 'info',
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Locked => 'danger',
        };
    }
}
