<?php

declare(strict_types=1);

namespace App\Domain\Conversion\Livewire;

use App\Domain\Conversion\Enums\ConversionStatus;
use App\Domain\Conversion\Enums\ConversionType;
use App\Domain\Conversion\Models\Conversion;
use App\Domain\Shared\Livewire\Concerns\CariPilihan;
use App\Domain\Shared\Pilihan\Pilihan;
use App\Domain\Shared\Pilihan\SumberPilihan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Layar 24-konversi-waste §6 — daftar konversi material (CNV biasa & pembalik), dalam cakupan pengguna. */
class ConversionList extends Component
{
    use CariPilihan;
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
        return view('livewire.conversion.conversion-list', [
            'conversions' => $this->daftar(),
            'statuses' => ConversionStatus::options(),
            'types' => ConversionType::options(),
            'opsiProyek' => $this->pilihanProyek()->awalDengan($this->projectFilter),
        ]);
    }

    /** Saringan proyek: daftar lama, dicari ke server (A-395). */
    private function pilihanProyek(): Pilihan
    {
        return SumberPilihan::proyekIdCakupan();
    }

    protected function pilihanServer(string $model): ?Pilihan
    {
        return $model === 'projectFilter' && auth()->user()?->can('viewAny', Conversion::class) ? $this->pilihanProyek() : null;
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
