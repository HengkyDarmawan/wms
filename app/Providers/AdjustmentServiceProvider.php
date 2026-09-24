<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Adjustment\Livewire\AdjustmentDetail;
use App\Domain\Adjustment\Livewire\AdjustmentForm;
use App\Domain\Adjustment\Livewire\AdjustmentList;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Adjustment\Policies\StockAdjustmentPolicy;
use App\Domain\Adjustment\Support\StockAdjustmentApprovalHandler;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Adjustment (penyesuaian stok): policy, penangan approval ADJ, komponen Livewire (AD-02). */
class AdjustmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);

        // ADJ manual selalu lewat mesin approval, minimal satu lapis (A-09).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::StockAdjustment, StockAdjustmentApprovalHandler::class);

        Livewire::component('adjustment.adjustment-list', AdjustmentList::class);
        Livewire::component('adjustment.adjustment-form', AdjustmentForm::class);
        Livewire::component('adjustment.adjustment-detail', AdjustmentDetail::class);
    }
}
