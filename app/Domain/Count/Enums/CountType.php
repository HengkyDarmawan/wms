<?php

declare(strict_types=1);

namespace App\Domain\Count\Enums;

/**
 * Katalog Status §3 `count_type` (Blueprint §9, D-23, BR-OPN-10).
 *
 * `cycle_abc` bertag [F2]: nilainya ada supaya data lama tetap terbaca,
 * tetapi tidak bisa dipilih di Fase 1 (BR-GEN-10).
 */
enum CountType: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';
    case Adhoc = 'adhoc';
    case SpotCheck = 'spot_check';
    case CycleAbc = 'cycle_abc';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Annual => 'Tahunan',
            self::Adhoc => 'Ad-hoc',
            self::SpotCheck => 'Pemeriksaan mendadak',
            self::CycleAbc => 'Cycle count ABC',
        };
    }

    /** @return array<string, string> jenis yang bisa dipilih di Fase 1 */
    public static function options(): array
    {
        $hasil = [];

        foreach (self::cases() as $case) {
            if ($case->isAvailable()) {
                $hasil[$case->value] = $case->label();
            }
        }

        return $hasil;
    }

    public function isAvailable(): bool
    {
        return $this !== self::CycleAbc;
    }

    /** BR-OPN-10: pemeriksaan mendadak tanpa pembekuan dan tanpa ADJ sendiri. */
    public function isSpotCheck(): bool
    {
        return $this === self::SpotCheck;
    }
}
