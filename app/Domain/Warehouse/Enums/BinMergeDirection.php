<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Enums;

/** Katalog Status §3 `bin_merge_direction` — arah Gabung Bin (K-B, A-359). */
enum BinMergeDirection: string
{
    /** Bin di sebelahnya pada tingkat yang sama. */
    case Side = 'side';

    /** Bin di tingkat atasnya pada petak yang sama. */
    case Above = 'above';

    public function label(): string
    {
        return match ($this) {
            self::Side => 'Samping',
            self::Above => 'Atas',
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
}
