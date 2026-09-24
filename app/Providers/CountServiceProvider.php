<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Count\Livewire\CountEntry;
use App\Domain\Count\Livewire\MyCountTasks;
use App\Domain\Count\Livewire\StockCountDetail;
use App\Domain\Count\Livewire\StockCountForm;
use App\Domain\Count\Livewire\StockCountList;
use App\Domain\Count\Models\CountAssignment;
use App\Domain\Count\Models\StockCount;
use App\Domain\Count\Policies\CountAssignmentPolicy;
use App\Domain\Count\Policies\StockCountPolicy;
use App\Domain\Count\Support\StockCountApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Count (stock opname): policy, penangan approval OPN, komponen Livewire (AD-02). */
class CountServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(StockCount::class, StockCountPolicy::class);
        Gate::policy(CountAssignment::class, CountAssignmentPolicy::class);

        // Hasil opname disetujui di tingkat sesi lewat mesin approval (BR-OPN-06, BR-OPN-09).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::StockCount, StockCountApprovalHandler::class);

        Livewire::component('count.count-list', StockCountList::class);
        Livewire::component('count.count-form', StockCountForm::class);
        Livewire::component('count.count-detail', StockCountDetail::class);
        Livewire::component('count.my-tasks', MyCountTasks::class);
        Livewire::component('count.count-entry', CountEntry::class);
    }
}
