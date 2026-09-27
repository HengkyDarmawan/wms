<?php

declare(strict_types=1);

namespace App\Domain\Label\Support;

use App\Domain\Master\Models\Item;

/**
 * Format kode label (A-296): kode item apa adanya + tanda hubung + urut 4
 * digit (5 digit atau lebih bila > 9999) — `BAUT-M12-0001`; label isi menambah
 * urut 4 digit di belakang kode induk — `BAUT-M12-0001-0001`.
 */
class LabelCode
{
    public const PANJANG_URUT = 4;

    public static function parent(Item $item, int $sequence): string
    {
        return mb_strtoupper((string) $item->code).'-'.self::urut($sequence);
    }

    public static function child(string $parentCode, int $sequence): string
    {
        return $parentCode.'-'.self::urut($sequence);
    }

    private static function urut(int $n): string
    {
        return str_pad((string) $n, self::PANJANG_URUT, '0', STR_PAD_LEFT);
    }
}
