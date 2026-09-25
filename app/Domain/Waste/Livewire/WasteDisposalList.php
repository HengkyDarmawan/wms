<?php

declare(strict_types=1);

namespace App\Domain\Waste\Livewire;

use App\Domain\Master\Models\Project;
use App\Domain\Waste\Enums\WasteDisposalStatus;
use App\Domain\Waste\Enums\WasteDisposition;
use App\Domain\Waste\Models\WasteDisposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 24-konversi-waste §6 — daftar Berita Acara Waste, dalam cakupan pengguna. */
class WasteDisposalList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WasteDisposal::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $proyek = auth()->user()?->accessibleProjectIds();

        return view('livewire.waste.waste-disposal-list', [
            'disposals' => $this->daftar(),
            'statuses' => WasteDisposalStatus::options(),
            'dispositions' => WasteDisposition::options(),
            'projects' => Project::query()
                ->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return WasteDisposal::query()
            ->with('project:id,code,name', 'warehouse:id,code', 'submitter:id,name')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', (int) $this->projectFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
