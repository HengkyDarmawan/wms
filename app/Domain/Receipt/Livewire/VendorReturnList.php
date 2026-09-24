<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Livewire;

use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Models\VendorReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 19-receipt-putaway §6.6 — daftar retur ke vendor. */
class VendorReturnList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', VendorReturn::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.receipt.vendor-return-list', [
            'returns' => $this->daftar(),
            'statuses' => VendorReturnStatus::options(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return VendorReturn::query()
            ->with('warehouse:id,code', 'vendor:id,name', 'receipt:id,number')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
