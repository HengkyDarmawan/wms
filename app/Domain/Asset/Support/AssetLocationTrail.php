<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Models\Bin;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Jejak lokasi satu aset (A-251): kartu stok serial diringkas menjadi urutan
 * tempat yang mudah dibaca — "Gudang CKG → Dalam perjalanan → Proyek A
 * (12 hari) → Proyek B (sekarang)". Pergerakan di dalam satu gudang (mis.
 * Loading Area → bin simpan) digabung menjadi satu tempat.
 */
class AssetLocationTrail
{
    /**
     * @return Collection<int, array{key: string, label: string, project_id: ?int, dari: CarbonInterface, sampai: ?CarbonInterface, hari: int, sekarang: bool}>
     */
    public function for(Serial $serial): Collection
    {
        $gerak = StockMovement::query()->where('serial_id', $serial->id)->orderBy('occurred_at')->orderBy('id')
            ->get(['id', 'to_bin_id', 'occurred_at']);

        $bin = Bin::query()->withoutGlobalScopes()->with('warehouse:id,code')
            ->whereIn('id', $gerak->pluck('to_bin_id')->filter()->unique())->get()->keyBy('id');
        $proyek = Project::query()->withoutGlobalScopes()->whereIn('id', $bin->pluck('project_id')->filter()->unique())->get(['id', 'code', 'name'])->keyBy('id');

        $jejak = [];

        foreach ($gerak as $m) {
            [$kunci, $label, $proyekId] = $this->tempat($m->to_bin_id !== null ? $bin->get($m->to_bin_id) : null, $proyek);

            if ($jejak !== [] && end($jejak)['key'] === $kunci) {
                continue;
            }

            $jejak[] = ['key' => $kunci, 'label' => $label, 'project_id' => $proyekId, 'dari' => $m->occurred_at];
        }

        $zona = tenant()?->timezone ?? 'Asia/Jakarta';

        return collect($jejak)->values()->map(function (array $t, int $i) use ($jejak, $zona) {
            $sampai = $jejak[$i + 1]['dari'] ?? null;
            $akhir = $sampai ?? now();

            return $t + [
                'sampai' => $sampai,
                'hari' => (int) $t['dari']->copy()->setTimezone($zona)->startOfDay()->diffInDays($akhir->copy()->setTimezone($zona)->startOfDay()) + 1,
                'sekarang' => $sampai === null,
            ];
        });
    }

    /**
     * @param  Collection<int, Project>  $proyek
     * @return array{0: string, 1: string, 2: ?int}
     */
    private function tempat(?Bin $bin, Collection $proyek): array
    {
        if ($bin === null) {
            return ['keluar', __('Keluar dari catatan (hilang/dihapusbukukan)'), null];
        }

        return match ($bin->bin_type) {
            BinType::OnSite => ['proyek:'.$bin->project_id, __('Proyek').' '.($proyek->get($bin->project_id)?->code ?? '#'.$bin->project_id), $bin->project_id !== null ? (int) $bin->project_id : null],
            BinType::InTransit => ['transit:'.$bin->warehouse_id, __('Dalam perjalanan'), null],
            default => ['gudang:'.$bin->warehouse_id, __('Gudang').' '.$bin->warehouse?->code, null],
        };
    }
}
