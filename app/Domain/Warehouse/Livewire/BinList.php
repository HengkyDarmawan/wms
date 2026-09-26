<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Actions\ChangeBinStatus;
use App\Domain\Warehouse\Actions\SaveBin;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar 12-warehouse §6 — daftar bin lintas gudang.
 *
 * Bin dibekukan saat sesi opname (BR-OPN-02) dan ditandai perlu dihitung
 * setelah short pick (A-67, BR-SJ-02). Cakupan gudang dibatasi global scope.
 */
class BinList extends Component
{
    use HandlesWarehouseRules;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $warehouseFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $flagFilter = '';

    /** A-254: saring per zona / rak (kode). */
    #[Url(except: '')]
    public string $zoneFilter = '';

    #[Url(except: '')]
    public string $rackFilter = '';

    /** Ubah bin (kategori & kapasitas; kode terkunci, BR-WH-01). */
    #[Locked]
    public ?int $ubahId = null;

    /** @var array<string, string> */
    public array $formBin = [];

    #[Locked]
    public ?int $actingId = null;

    public string $aksi = '';

    public string $reasonCode = '';

    public string $reasonNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Bin::class);
    }

    public function updated(string $property): void
    {
        if ($property === 'search' || str_ends_with($property, 'Filter')) {
            $this->resetPage();
        }
    }

    /** Membuka dialog bekukan atau nonaktifkan; keduanya menuntut alasan. */
    public function minta(int $id, string $aksi): void
    {
        $bin = Bin::findOrFail($id);

        $this->authorize('manage', $bin);

        $this->ruleError = '';
        $this->actingId = $bin->id;
        $this->aksi = in_array($aksi, ['bekukan', 'nonaktifkan'], true) ? $aksi : '';
        $this->reasonCode = '';
        $this->reasonNotes = '';
    }

    public function jalankanAksi(ChangeBinStatus $action): void
    {
        $bin = Bin::findOrFail($this->actingId);

        $this->authorize('manage', $bin);

        $this->validate(['reasonCode' => ['required', 'string']], attributes: ['reasonCode' => __('Alasan')]);

        $berhasil = $this->jalankan(fn () => match ($this->aksi) {
            'bekukan' => $action->freeze($bin, $this->reasonCode, null, auth()->user()),
            'nonaktifkan' => $action->deactivate($bin, $this->reasonCode, $this->reasonNotes ?: null, auth()->user()),
            default => null,
        });

        if (! $berhasil) {
            return;
        }

        $this->actingId = null;
        $this->aksi = '';
        $this->dispatch('pesan', teks: __('Status bin diperbarui.'));
    }

    public function mintaUbah(int $id): void
    {
        $bin = Bin::findOrFail($id);
        $this->authorize('update', $bin);

        $this->ubahId = (int) $bin->id;
        $this->formBin = [
            'storage_category_id' => (string) ($bin->storage_category_id ?? ''),
            'capacity_qty' => $this->angka($bin->capacity_qty),
            'capacity_weight' => $this->angka($bin->capacity_weight),
            'capacity_volume' => $this->angka($bin->capacity_volume),
            'capacity_length' => $this->angka($bin->capacity_length),
            'capacity_mode' => (string) ($bin->capacity_mode ?? ''),
        ];
        $this->resetValidation();
    }

    public function simpanBin(SaveBin $action): void
    {
        $bin = Bin::findOrFail($this->ubahId);
        $this->authorize('update', $bin);

        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($bin->warehouse_id);

        if ($this->jalankan(fn () => $action->handle($gudang, $bin, $this->formBin + [
            'bin_type' => $bin->bin_type->value,
            'rack_level_id' => $bin->rack_level_id,
            'project_id' => $bin->project_id,
        ], auth()->user()), 'formBin')) {
            $this->ubahId = null;
            $this->dispatch('pesan', teks: __('Bin disimpan.'));
        }
    }

    public function batalUbah(): void
    {
        $this->ubahId = null;
        $this->resetValidation();
    }

    public function batalAksi(): void
    {
        $this->actingId = null;
        $this->aksi = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function cairkan(int $id, ChangeBinStatus $action): void
    {
        $bin = Bin::findOrFail($id);

        $this->authorize('manage', $bin);

        if ($this->jalankan(fn () => $action->unfreeze($bin, auth()->user()))) {
            $this->dispatch('pesan', teks: __('Bin dicairkan.'));
        }
    }

    public function aktifkan(int $id, ChangeBinStatus $action): void
    {
        $bin = Bin::findOrFail($id);

        $this->authorize('manage', $bin);

        $action->reactivate($bin, auth()->user());

        $this->dispatch('pesan', teks: __('Bin diaktifkan kembali.'));
    }

    /** A-67 dan BR-SJ-02: penanda "perlu dihitung" bisa dipasang dan dilepas manual. */
    public function ubahPenandaHitung(int $id, bool $flag, ChangeBinStatus $action): void
    {
        $bin = Bin::findOrFail($id);

        $this->authorize('manage', $bin);

        $action->flagForCount($bin, $flag, auth()->user());

        $this->dispatch('pesan', teks: $flag ? __('Bin ditandai perlu dihitung.') : __('Penanda hitung dilepas.'));
    }

    public function render(): View
    {
        return view('livewire.warehouse.bin-list', [
            'bins' => $this->bins(),
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code', 'name']),
            'binTypes' => BinType::options(),
            'binStatuses' => BinStatus::options(),
            'storageCategories' => StorageCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'alasan' => $this->pilihanAlasan($this->aksi === 'bekukan' ? ReasonContext::Adjustment : ReasonContext::Cancel),
        ]);
    }

    private function angka(mixed $n): string
    {
        return $n === null ? '' : rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    }

    private function bins(): LengthAwarePaginator
    {
        return Bin::query()
            ->with('warehouse:id,code,name', 'storageCategory:id,name,capacity_mode', 'project:id,code,name', 'rackLevel:id,code,rack_id', 'rackLevel.rack:id,code,zone_id,is_area', 'rackLevel.rack.zone:id,code', 'occupiedBy:id,code')
            ->when($this->search !== '', fn (Builder $q) => $q->where('code', 'like', '%'.$this->search.'%'))
            ->when($this->warehouseFilter !== '', fn (Builder $q) => $q->where('warehouse_id', (int) $this->warehouseFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('bin_type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('bin_status', $this->statusFilter))
            ->when($this->flagFilter !== '', fn (Builder $q) => $q->where('count_flag', $this->flagFilter === 'ya'))
            ->when($this->zoneFilter !== '', fn (Builder $q) => $q->whereHas('rackLevel.rack.zone', fn ($z) => $z->where('code', mb_strtoupper(trim($this->zoneFilter)))))
            ->when($this->rackFilter !== '', fn (Builder $q) => $q->whereHas('rackLevel.rack', fn ($r) => $r->where('code', mb_strtoupper(trim($this->rackFilter)))))
            ->orderBy('code')
            ->paginate(25);
    }
}
