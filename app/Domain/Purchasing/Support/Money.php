<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Support;

/**
 * Nilai uang Purchasing (A-211): Rupiah, dua desimal. Hanya dipakai di domain
 * Purchasing (A-208).
 */
final class Money
{
    public const CURRENCY = 'IDR';

    public static function round(float $amount): float
    {
        return round($amount, 2);
    }

    /** `Rp 1.250.000` atau `Rp 1.250.000,50`. */
    public static function format(float|string|null $amount): string
    {
        $n = (float) ($amount ?? 0);
        $desimal = abs($n - round($n)) >= 0.005 ? 2 : 0;

        return 'Rp '.number_format($n, $desimal, ',', '.');
    }
}
