<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Livewire;

use App\Domain\PurchaseRequest\Enums\PurchaseRequestOrigin;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 26-purchase-request §6.1 — daftar PRQ di gudang tujuan dalam cakupan pengguna. */
class PurchaseRequestList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(as: 'origin', except: '')]
    public string $originFilter = '';

    #[Url(as: 'warehouse', except: '')]
    public string $warehouseFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PurchaseRequest::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.purchase-request.purchase-request-list', [
            'requests' => $this->daftar(),
            'statuses' => PurchaseRequestStatus::options(),
            'origins' => PurchaseRequestOrigin::options(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return PurchaseRequest::query()
            ->with('warehouse:id,code', 'project:id,code', 'materialRequest:id,number', 'creator:id,name')
            ->withCount('lines', 'orders')
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('orders', fn (Builder $o) => $o->where('external_po_no', 'like', '%'.$this->search.'%')
                    ->orWhere('marketplace_order_no', 'like', '%'.$this->search.'%'))))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->originFilter !== '', fn (Builder $q) => $q->where('origin', $this->originFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
