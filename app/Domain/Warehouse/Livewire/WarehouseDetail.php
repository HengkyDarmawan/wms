<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 12-warehouse §6 — detail gudang: tab Zona & rak, Bin, dan Riwayat.
 *
 * Tab Zona & rak memakai komponen {@see WarehouseLocations} (A-410): popup tambah
 * zona/rak/tingkat/bin, urutan, ubah, hapus/nonaktif, gabung & pisah bin.
 */
class WarehouseDetail extends Component
{
    use HandlesWarehouseRules;
    use WithPagination;

    public const TABS = ['lokasi', 'bin', 'riwayat'];

    #[Locked]
    public Warehouse $warehouse;

    #[Url(except: 'lokasi')]
    public string $tab = 'lokasi';

    public function mount(Warehouse $warehouse): void
    {
        $this->authorize('view', $warehouse);

        $this->warehouse = $warehouse;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'lokasi';
        }
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'lokasi';
    }

    public function render(): View
    {
        return view('livewire.warehouse.warehouse-detail', [
            'bins' => $this->tab === 'bin'
                ? Bin::query()
                    ->where('warehouse_id', $this->warehouse->id)
                    ->with('storageCategory:id,name', 'project:id,code')
                    ->orderBy('code')
                    ->paginate(25, pageName: 'bin')
                : null,
            'riwayat' => $this->tab === 'riwayat' ? $this->riwayat() : null,
        ]);
    }

    /** BR-GEN-05: jejak perubahan gudang ini. */
    private function riwayat(): mixed
    {
        return Activity::query()
            ->where('subject_type', Warehouse::class)
            ->where('subject_id', $this->warehouse->id)
            ->with('causer')
            ->latest('id')
            ->paginate(15, pageName: 'riwayat');
    }
}
