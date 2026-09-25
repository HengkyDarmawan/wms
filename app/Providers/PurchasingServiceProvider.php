<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\PurchaseRequest\Events\OrderLinesReceived;
use App\Domain\Purchasing\Livewire\PurchaseOrderDetail;
use App\Domain\Purchasing\Livewire\PurchaseOrderForm;
use App\Domain\Purchasing\Livewire\PurchaseOrderList;
use App\Domain\Purchasing\Livewire\VendorPriceList;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\VendorPrice;
use App\Domain\Purchasing\Policies\PurchaseOrderPolicy;
use App\Domain\Purchasing\Policies\VendorPricePolicy;
use App\Domain\Purchasing\Support\PurchaseOrderApprovalHandler;
use App\Domain\Purchasing\Support\PurchaseOrderReceipts;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Purchasing inti Fase 1b (D-29, purchasing/02): policy, penangan approval
 * PO (nilai uang, D-28), pendengar penerimaan barang PRQ, komponen Livewire.
 */
class PurchasingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(VendorPrice::class, VendorPricePolicy::class);

        // PO: approval opsional berbasis nilai; tanpa aturan = disetujui otomatis (A-08, A-212).
        $this->app->make(ApprovalRegistry::class)->register(ApprovalDocumentType::PurchaseOrder, PurchaseOrderApprovalHandler::class);

        // GRN vendor → jumlah diterima baris PO (A-214).
        Event::listen(OrderLinesReceived::class, [PurchaseOrderReceipts::class, 'handle']);

        Livewire::component('purchasing.purchase-order-list', PurchaseOrderList::class);
        Livewire::component('purchasing.purchase-order-form', PurchaseOrderForm::class);
        Livewire::component('purchasing.purchase-order-detail', PurchaseOrderDetail::class);
        Livewire::component('purchasing.vendor-price-list', VendorPriceList::class);
    }
}
