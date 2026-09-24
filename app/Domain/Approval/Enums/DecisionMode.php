<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `decision_mode` — cara putus satu lapis bila approvernya
 * lebih dari satu orang (Blueprint §8.1, BR-APR-09).
 */
enum DecisionMode: string
{
    use HasOptions;

    case Sequential = 'sequential';
    case Any = 'any';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Sequential => 'Berurutan',
            self::Any => 'Cukup salah satu',
            self::All => 'Semua harus setuju',
        };
    }

    /** Jumlah persetujuan yang dibutuhkan lapis dengan n approver. */
    public function required(int $approvers): int
    {
        return $this === self::Any ? min(1, $approvers) : $approvers;
    }
}
