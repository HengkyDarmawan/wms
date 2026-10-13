<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — geser urutan tampil zona atau rak satu langkah
 * naik/turun (A-410). Hanya urutan tampil; kode, kode bin, dan posisi di
 * denah tidak berubah. Tingkat tidak digeser: L1 selalu paling bawah.
 */
class ReorderLocation
{
    /** @param  int  $arah  -1 = naik, 1 = turun */
    public function zone(Zone $zone, int $arah, ?User $actor = null): void
    {
        $this->geser(Zone::query()->where('warehouse_id', $zone->warehouse_id), $zone, $arah, 'Zona', $actor);
    }

    /** @param  int  $arah  -1 = naik, 1 = turun */
    public function rack(Rack $rack, int $arah, ?User $actor = null): void
    {
        $this->geser(Rack::query()->where('zone_id', $rack->zone_id), $rack, $arah, 'Rak', $actor);
    }

    /** Urutan dirapikan 1..n dulu (data lama bisa kembar), lalu ditukar dengan tetangganya. */
    private function geser(mixed $query, Zone|Rack $model, int $arah, string $label, ?User $actor): void
    {
        DB::transaction(function () use ($query, $model, $arah, $label, $actor): void {
            $urut = $query->orderBy('sort_order')->orderBy('code')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $i = array_search((int) $model->id, $urut, true);
            $j = $i === false ? false : $i + ($arah < 0 ? -1 : 1);

            if ($i === false || $j < 0 || $j >= count($urut)) {
                return;
            }

            [$urut[$i], $urut[$j]] = [$urut[$j], $urut[$i]];

            foreach ($urut as $n => $id) {
                $model::query()->whereKey($id)->update(['sort_order' => $n + 1]);
            }

            activity('warehouse')->performedOn($model)->causedBy($actor)
                ->log($label.' '.$model->code.' digeser '.($arah < 0 ? 'naik' : 'turun'));
        });
    }
}
