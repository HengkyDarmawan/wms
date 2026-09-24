<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Receipt\Livewire\PutawayDetail;
use App\Domain\Receipt\Livewire\PutawayList;
use App\Domain\Receipt\Livewire\ReceiptDetail;
use App\Domain\Receipt\Livewire\ReceiptForm;
use App\Domain\Receipt\Livewire\ReceiptList;
use App\Domain\Receipt\Livewire\VendorReturnDetail;
use App\Domain\Receipt\Livewire\VendorReturnForm;
use App\Domain\Receipt\Livewire\VendorReturnList;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Receipt\Models\VendorReturn;
use App\Domain\Receipt\Policies\GoodsReceiptPolicy;
use App\Domain\Receipt\Policies\PutawayTaskPolicy;
use App\Domain\Receipt\Policies\VendorReturnPolicy;
use App\Domain\Receipt\Support\VendorReturnApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Receipt/Putaway: policy per entitas dan komponen Livewire (AD-02). */
class ReceiptServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(PutawayTask::class, PutawayTaskPolicy::class);
        Gate::policy(VendorReturn::class, VendorReturnPolicy::class);

        // RTV diputus lewat mesin approval (20-approval §13, A-93).
        $this->app->make(ApprovalRegistry::class)
            ->register(ApprovalDocumentType::VendorReturn, VendorReturnApprovalHandler::class);

        Livewire::component('receipt.receipt-list', ReceiptList::class);
        Livewire::component('receipt.receipt-form', ReceiptForm::class);
        Livewire::component('receipt.receipt-detail', ReceiptDetail::class);
        Livewire::component('receipt.putaway-list', PutawayList::class);
        Livewire::component('receipt.putaway-detail', PutawayDetail::class);
        Livewire::component('receipt.vendor-return-list', VendorReturnList::class);
        Livewire::component('receipt.vendor-return-form', VendorReturnForm::class);
        Livewire::component('receipt.vendor-return-detail', VendorReturnDetail::class);
    }
}
