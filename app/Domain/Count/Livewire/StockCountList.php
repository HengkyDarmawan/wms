<?php

declare(strict_types=1);

namespace App\Domain\Count\Livewire;

use App\Domain\Count\Enums\CountType;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Models\CountLine;
use App\Domain\Count\Models\StockCount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 21-opname-penyesuaian §6.1 — daftar sesi opname dengan ringkasan
 * akurasi (dashboard konsolidasi D-23 versi Fase 1).
 */
class StockCountList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StockCount::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $sesi = $this->daftar();

        return view('livewire.count.count-list', [
            'counts' => $sesi,
            'akurasi' => $this->akurasi($sesi->getCollection()->pluck('id')->all()),
            'statuses' => StockCountStatus::options(),
            'types' => CountType::options(),
            'ringkasan' => [
                'berjalan' => StockCount::query()->whereIn('status', [StockCountStatus::InProgress->value, StockCountStatus::Recount->value])->count(),
                'rekonsiliasi' => StockCount::query()->where('status', StockCountStatus::Reconciling->value)->count(),
                'ditutup' => StockCount::query()->where('status', StockCountStatus::Closed->value)->count(),
            ],
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        return StockCount::query()
            ->with('warehouses:id,code', 'creator:id,name')
            ->withCount(['assignments' => fn (Builder $q) => $q->where('round', 1)])
            ->when($this->search !== '', fn (Builder $q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('count_type', $this->typeFilter))
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * Akurasi = baris tanpa selisih ÷ baris terhitung; hanya setelah sesi
     * masuk rekonsiliasi supaya hitung buta tidak bocor (A-103).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{akurasi: ?float, besar: int}>
     */
    private function akurasi(array $ids): array
    {
        $hasil = [];

        $sesi = StockCount::query()->whereIn('id', $ids)
            ->whereIn('status', [StockCountStatus::Reconciling->value, StockCountStatus::Approved->value, StockCountStatus::Closed->value])
            ->pluck('id')->all();

        foreach ($sesi as $id) {
            $baris = CountLine::query()->where('stock_count_id', $id)->whereNotNull('final_qty');
            $total = (clone $baris)->count();
            $cocok = (clone $baris)->whereNull('variance_class')->count();

            $hasil[$id] = [
                'akurasi' => $total > 0 ? round($cocok / $total * 100, 1) : null,
                'besar' => (clone $baris)->where('variance_class', 'major')->count(),
            ];
        }

        return $hasil;
    }
}
