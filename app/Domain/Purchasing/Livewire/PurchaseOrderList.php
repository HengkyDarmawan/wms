<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Livewire;

use App\Domain\Master\Models\Vendor;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar purchasing/02 §6 — daftar PO di gudang tujuan dalam cakupan pengguna. */
class PurchaseOrderList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(as: 'vendor', except: '')]
    public string $vendorFilter = '';

    #[Url(as: 'warehouse', except: '')]
    public string $warehouseFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PurchaseOrder::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.purchasing.purchase-order-list', [
            'orders' => $this->daftar(),
            'statuses' => PurchaseOrderStatus::options(),
            'vendors' => Vendor::query()->whereIn('id', PurchaseOrder::query()->select('vendor_id'))->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with('vendor:id,code,name', 'warehouse:id,code', 'creator:id,name')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('name', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%'))
                ->orWhereHas('lines.requestLine.purchaseRequest', fn (Builder $p) => $p->withoutGlobalScopes()->where('number', 'like', '%'.$this->search.'%'))))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->vendorFilter !== '', fn (Builder $q) => $q->where('vendor_id', (int) $this->vendorFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
