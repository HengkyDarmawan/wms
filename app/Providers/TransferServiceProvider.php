<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Transfer\Livewire\ProjectMove;
use App\Domain\Transfer\Livewire\TransferDetail;
use App\Domain\Transfer\Livewire\TransferForm;
use App\Domain\Transfer\Livewire\TransferList;
use App\Domain\Transfer\Models\Transfer;
use App\Domain\Transfer\Policies\TransferPolicy;
use App\Domain\Transfer\Support\TransferApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Transfer (TRF): policy, penangan approval, komponen Livewire (AD-02, 22-retur-transfer). */
class TransferServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Transfer::class, TransferPolicy::class);

        // TRF diputus lewat mesin approval; tanpa aturan disetujui otomatis (A-08, BR-RET-02).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::Transfer, TransferApprovalHandler::class);

        Livewire::component('transfer.transfer-list', TransferList::class);
        Livewire::component('transfer.transfer-form', TransferForm::class);
        Livewire::component('transfer.transfer-detail', TransferDetail::class);
        Livewire::component('transfer.project-move', ProjectMove::class);
    }
}
