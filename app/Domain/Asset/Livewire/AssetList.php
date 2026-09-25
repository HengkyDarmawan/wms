<?php

declare(strict_types=1);

namespace App\Domain\Asset\Livewire;

use App\Domain\Asset\Support\AssetQuery;
use App\Domain\Master\Enums\AssetState;
use App\Domain\Master\Models\Project;
use App\Domain\Master\Models\Serial;
use App\Domain\Stock\Models\StockBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 25-aset §6 — daftar aset: state, lokasi, proyek peminjam, tanggal
 * kembali (lewat jatuh tempo, BR-AST-06), grade/skor, sisa umur (BR-AST-08).
 */
class AssetList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'state', except: '')]
    public string $stateFilter = '';

    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    #[Url(as: 'perhatian', except: false)]
    public bool $attention = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Serial::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $aset = $this->daftar();
        $lokasi = StockBalance::query()->with('bin:id,code,warehouse_id', 'bin.warehouse:id,code')
            ->whereIn('serial_id', $aset->getCollection()->pluck('id'))
            ->where('qty_base', '>', 0)->get()->keyBy('serial_id');

        return view('livewire.asset.asset-list', [
            'assets' => $aset,
            'lokasi' => $lokasi,
            'states' => AssetState::options(),
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'ambang' => Serial::lifeAlertPercent(),
        ]);
    }

    private function daftar(): LengthAwarePaginator
    {
        $cari = trim($this->search);

        return app(AssetQuery::class)->for(auth()->user())
            ->with('item:id,code,name', 'currentProject:id,code,name')
            ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('serial_no', 'like', '%'.$cari.'%')
                ->orWhereHas('item', fn (Builder $i) => $i->where('code', 'like', '%'.$cari.'%')->orWhere('name', 'like', '%'.$cari.'%'))))
            ->when($this->stateFilter !== '', fn (Builder $q) => $q->where('asset_state', $this->stateFilter))
            ->when($this->projectFilter !== '', fn (Builder $q) => $q->where('current_project_id', (int) $this->projectFilter))
            ->when($this->attention, fn (Builder $q) => $q->overdue())
            ->orderBy('serial_no')
            ->paginate(25);
    }
}
