<?php

declare(strict_types=1);

namespace App\Domain\Return\Livewire;

use App\Domain\Return\Enums\GoodsReturnStatus;
use App\Domain\Return\Models\GoodsReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 22-retur-transfer §6 — daftar retur dari proyek. Dipakai back-office
 * dan portal klien (BR-RET-05); cakupan proyek/gudang lewat global scope model.
 */
class ReturnList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Locked]
    public bool $portal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', GoodsReturn::class);

        $this->portal = request()->routeIs('portal.*');
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.return.return-list', [
            'returns' => $this->daftar(),
            'statuses' => GoodsReturnStatus::options(),
            'rute' => $this->portal ? 'portal.returns' : 'returns',
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return GoodsReturn::query()
            ->with('project:id,code,name', 'fromWarehouse:id,code', 'toWarehouse:id,code', 'requester:id,name')
            ->withCount('requestedLines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
