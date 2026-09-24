<?php

declare(strict_types=1);

namespace App\Domain\Approval\Enums;

/**
 * Katalog Status §3 `condition_match` — cara menggabungkan kondisi aturan
 * (A-87): semua kondisi terpenuhi, atau cukup salah satu ("> 20 baris atau
 * kategori Aset", 00-akun-uji §5).
 */
enum ConditionMatch: string
{
    use HasOptions;

    case All = 'all';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua kondisi terpenuhi',
            self::Any => 'Salah satu kondisi terpenuhi',
        };
    }
}
