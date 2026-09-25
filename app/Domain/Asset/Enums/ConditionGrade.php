<?php

declare(strict_types=1);

namespace App\Domain\Asset\Enums;

use App\Domain\Master\Enums\AssetState;

/**
 * Katalog §3 `condition_grade` — grade kondisi aset saat serah terima dan
 * pemeriksaan (Blueprint §6.8, BR-AST-03).
 *
 * Hasil pemeriksaan (A-164): A/B → `available`, C → `maintenance`,
 * D → `damaged`. C/D menerbitkan `asset_lost_or_damaged` untuk Akuntansi.
 */
enum ConditionGrade: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function label(): string
    {
        return match ($this) {
            self::A => 'A — Baik',
            self::B => 'B — Layak',
            self::C => 'C — Rusak ringan',
            self::D => 'D — Rusak berat',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            $hasil[$case->value] = $case->label();
        }

        return $hasil;
    }

    public function resultingState(): AssetState
    {
        return match ($this) {
            self::A, self::B => AssetState::Available,
            self::C => AssetState::Maintenance,
            self::D => AssetState::Damaged,
        };
    }

    /** BR-AST-03: grade C/D diteruskan ke Akuntansi. */
    public function isDamage(): bool
    {
        return $this === self::C || $this === self::D;
    }
}
