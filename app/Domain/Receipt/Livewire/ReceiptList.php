<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Enums\ReceiptType;
use App\Domain\Receipt\Models\GoodsReceipt;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 19-receipt-putaway §6.1 — daftar GRN, plus SJ transfer yang sudah
 * sampai di gudang dalam cakupan tetapi belum diterima.
 */
class ReceiptList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GoodsReceipt::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.receipt.receipt-list', [
            'receipts' => $this->daftar(),
            'statuses' => GoodsReceiptStatus::options(),
            'types' => ReceiptType::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'incoming' => $this->sjMenunggu(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return GoodsReceipt::query()
            ->with('warehouse:id,code,name', 'vendor:id,name', 'shipment:id,number')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhere('vendor_doc_no', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('receipt_type', $this->typeFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** @return Collection<int, Shipment> SJ ke gudang dalam cakupan, sudah ada bukti terima, belum ber-GRN. */
    private function sjMenunggu(): Collection
    {
        $gudang = Warehouse::query()->pluck('id')->all();

        return Shipment::query()->withoutGlobalScopes()
            ->with('warehouse:id,code', 'destinationWarehouse:id,code')
            ->whereIn('destination_type', ['warehouse', 'site_warehouse'])
            ->whereIn('destination_warehouse_id', $gudang)
            ->whereIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::PartiallyDelivered->value])
            ->whereNotIn('id', GoodsReceipt::query()->withoutGlobalScopes()->active()->whereNotNull('shipment_id')->select('shipment_id'))
            ->orderBy('delivered_at')
            ->limit(20)
            ->get();
    }
}
