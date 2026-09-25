<?php

declare(strict_types=1);

namespace App\Domain\Asset\Livewire;

use App\Domain\Asset\Enums\AssetHandoverStatus;
use App\Domain\Asset\Models\AssetHandover;
use App\Domain\Master\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 25-aset §6 — daftar serah terima aset (AST), dalam cakupan pengguna. */
class HandoverList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    #[Url(as: 'lewat', except: false)]
    public bool $overdue = false;

    public function mount(): void
    {
        $this->authorize('viewAny', AssetHandover::class);
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

        return view('livewire.asset.handover-list', [
            'handovers' => $this->daftar(),
            'statuses' => AssetHandoverStatus::options(),
            'projects' => Project::query()
                ->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        $cari = trim($this->search);

        return AssetHandover::query()
            ->with('serial:id,serial_no', 'item:id,code,name', 'project:id,code,name', 'warehouse:id,code')
            ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('number', 'like', '%'.$cari.'%')
                ->orWhereHas('serial', fn (Builder $s) => $s->where('serial_no', 'like', '%'.$cari.'%'))))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', (int) $this->projectFilter))
            ->when($this->overdue, fn (Builder $q) => $q->where('status', AssetHandoverStatus::CheckedOut->value)
                ->whereNull('lost_at')->whereNotNull('due_return_date')->whereDate('due_return_date', '<', now()->toDateString()))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
