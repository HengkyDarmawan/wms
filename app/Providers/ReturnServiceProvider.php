<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Return\Livewire\ReturnDetail;
use App\Domain\Return\Livewire\ReturnForm;
use App\Domain\Return\Livewire\ReturnList;
use App\Domain\Return\Models\GoodsReturn;
use App\Domain\Return\Policies\GoodsReturnPolicy;
use App\Domain\Return\Support\GoodsReturnApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Return (RET, retur dari proyek): policy, penangan approval, komponen Livewire (AD-02, 22-retur-transfer). */
class ReturnServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(GoodsReturn::class, GoodsReturnPolicy::class);

        // RET diputus lewat mesin approval; tanpa aturan disetujui otomatis (A-08).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::GoodsReturn, GoodsReturnApprovalHandler::class);

        Livewire::component('return.return-list', ReturnList::class);
        Livewire::component('return.return-form', ReturnForm::class);
        Livewire::component('return.return-detail', ReturnDetail::class);
    }
}
