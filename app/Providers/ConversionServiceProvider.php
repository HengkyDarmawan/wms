<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalRegistry;
use App\Domain\Conversion\Livewire\ConversionDetail;
use App\Domain\Conversion\Livewire\ConversionForm;
use App\Domain\Conversion\Livewire\ConversionList;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Conversion\Policies\ConversionPolicy;
use App\Domain\Conversion\Support\ConversionApprovalHandler;
use App\Domain\Waste\Livewire\WasteDisposalDetail;
use App\Domain\Waste\Livewire\WasteDisposalForm;
use App\Domain\Waste\Livewire\WasteDisposalList;
use App\Domain\Waste\Models\WasteDisposal;
use App\Domain\Waste\Policies\WasteDisposalPolicy;
use App\Domain\Waste\Support\WasteDisposalApprovalHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Modul Konversi & Waste (CNV, WST): policy, penangan approval, komponen Livewire (AD-02, 24-konversi-waste). */
class ConversionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Conversion::class, ConversionPolicy::class);
        Gate::policy(WasteDisposal::class, WasteDisposalPolicy::class);

        $registry = $this->app->make(ApprovalRegistry::class);
        // CNV: approval opsional, hanya bila ada aturan (A-153). WST: tanpa aturan = disetujui otomatis (A-08).
        $registry->register(ApprovalDocumentType::Conversion, ConversionApprovalHandler::class);
        $registry->register(ApprovalDocumentType::WasteDisposal, WasteDisposalApprovalHandler::class);

        Livewire::component('conversion.conversion-list', ConversionList::class);
        Livewire::component('conversion.conversion-form', ConversionForm::class);
        Livewire::component('conversion.conversion-detail', ConversionDetail::class);
        Livewire::component('waste.waste-disposal-list', WasteDisposalList::class);
        Livewire::component('waste.waste-disposal-form', WasteDisposalForm::class);
        Livewire::component('waste.waste-disposal-detail', WasteDisposalDetail::class);
    }
}
