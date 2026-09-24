<?php

declare(strict_types=1);

namespace App\Domain\Issue\Livewire;

use App\Domain\Issue\Enums\MaterialIssueStatus;
use App\Domain\Issue\Models\MaterialIssue;
use App\Domain\Master\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 23-pemakaian §6 — daftar pemakaian material (ISU biasa & pembalik), dalam cakupan pengguna. */
class IssueList extends Component
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
        $this->authorize('viewAny', MaterialIssue::class);
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

        return view('livewire.issue.issue-list', [
            'issues' => $this->daftar(),
            'statuses' => MaterialIssueStatus::options(),
            'projects' => Project::query()
                ->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return MaterialIssue::query()
            ->with('project:id,code,name', 'warehouse:id,code', 'issuer:id,name', 'reversalOf:id,number')
            ->withCount('lines')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', (int) $this->projectFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
