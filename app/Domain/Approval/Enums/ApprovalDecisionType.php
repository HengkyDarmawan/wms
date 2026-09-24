<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/** Katalog Status §3 `approval_decision`. */
enum ApprovalDecisionType: string
{
    use HasOptions;

    case Approved = 'approved';
    case Rejected = 'rejected';
    case Delegated = 'delegated';
    case Escalated = 'escalated';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Setuju',
            self::Rejected => 'Tolak',
            self::Delegated => 'Didelegasikan',
            self::Escalated => 'Dieskalasi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Approved => 'text-bg-success',
            self::Rejected => 'text-bg-danger',
            self::Delegated, self::Escalated => 'text-bg-info',
        };
    }
}
