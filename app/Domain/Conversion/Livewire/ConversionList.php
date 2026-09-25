<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Master\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 24-konversi-waste §6 — daftar konversi material (CNV biasa & pembalik), dalam cakupan pengguna. */
class ConversionList extends Component
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
        $this->authorize('viewAny', Conversion::class);
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

        return view('livewire.conversion.conversion-list', [
            'conversions' => $this->daftar(),
            'statuses' => ConversionStatus::options(),
            'types' => ConversionType::options(),
            'projects' => Project::query()
                ->when($proyek !== null, fn (Builder $q) => $q->whereIn('id', $proyek))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return Conversion::query()
            ->with('project:id,code,name', 'warehouse:id,code', 'preparer:id,name', 'reversalOf:id,number')
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('project_id', (int) $this->projectFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }
}
