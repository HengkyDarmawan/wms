<?php

declare(strict_types=1);

namespace App\Domain\Master\Enums;

/**
 * Katalog Status §3 `project_status` (A-40, BR-PRJ-01).
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Closed => 'Ditutup',
            self::Cancelled => 'Dibatalkan',
            self::Archived => 'Diarsipkan',
        };
    }

    /** @return array<string, string> nilai => label, untuk isian select. */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }
}
