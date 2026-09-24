<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/** Katalog Status §3 `approval_task_status` (ERD 08c). */
enum ApprovalTaskStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Decided = 'decided';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Decided => 'Diputus',
            self::Superseded => 'Digantikan',
            self::Expired => 'Dialihkan (eskalasi)',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'text-bg-warning',
            self::Decided => 'text-bg-success',
            self::Superseded, self::Expired => 'text-bg-secondary',
        };
    }
}
