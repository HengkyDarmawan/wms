<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Layar 12-warehouse §6 — detail gudang: zona, rak, level, bin, dan riwayat.
 *
 * Pembuat bin massal ada di sini karena gudang baru bisa punya ratusan bin dan
 * mengetiknya satu per satu adalah cara tercepat menghasilkan kode yang tidak
 * konsisten (BR-WH-01).
 */
class WarehouseDetail extends Component
{
    use HandlesWarehouseRules;

    #[Locked]
    public Warehouse $warehouse;

    public string $tab = 'lokasi';

    /** @var array<string, string> */
    public array $form = [
        'zone_code' => '',
        'zone_name' => '',
        'rack_code' => '',
        'level_code' => '',
    ];

    #[Locked]
    public ?int $zoneId = null;

    #[Locked]
    public ?int $rackId = null;

    #[Locked]
    public ?int $generatingLevelId = null;

    /** @var array<string, mixed> */
    public array $generator = [
        'jumlah' => 5,
        'prefix' => 'B',
        'storage_category_id' => '',
        'capacity_qty' => '',
    ];

    public function mount(Warehouse $warehouse): void
    {
        $this->authorize('view', $warehouse);

        $this->warehouse = $warehouse;
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['lokasi', 'bin', 'riwayat'], true) ? $tab : 'lokasi';
    }

    public function tambahZona(SaveLocation $action): void
    {
        $this->authorize('create', Bin::class);

        $this->validate([
            'form.zone_code' => ['required', 'string', 'max:10'],
            'form.zone_name' => ['required', 'string', 'max:60'],
        ], attributes: [
            'form.zone_code' => __('Kode zona'),
            'form.zone_name' => __('Nama zona'),
        ]);

        $berhasil = $this->jalankan(fn () => $action->saveZone($this->warehouse, null, [
            'code' => $this->form['zone_code'],
            'name' => $this->form['zone_name'],
        ], auth()->user()));

        if (! $berhasil) {
            return;
        }

        $this->form['zone_code'] = '';
        $this->form['zone_name'] = '';
        $this->dispatch('pesan', teks: __('Zona ditambahkan.'));
    }

    public function tambahRak(int $zoneId, SaveLocation $action): void
    {
        $this->authorize('create', Bin::class);

        $zona = Zone::where('warehouse_id', $this->warehouse->id)->findOrFail($zoneId);

        $this->validate(
            ['form.rack_code' => ['required', 'string', 'max:10']],
            attributes: ['form.rack_code' => __('Kode rak')],
        );

        $berhasil = $this->jalankan(
            fn () => $action->saveRack($zona, null, ['code' => $this->form['rack_code']], auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->form['rack_code'] = '';
        $this->dispatch('pesan', teks: __('Rak ditambahkan.'));
    }

    public function tambahLevel(int $rackId, SaveLocation $action): void
    {
        $this->authorize('create', Bin::class);

        $rak = $this->rakMilikGudang($rackId);

        $this->validate(
            ['form.level_code' => ['required', 'string', 'max:10']],
            attributes: ['form.level_code' => __('Kode level')],
        );

        $berhasil = $this->jalankan(
            fn () => $action->saveLevel($rak, null, ['code' => $this->form['level_code']], auth()->user()),
        );

        if (! $berhasil) {
            return;
        }

        $this->form['level_code'] = '';
        $this->dispatch('pesan', teks: __('Level rak ditambahkan.'));
    }

    public function mintaBuatBin(int $levelId): void
    {
        $this->authorize('create', Bin::class);

        $this->ruleError = '';
        $this->generatingLevelId = $levelId;
    }

    public function buatBinMassal(GenerateBins $action): void
    {
        $this->authorize('create', Bin::class);

        $level = $this->levelMilikGudang((int) $this->generatingLevelId);

        $this->validate([
            'generator.jumlah' => ['required', 'integer', 'min:1', 'max:200'],
            'generator.prefix' => ['nullable', 'string', 'max:5'],
            'generator.capacity_qty' => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'generator.jumlah' => __('Jumlah bin'),
            'generator.prefix' => __('Awalan kode'),
        ]);

        $dibuat = [];

        $berhasil = $this->jalankan(function () use ($action, $level, &$dibuat): void {
            $dibuat = $action->handle($level, (int) $this->generator['jumlah'], $this->generator, auth()->user());
        }, 'generator');

        if (! $berhasil) {
            return;
        }

        $this->generatingLevelId = null;
        $this->dispatch('pesan', teks: count($dibuat).' '.__('bin dibuat.'));
    }

    public function batalBuatBin(): void
    {
        $this->generatingLevelId = null;
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.warehouse.warehouse-detail', [
            'zones' => Zone::query()
                ->where('warehouse_id', $this->warehouse->id)
                ->with(['racks.levels' => fn ($q) => $q->withCount('bins')])
                ->orderBy('code')
                ->get(),
            'bins' => $this->tab === 'bin'
                ? Bin::query()
                    ->where('warehouse_id', $this->warehouse->id)
                    ->with('storageCategory:id,name', 'project:id,code')
                    ->orderBy('code')
                    ->paginate(25, pageName: 'bin')
                : null,
            'riwayat' => $this->tab === 'riwayat' ? $this->riwayat() : null,
            'storageCategories' => StorageCategory::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function rakMilikGudang(int $rackId): Rack
    {
        return Rack::query()
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $this->warehouse->id))
            ->findOrFail($rackId);
    }

    private function levelMilikGudang(int $levelId): RackLevel
    {
        return RackLevel::query()
            ->whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $this->warehouse->id))
            ->findOrFail($levelId);
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
