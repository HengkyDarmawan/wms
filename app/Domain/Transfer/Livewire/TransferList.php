<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Livewire;

use App\Domain\Transfer\Enums\TransferOrigin;
use App\Domain\Transfer\Enums\TransferStatus;
use App\Domain\Transfer\Models\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 22-retur-transfer §6 — daftar transfer (antar gudang, antar proyek, dalam proyek). */
class TransferList extends Component
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
        $this->authorize('viewAny', Transfer::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.transfer.transfer-list', [
            'transfers' => $this->daftar(),
            'statuses' => TransferStatus::options(),
            'origins' => TransferOrigin::options(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return Transfer::query()
            ->with('fromWarehouse:id,code', 'toWarehouse:id,code', 'submitter:id,name')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->originFilter !== '', fn (Builder $q) => $q->where('origin', $this->originFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
