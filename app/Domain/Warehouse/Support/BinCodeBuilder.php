<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Support;

use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Support\Str;

/**
 * BR-WH-01 — kode bin diturunkan dari hierarki, tidak diketik manual.
 *
 * Bin berak:      {GUDANG}-{ZONA}-{RAK}-{LEVEL}-{BIN}   mis. CKG-A-R03-L2-B05
 * Bin sistem:     {GUDANG}-{AKHIRAN}                    mis. CKG-RCV
 * Bin on-site:    {GUDANG}-ONSITE-{PROYEK}              mis. KRW1-ONSITE-PRJ-001
 */
class BinCodeBuilder
{
    /** Membakukan satu segmen kode: huruf besar, tanpa pemisah asing. */
    public static function segment(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->value();
    }

    /** Kode bin yang menempel pada level rak. */
    public static function forRackLevel(RackLevel $level, string $binCode): string
    {
        $level->loadMissing('rack.zone.warehouse');

        $segmen = [
            self::segment((string) $level->rack?->zone?->warehouse?->code),
            self::segment((string) $level->rack?->zone?->code),
            self::segment((string) $level->rack?->code),
            self::segment((string) $level->code),
            self::segment($binCode),
        ];

        return implode('-', array_filter($segmen, fn (string $s) => $s !== ''));
    }

    /** Kode bin sistem milik gudang, mis. CKG-RCV. */
    public static function forSystemBin(Warehouse $warehouse, BinType $type): string
    {
        return self::segment((string) $warehouse->code).'-'.$type->codeSuffix();
    }

    /** Kode bin on-site, memakai kode proyek supaya satu bin per proyek terbaca. */
    public static function forOnSiteBin(Warehouse $warehouse, string $projectCode): string
    {
        return self::segment((string) $warehouse->code)
            .'-'.BinType::OnSite->codeSuffix()
            .'-'.self::segment($projectCode);
    }

    /**
     * Nomor bin berikutnya pada satu level, mis. B01 → B02.
     * Dipakai pembuat bin massal supaya tidak menabrak kode yang sudah ada.
     */
    public static function nextSequence(RackLevel $level, string $prefix = 'B'): string
    {
        $prefix = self::segment($prefix) ?: 'B';

        $terakhir = Bin::query()
            ->where('rack_level_id', $level->id)
            ->pluck('code')
            ->map(function (string $code) use ($prefix): int {
                $bagian = substr((string) strrchr($code, '-'), 1);

                if (! str_starts_with($bagian, $prefix)) {
                    return 0;
                }

                return (int) substr($bagian, strlen($prefix));
            })
            ->max() ?? 0;

        return $prefix.str_pad((string) ($terakhir + 1), 2, '0', STR_PAD_LEFT);
    }
}
