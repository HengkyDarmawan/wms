<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

/**
 * Cakupan penugasan role — BR-GEN-09, A-46, BR-ACC-04.
 */
enum ScopeType: string
{
    case All = 'all';
    case Warehouse = 'warehouse';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua',
            self::Warehouse => 'Gudang',
            self::Project => 'Proyek',
        };
    }

    public function needsScopeId(): bool
    {
        return $this !== self::All;
    }
}
