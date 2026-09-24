<?php

declare(strict_types=1);

namespace App\Domain\Count\Support;

use App\Domain\Count\Models\StockCount;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Models\Bin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Menerjemahkan cakupan sesi (`stock_counts.scope`) menjadi daftar bin dan
 * baris snapshot (Blueprint §9, BR-OPN-01, A-100).
 *
 * Bin yang dihitung: bin fisik aktif/beku di gudang cakupan — penyimpanan,
 * Penerimaan, Loading Area, Karantina, Retur, Waste. Bin virtual (Dalam
 * Perjalanan, On-site) tidak bisa dihitung di gudang. Urutan: bin berpenanda
 * hitung (`count_flag`, A-67) dulu, lalu kode.
 */
class CountScope
{
    /** @return Collection<int, Bin> */
    public function bins(StockCount $count): Collection
    {
        $scope = $count->scope ?? [];
        $binIds = array_map('intval', (array) ($scope['bin_ids'] ?? []));
        $zoneIds = array_map('intval', (array) ($scope['zone_ids'] ?? []));
        $itemIds = array_map('intval', (array) ($scope['item_ids'] ?? []));

        return Bin::withoutGlobalScopes()
            ->whereIn('warehouse_id', $count->warehouseIds())
            ->where('is_virtual', false)
            ->where('bin_status', '!=', BinStatus::Inactive->value)
            ->whereNotIn('bin_type', ['in_transit', 'on_site'])
            ->when($binIds !== [], fn (Builder $q) => $q->whereIn('id', $binIds))
            ->when($binIds === [] && $zoneIds !== [], fn (Builder $q) => $q->whereHas(
                'rackLevel',
                fn (Builder $l) => $l->whereHas('rack', fn (Builder $r) => $r->whereIn('zone_id', $zoneIds)),
            ))
            // Cakupan per item tanpa daftar bin: bin yang memegang item itu saja.
            ->when($binIds === [] && $itemIds !== [], fn (Builder $q) => $q->whereIn('id', StockBalance::query()
                ->whereIn('item_id', $itemIds)->where('qty_base', '>', 0)->select('bin_id')))
            ->orderByDesc('count_flag')
            ->orderBy('code')
            ->get();
    }

    /**
     * Saldo fisik per bin × item × lot/serial/potongan × kondisi saat sesi
     * mulai — termasuk yang dicadangkan dan yang di Loading Area (BR-OPN-01).
     *
     * @param  array<int, int>  $binIds
     * @return Collection<int, StockBalance>
     */
    public function snapshot(StockCount $count, array $binIds): Collection
    {
        $itemIds = array_map('intval', (array) (($count->scope ?? [])['item_ids'] ?? []));

        return StockBalance::query()
            ->whereIn('bin_id', $binIds)
            ->where('qty_base', '>', 0)
            ->when($itemIds !== [], fn (Builder $q) => $q->whereIn('item_id', $itemIds))
            ->orderBy('bin_id')->orderBy('item_id')->orderBy('id')
            ->get();
    }
}
