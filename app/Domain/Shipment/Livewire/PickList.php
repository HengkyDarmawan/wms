<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Livewire;

use App\Domain\Request\Models\MaterialRequest;
use App\Domain\Shipment\Actions\CreatePickTask;
use App\Domain\Shipment\Enums\PickTaskStatus;
use App\Domain\Shipment\Livewire\Concerns\HandlesShipmentRules;
use App\Domain\Shipment\Models\PickTask;
use App\Domain\Stock\Models\StockReservation;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 15-picking-shipment §6 — daftar tugas picking.
 *
 * Bagian atasnya menampilkan REQ disetujui yang belum punya PCK: pekerjaan yang
 * menunggu, bukan hanya pekerjaan yang sudah dibuat. Tanpa itu, tugas yang
 * belum dibuat tidak terlihat oleh siapa pun.
 */
class PickList extends Component
{
    use HandlesShipmentRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: true)]
    public bool $hanyaTerbuka = true;

    public function mount(): void
    {
        $this->authorize('viewAny', PickTask::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    /** Membuat PCK dari sebuah REQ; satu per gudang sumber. */
    public function buatDariReq(int $requestId, CreatePickTask $action): void
    {
        $this->authorize('create', PickTask::class);

        $req = MaterialRequest::findOrFail($requestId);

        if ($this->jalankan(fn () => $action->handle($req, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Tugas picking dibuat.'));
        }
    }

    public function render(): View
    {
        return view('livewire.shipment.pick-list', [
            'tasks' => $this->daftar(),
            'menunggu' => $this->reqMenungguPicking(),
            'statuses' => PickTaskStatus::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return PickTask::query()
            ->with('warehouse:id,code,name', 'assignee:id,name')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q
                ->where('warehouse_id', (int) $this->warehouseFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->hanyaTerbuka && $this->statusFilter === '', fn (Builder $q) => $q->open())
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * REQ yang sudah disetujui, punya baris bersumber stok, dan belum satu pun
     * barisnya masuk PCK yang masih hidup.
     *
     * @return Collection<int, MaterialRequest>
     */
    private function reqMenungguPicking(): Collection
    {
        if (! auth()->user()?->can('create', PickTask::class)) {
            return collect();
        }

        return MaterialRequest::query()
            ->with('project:id,code,name')
            ->whereIn('status', ['approved', 'in_progress', 'partially_fulfilled'])
            ->whereHas('lines', fn (Builder $l) => $l
                ->open()
                ->whereNotNull('source_warehouse_id')
                ->where(fn (Builder $w) => $w
                    ->where(fn (Builder $s) => $s
                        ->where('fulfillment_source', 'stock')
                        // `source_line_id` dipakai REQ, TRF, dan RET: saring sumbernya.
                        ->whereNotIn('id', PickTask::query()->withoutGlobalScopes()
                            ->where('pick_tasks.source_type', 'material_request')
                            ->whereNot('pick_tasks.status', PickTaskStatus::Cancelled->value)
                            ->join('pick_task_lines as ptl', 'ptl.pick_task_id', '=', 'pick_tasks.id')
                            ->select('ptl.source_line_id')))
                    // A-108: baris bersumber transfer yang barangnya sudah tiba dan direservasi.
                    ->orWhere(fn (Builder $t) => $t
                        ->where('fulfillment_source', 'transfer')
                        ->whereIn('id', StockReservation::query()->active()
                            ->where('level', 'soft')
                            ->where('document_type', 'material_request')
                            ->select('document_line_id')))))
            ->orderBy('required_date')
            ->limit(10)
            ->get();
    }
}
