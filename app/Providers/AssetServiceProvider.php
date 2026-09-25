<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Asset\Livewire\AssetDetail;
use App\Domain\Asset\Livewire\AssetList;
use App\Domain\Asset\Livewire\HandoverDetail;
use App\Domain\Asset\Livewire\HandoverList;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Asset\Policies\AssetHandoverPolicy;
use App\Domain\Asset\Policies\AssetPolicy;
use App\Domain\Asset\Support\AssetStateSync;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockMovement;
use App\Domain\Stock\Models\StockReservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Modul Aset dipinjamkan (AST): policy, sinkron state aset dari kartu stok dan
 * reservasi (BR-AST-01, A-164), komponen Livewire (AD-02, 25-aset).
 */
class AssetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AssetHandover::class, AssetHandoverPolicy::class);
        Gate::policy(Serial::class, AssetPolicy::class);

        // State aset mengikuti setiap pergerakan & alokasi, dari modul mana pun.
        StockMovement::created(fn (StockMovement $m) => app(AssetStateSync::class)->fromMovement($m));
        StockReservation::saved(fn (StockReservation $r) => app(AssetStateSync::class)->fromReservation($r));

        Livewire::component('asset.asset-list', AssetList::class);
        Livewire::component('asset.asset-detail', AssetDetail::class);
        Livewire::component('asset.handover-list', HandoverList::class);
        Livewire::component('asset.handover-detail', HandoverDetail::class);
    }
}
