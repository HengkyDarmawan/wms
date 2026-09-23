<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Stock\Livewire\BalanceList;
use App\Domain\Stock\Livewire\EventList;
use App\Domain\Stock\Livewire\PeriodLock;
use App\Domain\Stock\Livewire\ReservationList;
use App\Domain\Stock\Livewire\StockCard;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Stock\Models\StockEvent;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Stock\Policies\ReservationPolicy;
use App\Domain\Stock\Policies\StockEventPolicy;
use App\Domain\Stock\Policies\StockPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Stock: policy per entitas dan komponen Livewire modul (AD-02). */
class StockServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(StockBalance::class, StockPolicy::class);
        Gate::policy(StockReservation::class, ReservationPolicy::class);
        Gate::policy(StockEvent::class, StockEventPolicy::class);

        Livewire::component('stock.balance-list', BalanceList::class);
        Livewire::component('stock.stock-card', StockCard::class);
        Livewire::component('stock.reservation-list', ReservationList::class);
        Livewire::component('stock.event-list', EventList::class);
        Livewire::component('stock.period-lock', PeriodLock::class);
    }
}
