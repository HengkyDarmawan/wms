<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Request\Livewire\PortalRequestDetail;
use App\Domain\Request\Livewire\PortalRequestList;
use App\Domain\Request\Livewire\RequestDetail;
use App\Domain\Request\Livewire\RequestForm;
use App\Domain\Request\Livewire\RequestList;
use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Request\Policies\MaterialRequestPolicy;
use App\Domain\Request\Support\RequestApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Request: policy REQ dan komponen Livewire modul (AD-02). */
class RequestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(MaterialRequest::class, MaterialRequestPolicy::class);

        // REQ diputus lewat mesin approval (20-approval §13, D-28).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::MaterialRequest, RequestApprovalHandler::class);

        Livewire::component('request.request-list', RequestList::class);
        Livewire::component('request.request-form', RequestForm::class);
        Livewire::component('request.request-detail', RequestDetail::class);
        Livewire::component('request.portal-request-list', PortalRequestList::class);
        Livewire::component('request.portal-request-detail', PortalRequestDetail::class);
    }
}
