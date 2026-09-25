<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\PurchaseRequest\Console\GenerateReorderRequestsCommand;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestDetail;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestForm;
use App\Domain\PurchaseRequest\Livewire\PurchaseRequestList;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Policies\PurchaseRequestPolicy;
use App\Domain\PurchaseRequest\Support\PurchaseRequestApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Purchase Request (PRQ): policy, penangan approval, job titik pesan ulang, komponen Livewire (AD-02, 26-purchase-request). */
class PurchaseRequestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);

        // PRQ: approval opsional; tanpa aturan = disetujui otomatis (A-08).
        $this->app->make(ApprovalRegistry::class)->register(ApprovalDocumentType::PurchaseRequest, PurchaseRequestApprovalHandler::class);

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateReorderRequestsCommand::class]);
        }

        Livewire::component('purchase-request.purchase-request-list', PurchaseRequestList::class);
        Livewire::component('purchase-request.purchase-request-form', PurchaseRequestForm::class);
        Livewire::component('purchase-request.purchase-request-detail', PurchaseRequestDetail::class);
    }
}
