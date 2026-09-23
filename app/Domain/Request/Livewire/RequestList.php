<?php

declare(strict_types=1);

namespace App\Domain\Request\Livewire;

use App\Domain\Master\Models\CompanySetting;
use App\Domain\Master\Models\Project;
use App\Domain\Request\Enums\MaterialRequestStatus;
use App\Domain\Request\Models\MaterialRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 14-request §6 — daftar REQ internal.
 *
 * Penanda SLA tinjau (BR-REQ-14) ada di daftar, bukan hanya di laporan: yang
 * perlu bertindak adalah staf yang membuka layar ini tiap pagi.
 */
class RequestList extends Component
{
    use WithPagination;

    /** Ambang hari sebelum peninjauan dianggap terlambat (BR-REQ-14). */
    public const SLA = 'review_sla_days';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $projectFilter = '';

    #[Url(except: '')]
    public string $requesterTypeFilter = '';

    #[Url(except: false)]
    public bool $hanyaTerbuka = true;

    #[Url(except: false)]
    public bool $hanyaTerlambat = false;

    public function mount(): void
    {
        $this->authorize('viewAny', MaterialRequest::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('livewire.request.request-list', [
            'requests' => $this->daftar(),
            'sla' => $this->slaHari(),
            'statuses' => MaterialRequestStatus::options(),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function slaHari(): int
    {
        $nilai = (int) CompanySetting::get(self::SLA, 1);

        return $nilai > 0 ? $nilai : 1;
    }

    private function daftar(): LengthAwarePaginator
    {
        $sla = $this->slaHari();

        return MaterialRequest::query()
            ->with('project:id,code,name', 'requester:id,name')
            ->withCount(['lines as open_lines_count' => fn (Builder $q) => $q->open()])
            ->when($this->search !== '', fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w
                    ->where('number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('project', fn (Builder $p) => $p
                        ->where('code', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%'))))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', (int) $this->projectFilter))
            ->when($this->requesterTypeFilter !== '', fn (Builder $q) => $q
                ->where('requester_type', $this->requesterTypeFilter))
            ->when($this->hanyaTerbuka, fn (Builder $q) => $q->open())
            ->when($this->hanyaTerlambat, fn (Builder $q) => $q->reviewOverdue($sla))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
