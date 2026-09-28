<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Stock\Models\StockBalance;
use App\Domain\Warehouse\Actions\DeactivateLocation;
use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\MarkBinsOccupied;
use App\Domain\Warehouse\Actions\SaveFloorPlanObject;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\FloorPlanObjectType;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\FloorPlanObject;
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
 * dan bin langsung dari denah. Sejak A-320: satu kanvas gedung (ukuran
 * gedung, zona digeser & diubah ukurannya di dalam gedung, objek denah tanpa
 * stok, zoom, geser halus tombol panah, putar 90°, peringatan tumpukan) dan
 * panel rak berdesain ulang (tampak depan, isi per bin, tab Isi | Atur).
 * `ringkas` = sematan hanya-lihat di Daftar Gudang (A-323). Tanpa library
 * tambahan: SVG + Alpine `denahGedung` (D-05, tanpa CDN).
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

    /** A-323: sematan hanya-lihat (Daftar Gudang mode Denah). */
    #[Locked]
    public bool $ringkas = false;

    /** Benda terpilih di kanvas: `rak:ID`, `zona:ID`, `obj:ID`, atau ''. */
    public string $terpilih = '';

    /** Bin yang dibuka di panel rak (klik petak tampak depan). */
    public ?int $binId = null;

    /** Tab panel rak: `isi` | `atur` (atur hanya di mode Atur denah). */
    public string $tabRak = 'isi';

    public bool $formGedungBuka = false;

    /** @var array<string, string> */
    public array $formGedung = ['length_m' => '', 'width_m' => ''];

    /** @var array<string, string> */
    public array $formObjek = [];

    public string $alasanNonaktif = '';

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

    public function mount(Warehouse $warehouse, bool $ringkas = false): void
    {
        $this->authorize('view', $warehouse);
        $this->warehouseId = (int) $warehouse->id;
        $this->ringkas = $ringkas;
        $this->formGedung = ['length_m' => $this->angka($warehouse->length_m), 'width_m' => $this->angka($warehouse->width_m)];

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

    /** Klik benda di kanvas (A-320). Di luar mode Atur hanya rak yang dibuka. */
    public function pilih(string $jenis, int $id): void
    {
        match ($jenis) {
            'rak' => $this->pilihRak($id),
            'zona' => $this->pilihZona($id),
            'obj' => $this->pilihObjek($id),
            default => null,
        };
    }

    public function pilihZona(int $id): void
    {
        $zona = Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id);
        $this->rakId = null;
        $this->binId = null;
        $this->terpilih = 'zona:'.$zona->id;
        $this->isiFormZona($zona);
        $this->alasanNonaktif = '';
        $this->resetErrorBag();
    }

    public function pilihObjek(int $id): void
    {
        $objek = $this->objek($id);
        $this->rakId = null;
        $this->binId = null;
        $this->terpilih = 'obj:'.$objek->id;
        $this->isiFormObjek($objek);
        $this->resetErrorBag();
    }

    public function pilihBin(int $id): void
    {
        $bin = Bin::query()->withoutGlobalScopes()->where('warehouse_id', $this->warehouseId)->findOrFail($id);
        $this->binId = (int) $bin->id;
    }

    public function pilihTab(string $tab): void
    {
        $this->tabRak = $tab === 'atur' && $this->edit ? 'atur' : 'isi';
    }

    public function pilihRak(int $id): void
    {
        $rak = $this->rak($id);
        $this->rakId = (int) $rak->id;
        $this->terpilih = 'rak:'.$rak->id;
        $this->alasanNonaktif = '';
        $this->binId = $this->binAwal($rak);
        $this->isiFormRak($rak);
        $this->tandai = ['utama' => '', 'bins' => [], 'alasan' => ''];
        $this->resetErrorBag();
    }

    private function isiFormRak(Rack $rak): void
    {
        $this->formRak = [
            'name' => (string) $rak->name,
            'length_m' => $this->angka($rak->length_m),
            'width_m' => $this->angka($rak->width_m),
            'height_m' => $this->angka($rak->height_m),
            'orientation' => (string) ($rak->orientation ?? 'h'),
            'pos_x' => $this->angka($rak->pos_x),
            'pos_y' => $this->angka($rak->pos_y),
        ];
    }

    public function tutupRak(): void
    {
        $this->rakId = null;
        $this->binId = null;
        $this->terpilih = '';
    }

    public function aturEdit(bool $nyala): void
    {
        abort_if($this->ringkas, 403);
        $this->authorize('manageLayout', $this->gudang());
        $this->edit = $nyala;

        if (! $nyala) {
            $this->tabRak = 'isi';
            $this->formGedungBuka = false;
        }
    }

    /** Geser rak di grid (POST Livewire; snap 0,5 m di aksi). */
    public function pindahRak(int $id, mixed $x, mixed $y, SaveWarehouseLayout $action): void
    {
        $this->geser('rak', $id, $x, $y, $action, app(SaveFloorPlanObject::class));
    }

    /** A-320: seret zona/rak/objek; zona & objek dalam koordinat gedung, rak dalam koordinat zonanya. */
    public function geser(string $jenis, int $id, mixed $x, mixed $y, SaveWarehouseLayout $action, SaveFloorPlanObject $objek): void
    {
        $this->geserGrid($jenis, $id, $x, $y, $action, $objek, SaveWarehouseLayout::GRID);
    }

    private function geserGrid(string $jenis, int $id, mixed $x, mixed $y, SaveWarehouseLayout $action, SaveFloorPlanObject $objek, float $grid): void
    {
        $this->bolehAtur();

        $this->jalankan(fn () => match ($jenis) {
            'rak' => $action->moveRack($this->rak($id), $x, $y, auth()->user(), $grid),
            'zona' => $action->moveZone(Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id), $x, $y, auth()->user(), $grid),
            'obj' => $objek->move($this->objek($id), $x, $y, auth()->user(), $grid),
            default => null,
        });

        $this->segarkanPilihan($jenis, $id);
    }

    /** A-320: tarik sudut kanan-bawah; ukuran tampak atas dalam meter. */
    public function ubahUkuran(string $jenis, int $id, mixed $p, mixed $l, SaveWarehouseLayout $action, SaveFloorPlanObject $objek): void
    {
        $this->bolehAtur();

        $this->jalankan(fn () => match ($jenis) {
            'rak' => $action->resizeRack($this->rak($id), $p, $l, auth()->user()),
            'zona' => $action->resizeZone(Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id), $p, $l, auth()->user()),
            'obj' => $objek->resize($this->objek($id), $p, $l, auth()->user()),
            default => null,
        });

        $this->segarkanPilihan($jenis, $id);
    }

    /** A-320: tombol panah — 0,5 m, atau 0,1 m dengan Shift. */
    public function geserHalus(mixed $dx, mixed $dy, SaveWarehouseLayout $action, SaveFloorPlanObject $objek): void
    {
        [$jenis, $id] = $this->pilihan();

        if ($jenis === null) {
            return;
        }

        // Langkah panah 0,5 m (Shift 0,1 m); penjepretan 0,1 m supaya geser halus sebelumnya tidak hilang.
        $grid = SaveWarehouseLayout::GRID_HALUS;
        $dx = max(-SaveWarehouseLayout::GRID, min(SaveWarehouseLayout::GRID, (float) $dx));
        $dy = max(-SaveWarehouseLayout::GRID, min(SaveWarehouseLayout::GRID, (float) $dy));
        [$x, $y] = match ($jenis) {
            'rak' => [$this->rak($id)->pos_x, $this->rak($id)->pos_y],
            'zona' => (fn ($z) => [$z->pos_x, $z->pos_y])(Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id)),
            default => (fn ($o) => [$o->pos_x, $o->pos_y])($this->objek($id)),
        };

        // Benda yang masih ditata otomatis mulai dari posisi gambarnya.
        if ($x === null || $y === null) {
            [$x, $y] = $this->posisiGambar($jenis, $id);
        }

        $this->geserGrid($jenis, $id, (float) $x + (float) $dx, (float) $y + (float) $dy, $action, $objek, $grid);
    }

    /** A-322: putar rak (arah h ↔ v) atau objek 90°. */
    public function putar(SaveWarehouseLayout $action, SaveFloorPlanObject $objek): void
    {
        [$jenis, $id] = $this->pilihan();

        if ($jenis === 'rak' || $jenis === 'obj') {
            $this->bolehAtur();
            $this->jalankan(fn () => $jenis === 'rak'
                ? $action->rotateRack($this->rak($id), auth()->user())
                : $objek->rotate($this->objek($id), auth()->user()));
            $this->segarkanPilihan($jenis, $id);
        }
    }

    /** A-320: ukuran gedung (garis luar denah). */
    public function simpanGedung(SaveWarehouseLayout $action): void
    {
        $this->resetErrorBag();
        $this->bolehAtur();

        if ($this->jalankan(fn () => $action->building($this->gudang(), $this->formGedung, auth()->user()), 'formGedung')) {
            $this->formGedungBuka = false;
            $this->dispatch('pesan', teks: __('Ukuran gedung disimpan.'));
        }
    }

    /** A-320: tambah objek denah (tanpa stok) dengan ukuran bawaan jenisnya. */
    public function tambahObjek(string $jenis, SaveFloorPlanObject $action): void
    {
        $this->bolehAtur();
        $objek = null;

        if ($this->jalankan(function () use ($action, $jenis, &$objek) {
            $objek = $action->create($this->gudang(), $jenis, [], auth()->user());
        })) {
            $this->pilihObjek((int) $objek->id);
            $this->dispatch('pesan', teks: __(':o ditambahkan — seret ke tempatnya.', ['o' => $objek->name]));
        }
    }

    public function simpanObjek(SaveFloorPlanObject $action): void
    {
        $this->resetErrorBag();
        $this->bolehAtur();
        [$jenis, $id] = $this->pilihan();

        if ($jenis === 'obj' && $this->jalankan(fn () => $action->update($this->objek($id), $this->formObjek, auth()->user()), 'formObjek')) {
            $this->pilihObjek($id);
            $this->dispatch('pesan', teks: __('Objek denah disimpan.'));
        }
    }

    /** P-03: objek, rak, dan zona dinonaktifkan — tidak dihapus. */
    public function nonaktifkan(SaveFloorPlanObject $objek, DeactivateLocation $lokasi): void
    {
        $this->resetErrorBag();
        $this->bolehAtur();
        [$jenis, $id] = $this->pilihan();

        $ok = $this->jalankan(fn () => match ($jenis) {
            'obj' => $objek->deactivate($this->objek($id), auth()->user()),
            'rak' => $lokasi->rack($this->rak($id), $this->alasanNonaktif, auth()->user()),
            'zona' => $lokasi->zone(Zone::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id), $this->alasanNonaktif, auth()->user()),
            default => null,
        }, 'nonaktif');

        if ($ok && $jenis !== null) {
            $this->tutupRak();
            $this->dispatch('pesan', teks: __('Dinonaktifkan dari denah.'));
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
            $this->isiFormZona($zona->refresh());
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
        $zonaPilih = null;
        $objekPilih = null;
        [$jenis, $id] = $this->pilihan();

        foreach ($denah['zones'] as $z) {
            if ($jenis === 'zona' && $z['id'] === $id) {
                $zonaPilih = $z;
            }

            foreach ($z['racks'] as $r) {
                if ($r['id'] === $this->rakId) {
                    $rak = $r + ['zona' => $z['code'], 'zona_nama' => $z['name'], 'zona_id' => $z['id']];
                }
            }
        }

        if ($jenis === 'obj') {
            $objekPilih = collect($denah['objects'])->firstWhere('id', $id);
        }

        $bin = $rak === null ? null : collect($rak['levels'])->flatMap(fn ($l) => collect($l['bins'])->map(fn ($b) => $b + ['level' => $l['code']]))
            ->firstWhere('id', $this->binId);

        return view('livewire.warehouse.warehouse-layout', [
            'gudang' => $gudang,
            'denah' => $denah,
            'rak' => $rak,
            'bin' => $bin,
            'zonaPilih' => $zonaPilih,
            'objekPilih' => $objekPilih,
            'bolehUbah' => ! $this->ringkas && (auth()->user()?->can('manageLayout', $gudang) ?? false),
            'skala' => WarehouseLayoutData::SKALA,
            'jenisObjek' => FloorPlanObjectType::options(),
            'alasan' => $this->edit ? ReasonCode::query()->where('context', ReasonContext::Cancel->value)->where('is_active', true)->orderBy('label')->pluck('label', 'code')->all() : [],
        ]);
    }

    private function isiFormZona(Zone $z): void
    {
        $this->formZona[$z->id] = ['name' => (string) $z->name, 'length_m' => $this->angka($z->length_m), 'width_m' => $this->angka($z->width_m),
            'pos_x' => $this->angka($z->pos_x), 'pos_y' => $this->angka($z->pos_y)];
    }

    private function isiFormObjek(FloorPlanObject $o): void
    {
        $this->formObjek = [
            'object_type' => $o->object_type->value,
            'name' => (string) $o->name,
            'pos_x' => $this->angka($o->pos_x), 'pos_y' => $this->angka($o->pos_y),
            'length_m' => $this->angka($o->length_m), 'width_m' => $this->angka($o->width_m),
        ];
    }

    /** @return array{0: ?string, 1: int} */
    private function pilihan(): array
    {
        if (! str_contains($this->terpilih, ':')) {
            return [null, 0];
        }

        [$jenis, $id] = explode(':', $this->terpilih, 2);

        return [$jenis, (int) $id];
    }

    private function bolehAtur(): void
    {
        abort_if($this->ringkas, 403);
        $this->authorize('manageLayout', $this->gudang());
    }

    private function segarkanPilihan(string $jenis, int $id): void
    {
        match ($jenis) {
            'zona' => $this->isiFormZona(Zone::query()->findOrFail($id)),
            'obj' => $this->terpilih === 'obj:'.$id ? $this->isiFormObjek($this->objek($id)) : null,
            'rak' => $this->rakId === $id ? $this->isiFormRak($this->rak($id)) : null,
            default => null,
        };
    }

    /** Posisi gambar benda yang masih ditata otomatis (untuk geser halus pertama). */
    private function posisiGambar(string $jenis, int $id): array
    {
        $denah = app(WarehouseLayoutData::class)->build($this->gudang());

        foreach ($denah['zones'] as $z) {
            if ($jenis === 'zona' && $z['id'] === $id) {
                return [$z['x'], $z['y']];
            }

            foreach ($z['racks'] as $r) {
                if ($jenis === 'rak' && $r['id'] === $id) {
                    return [$r['x'], $r['y']];
                }
            }
        }

        return [0.0, 0.0];
    }

    /** Bin pertama yang berisi (atau bin pertama) — panel langsung menunjukkan isi. */
    private function binAwal(Rack $rak): ?int
    {
        $bins = Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $rak->levels()->pluck('id'))->orderBy('code')->pluck('id');
        $berisi = StockBalance::query()->withoutGlobalScopes()->whereIn('bin_id', $bins)->where('qty_base', '>', 0.00005)
            ->orderBy('bin_id')->value('bin_id');

        return $berisi !== null ? (int) $berisi : ($bins->first() !== null ? (int) $bins->first() : null);
    }

    private function objek(int $id): FloorPlanObject
    {
        return FloorPlanObject::query()->where('warehouse_id', $this->warehouseId)->findOrFail($id);
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
