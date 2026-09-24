<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Count\Enums\VarianceClass;
use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Item;

/**
 * Klasifikasi selisih opname (BR-OPN-04, A-42, A-99).
 *
 * - `minor` (kecil, otomatis) bila selisih ≤ ambang **relatif** DAN ≤ ambang
 *   **absolut** — bawaan 1 % dan 1 unit dasar;
 * - `moderate` (sedang, hitung ulang) bila ≤ 5 %;
 * - selebihnya `major` (besar, approval + akar masalah).
 *
 * Ambang kategori item (`tolerance_pct`, `tolerance_abs`, diwarisi dari induk)
 * menang atas ambang company (`count_tolerance_pct`, `count_tolerance_abs`,
 * `count_moderate_pct`). Angka sistem nol tetapi ada barang = selisih relatif
 * tak terhingga → `major`. Tanpa selisih = tanpa kelas.
 */
class VarianceClassifier
{
    public const TOLERANSI_PERSEN = 'count_tolerance_pct';

    public const TOLERANSI_MUTLAK = 'count_tolerance_abs';

    public const BATAS_SEDANG = 'count_moderate_pct';

    /** @var array<int, array{pct: float, abs: float}> */
    private array $cache = [];

    /**
     * @return array{variance: float, pct: ?float, class: ?VarianceClass}
     */
    public function classify(float $systemQty, float $finalQty, Item $item): array
    {
        $selisih = round($finalQty - $systemQty, 4);

        if (abs($selisih) < 0.00005) {
            return ['variance' => 0.0, 'pct' => $systemQty > 0 ? 0.0 : null, 'class' => null];
        }

        $pct = $systemQty > 0 ? round($selisih / $systemQty * 100, 4) : null;
        $pctMutlak = $pct === null ? INF : abs($pct);
        $ambang = $this->ambang($item);

        $kelas = match (true) {
            $pctMutlak <= $ambang['pct'] && abs($selisih) <= $ambang['abs'] => VarianceClass::Minor,
            $pctMutlak <= (float) CompanySetting::get(self::BATAS_SEDANG, 5) => VarianceClass::Moderate,
            default => VarianceClass::Major,
        };

        return [
            'variance' => $selisih,
            'pct' => $pct === null ? null : max(-9999.9999, min(9999.9999, $pct)),
            'class' => $kelas,
        ];
    }

    /** @return array{pct: float, abs: float} */
    public function ambang(Item $item): array
    {
        $kunci = (int) $item->id;

        if (! isset($this->cache[$kunci])) {
            $kategori = $item->category?->effectiveTolerance() ?? ['pct' => null, 'abs' => null];

            $this->cache[$kunci] = [
                'pct' => (float) ($kategori['pct'] ?? CompanySetting::get(self::TOLERANSI_PERSEN, 1)),
                'abs' => (float) ($kategori['abs'] ?? CompanySetting::get(self::TOLERANSI_MUTLAK, 1)),
            ];
        }

        return $this->cache[$kunci];
    }
}
