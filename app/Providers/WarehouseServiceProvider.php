<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Warehouse\Livewire\BinList;
use App\Domain\Warehouse\Livewire\WarehouseDetail;
use App\Domain\Warehouse\Livewire\WarehouseList;
use App\Domain\Warehouse\Livewire\WarehouseTypeList;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseType;
use App\Domain\Warehouse\Policies\BinPolicy;
use App\Domain\Warehouse\Policies\WarehousePolicy;
use App\Domain\Warehouse\Policies\WarehouseTypePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Modul Warehouse: policy per entitas dan komponen Livewire modul.
 *
 * Migrasi tenant dijalankan lewat `tenants:migrate` dengan path dari
 * config/tenancy.php, jadi provider ini tidak memuat migrasi apa pun.
 */
class WarehouseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerLivewireComponents();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Bin::class, BinPolicy::class);
        Gate::policy(WarehouseType::class, WarehouseTypePolicy::class);
    }

    /** Kelasnya ada di folder domain, bukan App\Livewire, jadi didaftarkan manual (AD-02). */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('warehouse.warehouse-list', WarehouseList::class);
        Livewire::component('warehouse.warehouse-detail', WarehouseDetail::class);
        Livewire::component('warehouse.bin-list', BinList::class);
        Livewire::component('warehouse.type-list', WarehouseTypeList::class);
    }
}
