<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Master\Livewire\ClientList;
use App\Domain\Master\Livewire\ItemCategoryList;
use App\Domain\Master\Livewire\ItemDetail;
use App\Domain\Master\Livewire\ItemForm;
use App\Domain\Master\Livewire\ItemList;
use App\Domain\Master\Livewire\ProjectList;
use App\Domain\Master\Livewire\ReferenceList;
use App\Domain\Master\Livewire\UomList;
use App\Domain\Master\Livewire\VendorList;
use App\Domain\Master\Models\Carrier;
use App\Domain\Master\Models\Client;
use App\Domain\Master\Models\Item;
use App\Domain\Master\Models\ItemCategory;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Master\Models\Uom;
use App\Domain\Master\Models\UomCategory;
use App\Domain\Master\Models\Vehicle;
use App\Domain\Master\Models\Vendor;
use App\Domain\Master\Policies\ClientPolicy;
use App\Domain\Master\Policies\ItemCategoryPolicy;
use App\Domain\Master\Policies\ItemPolicy;
use App\Domain\Master\Policies\ProjectPolicy;
use App\Domain\Master\Policies\ReferencePolicy;
use App\Domain\Master\Policies\UomPolicy;
use App\Domain\Master\Policies\VendorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Modul Master: policy per entitas dan komponen Livewire modul.
 *
 * Migrasi tenant dijalankan lewat `tenants:migrate` dengan path dari
 * config/tenancy.php, jadi provider ini tidak memuat migrasi apa pun.
 */
class MasterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerLivewireComponents();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(ItemCategory::class, ItemCategoryPolicy::class);

        // Satuan dan kategorinya memakai satu izin (11-master §2).
        Gate::policy(Uom::class, UomPolicy::class);
        Gate::policy(UomCategory::class, UomPolicy::class);

        // Master kecil di layar Referensi.
        Gate::policy(ReasonCode::class, ReferencePolicy::class);
        Gate::policy(StorageCategory::class, ReferencePolicy::class);
        Gate::policy(Vehicle::class, ReferencePolicy::class);
        Gate::policy(Carrier::class, ReferencePolicy::class);
    }

    /** Kelasnya ada di folder domain, bukan App\Livewire, jadi didaftarkan manual (AD-02). */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('master.client-list', ClientList::class);
        Livewire::component('master.project-list', ProjectList::class);
        Livewire::component('master.vendor-list', VendorList::class);
        Livewire::component('master.item-list', ItemList::class);
        Livewire::component('master.item-form', ItemForm::class);
        Livewire::component('master.item-detail', ItemDetail::class);
        Livewire::component('master.item-category-list', ItemCategoryList::class);
        Livewire::component('master.uom-list', UomList::class);
        Livewire::component('master.reference-list', ReferenceList::class);
    }
}
