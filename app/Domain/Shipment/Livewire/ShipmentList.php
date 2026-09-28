<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Shipment\Enums\DestinationType;
use App\Domain\Shipment\Enums\ShipmentMethod;
use App\Domain\Shipment\Enums\ShipmentStatus;
use App\Domain\Shipment\Models\Shipment;
use App\Domain\Shipment\Support\ShipmentSearch;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 15-picking-shipment §6 — daftar surat jalan.
 *
 * SJ yang menyisakan selisih ditandai: itulah yang masih menunggu keputusan,
 * meskipun statusnya terlihat sudah "diterima sebagian" dan mudah terlewat.
 */
class ShipmentList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    #[Url(except: '')]
    public string $methodFilter = '';

    #[Url(except: false)]
    public bool $hanyaBerselisih = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Shipment::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.shipment.shipment-list', [
            'shipments' => $this->daftar(),
            'statuses' => ShipmentStatus::options(),
            'methods' => ShipmentMethod::options(),
            'destinations' => DestinationType::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return Shipment::visibleTo(auth()->user())
            ->with(
                'warehouse:id,code,name',
                'destinationProject:id,code,name,client_id',
                'destinationWarehouse:id,code,name',
                'destinationVendor:id,code,name',
                'vehicle:id,plate_no',
                'carrier:id,name',
            )
            ->withCount([
                'lines',
                'discrepancies as open_discrepancies_count' => fn (Builder $q) => $q->open(),
            ])
            // A-313: No. PO klien (REQ) dan No. GR klien (bukti terima) ikut dicari.
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhere('tracking_no', 'like', '%'.$this->search.'%')
                ->orWhere('driver_name', 'like', '%'.$this->search.'%')
                ->orWhereHas('proof', fn (Builder $p) => $p->where('client_gr_number', 'like', '%'.$this->search.'%'))
                ->orWhereIn('id', ShipmentSearch::byClientPo($this->search))))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->where('warehouse_id', (int) $this->warehouseFilter))
            ->when($this->methodFilter !== '', fn (Builder $q) => $q->where('shipment_method', $this->methodFilter))
            ->when($this->hanyaBerselisih, fn (Builder $q) => $q
                ->whereHas('discrepancies', fn (Builder $d) => $d->open()))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
