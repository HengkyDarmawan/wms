<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Livewire;

use App\Domain\Adjustment\Enums\AdjustmentOrigin;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Models\StockAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 21-opname-penyesuaian §6.6 — daftar penyesuaian stok (manual & hasil opname). */
class AdjustmentList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $originFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockAdjustment::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.adjustment.adjustment-list', [
            'adjustments' => $this->daftar(),
            'statuses' => StockAdjustmentStatus::options(),
            'origins' => AdjustmentOrigin::options(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return StockAdjustment::query()
            ->with('warehouse:id,code', 'reason:id,label', 'submitter:id,name', 'stockCount:id,number')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->originFilter !== '', fn (Builder $q) => $q->where('origin', $this->originFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
