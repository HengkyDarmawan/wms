<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shipment\Livewire\DiscrepancyList;
use App\Domain\Shipment\Livewire\PickDetail;
use App\Domain\Shipment\Livewire\PickList;
use App\Domain\Shipment\Livewire\ShipmentDetail;
use App\Domain\Shipment\Livewire\ShipmentForm;
use App\Domain\Shipment\Livewire\ShipmentList;
use App\Domain\Shipment\Models\DeliveryDiscrepancy;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Policies\DiscrepancyPolicy;
use App\Domain\Shipment\Policies\PickTaskPolicy;
use App\Domain\Shipment\Policies\ShipmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Picking & Shipment: policy per entitas dan komponen Livewire (AD-02). */
class ShipmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(PickTask::class, PickTaskPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(DeliveryDiscrepancy::class, DiscrepancyPolicy::class);

        Livewire::component('shipment.pick-list', PickList::class);
        Livewire::component('shipment.pick-detail', PickDetail::class);
        Livewire::component('shipment.shipment-list', ShipmentList::class);
        Livewire::component('shipment.shipment-form', ShipmentForm::class);
        Livewire::component('shipment.shipment-detail', ShipmentDetail::class);
        Livewire::component('shipment.discrepancy-list', DiscrepancyList::class);
    }
}
