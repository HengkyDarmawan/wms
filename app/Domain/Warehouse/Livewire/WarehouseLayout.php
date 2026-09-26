<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Warehouse\Actions\MarkBinsOccupied;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\WarehouseLayoutData;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Denah gudang 2D (A-254, A-255): zona berisi rak berwarna menurut status
 * atau umur isi, klik rak → level → bin → isi (tanggal masuk, umur, tertua),
 * cari bin/item/lot/serial/potongan, dan — bagi pemegang `bin.manage` — atur
 * ukuran & posisi (geser di grid), rak area alat berat, serta bin ikut
 * terpakai. Tanpa library tambahan: SVG + Alpine (D-05, tanpa CDN).
 */
class WarehouseLayout extends Component
{
    use HandlesWarehouseRules;

    #[Locked]
    public int $warehouseId;

    /** 'status' | 'umur' */
    #[Url(as: 'warna')]
    public string $mode = 'status';

    #[Url(as: 'q')]
    public string $cari = '';

    public ?int $rakId = null;

    public bool $edit = false;

    /** @var array<string, string> */
    public array $formRak = [];

    /** @var array<int|string, array<string, string>> zone_id => length_m, width_m */
    public array $formZona = [];

    /** @var array<string, string> zone_id, code, name, capacity_qty, seluruh_zona, length_m, width_m */
    public array $formArea = ['zone_id' => '', 'code' => '', 'name' => '', 'capacity_qty' => '1', 'seluruh_zona' => '0', 'length_m' => '', 'width_m' => ''];

    /** @var array{utama: string, bins: array<int, string>, alasan: string} */
    public array $tandai = ['utama' => '', 'bins' => [], 'alasan' => ''];

    public function mount(Warehouse $warehouse): void
    {
        $this->authorize('view', $warehouse);
        $this->warehouseId = (int) $warehouse->id;

        foreach (Zone::query()->where('warehouse_id', $warehouse->id)->get() as $z) {
            $this->formZona[$z->id] = ['length_m' => $this->angka($z->length_m), 'width_m' => $this->angka($z->width_m)];
        }
    }

    public function pilihRak(int $id): void
    {
        $rak = $this->rak($id);
        $this->rakId = (int) $rak->id;
        $this->formRak = [
            'name' => (string) $rak->name,
            'length_m' => $this->angka($rak->length_m),
            'width_m' => $this->angka($rak->width_m),
            'height_m' => $this->angka($rak->height_m),
            'orientation' => (string) ($rak->orientation ?? 'h'),
            'pos_x' => $this->angka($rak->pos_x),
            'pos_y' => $this->angka($rak->pos_y),
        ];
        $this->tandai = ['utama' => '', 'bins' => [], 'alasan' => ''];
        $this->resetErrorBag();
    }

    public function tutupRak(): void
    {
        $this->rakId = null;
    }

    public function aturEdit(bool $nyala): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $this->edit = $nyala;
    }

    /** Geser rak di grid (POST Livewire; snap 0,5 m di aksi). */
    public function pindahRak(int $id, mixed $x, mixed $y, SaveWarehouseLayout $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $rak = $this->rak($id);

        $this->jalankan(fn () => $action->moveRack($rak, $x, $y, auth()->user()));

        if ($this->rakId === $id) {
            $this->pilihRak($id);
        }
    }

    public function simpanRak(SaveWarehouseLayout $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $rak = $this->rak((int) $this->rakId);

        if ($this->jalankan(fn () => $action->rack($rak, $this->formRak, auth()->user()), 'formRak')) {
            $this->dispatch('pesan', teks: __('Ukuran & posisi rak disimpan.'));
        }
    }

    public function simpanTinggiLevel(int $levelId, string $tinggi, SaveWarehouseLayout $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $level = RackLevel::query()->whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $this->warehouseId))->findOrFail($levelId);

        $this->jalankan(fn () => $action->level($level, $tinggi, auth()->user()), 'formLevel');
    }

    public function simpanZona(int $zoneId, SaveWarehouseLayout $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $zona = Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($zoneId);

        if ($this->jalankan(fn () => $action->zone($zona, $this->formZona[$zoneId] ?? [], auth()->user()), 'formZona')) {
            $this->dispatch('pesan', teks: __('Ukuran zona disimpan.'));
        }
    }

    /** Rak area alat berat: satu bin untuk seluruh rak / zona (A-255). */
    public function buatArea(SaveWarehouseLayout $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $zona = Zone::query()->where('warehouse_id', $this->warehouseId)->find((int) $this->formArea['zone_id']);

        if ($zona === null) {
            $this->addError('formArea.zone_id', __('Pilih zona.'));

            return;
        }

        $bin = null;

        if ($this->jalankan(function () use ($action, $zona, &$bin) {
            $bin = $action->areaRack($zona, $this->formArea, auth()->user());
        }, 'formArea')) {
            $this->formArea = ['zone_id' => '', 'code' => '', 'name' => '', 'capacity_qty' => '1', 'seluruh_zona' => '0', 'length_m' => '', 'width_m' => ''];
            $this->dispatch('pesan', teks: __('Rak area :b dibuat.', ['b' => $bin?->code]));
        }
    }

    /** Tandai bin tetangga ikut terpakai barang besar di bin utama (A-255). */
    public function tandaiTerpakai(MarkBinsOccupied $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $utama = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $this->warehouseId)->find((int) $this->tandai['utama']);

        if ($utama === null) {
            $this->addError('tandai.occupied_by', __('Pilih bin utama.'));

            return;
        }

        $n = 0;

        if ($this->jalankan(function () use ($action, $utama, &$n) {
            $n = $action->handle($utama, $this->tandai['bins'], $this->tandai['alasan'], auth()->user());
        }, 'tandai')) {
            $this->tandai = ['utama' => '', 'bins' => [], 'alasan' => ''];
            $this->dispatch('pesan', teks: __(':n bin ditandai ikut terpakai.', ['n' => $n]));
        }
    }

    public function lepasTerpakai(int $binId, MarkBinsOccupied $action): void
    {
        $this->authorize('manageLayout', $this->gudang());
        $bin = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $this->warehouseId)->findOrFail($binId);

        $action->release($bin, auth()->user());
    }

    public function render(WarehouseLayoutData $data): View
    {
        $gudang = $this->gudang();
        $denah = $data->build($gudang, $this->cari);
        $rak = null;

        foreach ($denah['zones'] as $z) {
            foreach ($z['racks'] as $r) {
                if ($r['id'] === $this->rakId) {
                    $rak = $r + ['zona' => $z['code']];
                }
            }
        }

        return view('livewire.warehouse.warehouse-layout', [
            'gudang' => $gudang,
            'denah' => $denah,
            'rak' => $rak,
            'bolehUbah' => auth()->user()?->can('manageLayout', $gudang) ?? false,
            'skala' => 40,
        ]);
    }

    private function gudang(): Warehouse
    {
        return Warehouse::query()->findOrFail($this->warehouseId);
    }

    private function rak(int $id): Rack
    {
        return Rack::query()->whereHas('zone', fn ($q) => $q->where('warehouse_id', $this->warehouseId))->findOrFail($id);
    }

    private function angka(mixed $n): string
    {
        return $n === null ? '' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}
