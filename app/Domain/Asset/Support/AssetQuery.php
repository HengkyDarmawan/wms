<?php

declare(strict_types=1);

namespace App\Domain\Asset\Support;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\OwnershipModel;
use App\Domain\Master\Models\Serial;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar aset = serial item `asset`/`both`, dibatasi cakupan gudang pengguna
 * (BR-ACC-05, A-168): aset terlihat bila binnya berada di gudang dalam
 * cakupan, atau serah terima terakhirnya berasal dari gudang dalam cakupan.
 */
class AssetQuery
{
    /** @return Builder<Serial> */
    public function for(?User $user): Builder
    {
        $gudang = $user?->accessibleWarehouseIds();

        return Serial::query()
            ->whereHas('item', fn (Builder $q) => $q->whereIn('ownership_model', [OwnershipModel::Asset->value, OwnershipModel::Both->value]))
            ->when($gudang !== null, fn (Builder $q) => $q->where(function (Builder $w) use ($gudang) {
                $w->whereExists(fn ($s) => $s->selectRaw('1')->from('stock_balances as b')
                    ->join('bins as n', 'n.id', '=', 'b.bin_id')
                    ->whereColumn('b.serial_id', 'serials.id')
                    ->where('b.qty_base', '>', 0)
                    ->whereIn('n.warehouse_id', $gudang === [] ? [0] : $gudang))
                    ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('asset_handovers as h')
                        ->whereColumn('h.serial_id', 'serials.id')
                        ->whereIn('h.warehouse_id', $gudang === [] ? [0] : $gudang));
            }));
    }
}
