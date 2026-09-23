<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Enums;

/** Katalog Status §3 `bin_status` (BR-OPN-02). */
enum BinStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Frozen => 'Dibeku',
            self::Inactive => 'Nonaktif',
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

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Frozen => 'warning',
            self::Inactive => 'secondary',
        };
    }

    /** BR-OPN-02: bin beku menolak PCK, PUT, SJ, dan ISU baru. */
    public function acceptsMovement(): bool
    {
        return $this === self::Active;
    }
}
