<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\MarkBinsOccupied;
use App\Domain\Warehouse\Actions\SaveLocation;
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
 * terpakai. Sejak A-271 juga menambah zona, rak (+ level + bin), level,
 * dan bin langsung dari denah. Tanpa library tambahan: SVG + Alpine (D-05,
 * tanpa CDN).
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

    /** @var array<int|string, array<string, string>> zone_id => name, length_m, width_m */
    public array $formZona = [];

    /** @var array<string, string> A-271: zona baru dari denah */
    public array $formZonaBaru = ['code' => '', 'name' => ''];

    /** @var array<string, string> A-271: rak baru (+ level L1…Ln + bin per level) */
    public array $formRakBaru = ['zone_id' => '', 'code' => '', 'name' => '', 'levels' => '1', 'bins_per_level' => '0', 'prefix' => 'B', 'capacity_qty' => ''];

    /** @var array<string, string> A-271: level baru di rak terpilih */
    public array $formLevelBaru = ['code' => '', 'bins' => '0'];

    /** @var array<int|string, string> A-271: level_id => jumlah bin baru */
    public array $formBinBaru = [];

    /** @var array<string, string> zone_id, code, name, capacity_qty, seluruh_zona, length_m, width_m */
    public array $formArea = ['zone_id' => '', 'code' => '', 'name' => '', 'capacity_qty' => '1', 'seluruh_zona' => '0', 'length_m' => '', 'width_m' => ''];

    /** @var array{utama: string, bins: array<int, string>, alasan: string} */
    public array $tandai = ['utama' => '', 'bins' => [], 'alasan' => ''];

    public function mount(Warehouse $warehouse): void
    {
        $this->authorize('view', $warehouse);
        $this->warehouseId = (int) $warehouse->id;

        foreach (Zone::query()->where('warehouse_id', $warehouse->id)->get() as $z) {
            $this->isiFormZona($z);
        }
    }

    /** A-271: zona baru langsung dari denah (kode terkunci setelah dibuat, BR-WH-01). */
    public function tambahZona(SaveLocation $action): void
    {
        $this->resetErrorBag();
        $gudang = $this->gudang();
        $this->authorize('manageLayout', $gudang);
        $zona = null;

        if ($this->jalankan(function () use ($action, $gudang, &$zona) {
            $zona = $action->saveZone($gudang, null, $this->formZonaBaru, auth()->user());
        }, 'formZonaBaru')) {
            $this->isiFormZona($zona);
            $this->formZonaBaru = ['code' => '', 'name' => ''];
            $this->formRakBaru['zone_id'] = (string) $zona->id;
            $this->dispatch('pesan', teks: __('Zona :z ditambahkan.', ['z' => $zona->code]));
        }
    }

    /** A-271: rak baru + level L1…Ln + bin opsional per level, ditata otomatis. */
    public function tambahRak(SaveWarehouseLayout $action): void
    {
        $this->resetErrorBag();
        $this->authorize('manageLayout', $this->gudang());
        $zona = Zone::query()->where('warehouse_id', $this->warehouseId)->find((int) $this->formRakBaru['zone_id']);

        if ($zona === null) {
            $this->addError('formRakBaru.zone_id', __('Pilih zona.'));

            return;
        }

        $rak = null;

        if ($this->jalankan(function () use ($action, $zona, &$rak) {
            $rak = $action->newRack($zona, $this->formRakBaru, auth()->user());
        }, 'formRakBaru')) {
            $this->formRakBaru = ['zone_id' => (string) $zona->id, 'code' => '', 'name' => '', 'levels' => '1', 'bins_per_level' => '0', 'prefix' => 'B', 'capacity_qty' => ''];
            $this->pilihRak((int) $rak->id);
            $this->dispatch('pesan', teks: __('Rak :r ditambahkan.', ['r' => $zona->code.'-'.$rak->code]));
        }
    }

    /** A-271: level baru di rak terpilih; kode kosong = L berikutnya. */
    public function tambahLevel(SaveWarehouseLayout $action): void
    {
        $this->resetErrorBag();
        $this->authorize('manageLayout', $this->gudang());
        $rak = $this->rak((int) $this->rakId);
        $level = null;

        if ($this->jalankan(function () use ($action, $rak, &$level) {
            $level = $action->newLevel($rak, $this->formLevelBaru, auth()->user());
        }, 'formLevelBaru')) {
            $this->formLevelBaru = ['code' => '', 'bins' => '0'];
            $this->dispatch('pesan', teks: __('Level :l ditambahkan.', ['l' => $level->code]));
        }
    }

    /** A-271: tambah bin di satu level (nomor berikutnya, BR-WH-01). */
    public function tambahBin(int $levelId, GenerateBins $action): void
    {
        $this->resetErrorBag();
        $this->authorize('manageLayout', $this->gudang());
        $level = RackLevel::query()->whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $this->warehouseId))->findOrFail($levelId);
        $jumlah = trim((string) ($this->formBinBaru[$levelId] ?? '1'));

        if (! ctype_digit($jumlah) || (int) $jumlah < 1 || (int) $jumlah > SaveWarehouseLayout::MAKS_BIN_PER_LEVEL) {
            $this->addError('formBinBaru.'.$levelId, __('Jumlah bin 1–:n.', ['n' => SaveWarehouseLayout::MAKS_BIN_PER_LEVEL]));

            return;
        }

        if ($level->rack?->is_area) {
            $this->ruleError = __('Rak area hanya punya satu bin (A-255).');

            return;
        }

        $dibuat = [];

        if ($this->jalankan(function () use ($action, $level, $jumlah, &$dibuat) {
            $dibuat = $action->handle($level, (int) $jumlah, ['prefix' => 'B'], auth()->user());
        }, 'formBinBaru')) {
            unset($this->formBinBaru[$levelId]);
            $this->dispatch('pesan', teks: __(':n bin ditambahkan.', ['n' => count($dibuat)]));
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
        $this->resetErrorBag();
        $this->authorize('manageLayout', $this->gudang());
        $zona = Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($zoneId);

        if ($this->jalankan(fn () => $action->zone($zona, $this->formZona[$zoneId] ?? [], auth()->user()), 'formZona.'.$zoneId)) {
            $this->dispatch('pesan', teks: __('Zona disimpan.'));
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
            'skala' => WarehouseLayoutData::SKALA,
        ]);
    }

    private function isiFormZona(Zone $z): void
    {
        $this->formZona[$z->id] = ['name' => (string) $z->name, 'length_m' => $this->angka($z->length_m), 'width_m' => $this->angka($z->width_m)];
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
