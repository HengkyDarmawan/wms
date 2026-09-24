<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Models\PutawayTask;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 19-receipt-putaway §6.4 — daftar tugas put-away. */
class PutawayList extends Component
{
    use WithPagination;

    #[Url(except: 'pending')]
    public string $statusFilter = 'pending';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PutawayTask::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.receipt.putaway-list', [
            'tasks' => $this->daftar(),
            'statuses' => PutawayTaskStatus::options(),
            'warehouses' => Warehouse::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return PutawayTask::query()
            ->with('warehouse:id,code', 'receipt:id,number')
            ->withCount('lines')
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
