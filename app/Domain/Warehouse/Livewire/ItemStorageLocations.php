<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Models\Item;
use App\Domain\Warehouse\Actions\SaveItemStorageLocations;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\ItemStorageLocation;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Support\BinCode;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kartu **Tempat simpan** di detail item (A-365): per gudang dalam cakupan —
 * urutan, kode pendek / rak / area, tanda Khusus — dengan tombol Ubah bagi
 * pemegang `bin.manage`. Ubah = daftar berurutan satu gudang, disimpan sekali
 * lewat {@see SaveItemStorageLocations::replace()}.
 */
class ItemStorageLocations extends Component
{
    #[Locked]
    public int $itemId;

    /** Gudang yang sedang diubah (0 = tidak ada). */
    public int $gudangUbah = 0;

    /** @var array<int, array{tempat: string, khusus: bool, label: string}> */
    public array $baris = [];

    public string $tempatBaru = '';

    public bool $khususBaru = false;

    public string $gudangBaru = '';

    public string $galat = '';

    public function mount(Item $item): void
    {
        $this->authorize('view', $item);
        $this->itemId = (int) $item->id;
    }

    public function ubah(int $gudangId): void
    {
        $gudang = $this->gudangBoleh($gudangId);
        $this->gudangUbah = (int) $gudang->id;
        $this->galat = '';
        $this->tempatBaru = '';
        $this->khususBaru = false;
        $label = $this->opsiTempat($gudang)->pluck('text', 'value');
        $this->baris = collect(app(SaveItemStorageLocations::class)->barisSekarang($this->item(), $gudang))
            ->map(fn (array $b) => $b + ['label' => (string) ($label[$b['tempat']] ?? $b['tempat'])])->all();
    }

    public function tambahGudang(): void
    {
        if ((int) $this->gudangBaru > 0) {
            $this->ubah((int) $this->gudangBaru);
            $this->gudangBaru = '';
        }
    }

    public function tambah(): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);
        $opsi = $this->opsiTempat($gudang)->firstWhere('value', $this->tempatBaru);

        if ($opsi === null) {
            $this->galat = __('Pilih tempat dulu.');

            return;
        }

        if (collect($this->baris)->contains('tempat', $this->tempatBaru)) {
            $this->galat = __('Tempat itu sudah ada di daftar.');

            return;
        }

        $this->baris[] = ['tempat' => $this->tempatBaru, 'khusus' => $this->khususBaru, 'label' => $opsi['text']];
        $this->tempatBaru = '';
        $this->khususBaru = false;
        $this->galat = '';
    }

    public function geser(int $i, int $arah): void
    {
        $j = $i + $arah;

        if (isset($this->baris[$i], $this->baris[$j])) {
            [$this->baris[$i], $this->baris[$j]] = [$this->baris[$j], $this->baris[$i]];
        }
    }

    public function hapus(int $i): void
    {
        unset($this->baris[$i]);
        $this->baris = array_values($this->baris);
    }

    public function batal(): void
    {
        $this->reset('gudangUbah', 'baris', 'tempatBaru', 'khususBaru', 'galat');
    }

    public function simpan(SaveItemStorageLocations $aksi): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);

        try {
            $aksi->replace($this->item(), $gudang, array_map(fn ($b) => ['tempat' => $b['tempat'], 'khusus' => (bool) $b['khusus']], $this->baris), auth()->user());
        } catch (WarehouseRuleException $e) {
            $this->galat = $e->getMessage();

            return;
        }

        $this->batal();
        $this->dispatch('pesan', teks: __('Tempat simpan disimpan.'));
    }

    public function render(): View
    {
        $item = $this->item();
        $tempat = ItemStorageLocation::query()->with('bin:id,code', 'rack.zone', 'warehouse:id,code,name')
            ->where('item_id', $item->id)->orderBy('warehouse_id')->orderBy('sequence')->get()->groupBy('warehouse_id');
        $bolehUbah = auth()->user()?->hasPermission('bin.manage') ?? false;

        return view('livewire.warehouse.item-storage-locations', [
            'tempat' => $tempat,
            'bolehUbah' => $bolehUbah,
            'gudangLain' => $bolehUbah ? Warehouse::query()->active()->whereNotIn('id', $tempat->keys())->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'gudangUbahModel' => $this->gudangUbah > 0 ? Warehouse::query()->find($this->gudangUbah) : null,
            'opsi' => $this->gudangUbah > 0 ? $this->opsiTempat($this->gudangBoleh($this->gudangUbah))->all() : [],
        ]);
    }

    /**
     * Pilihan tempat satu gudang: seluruh rak, area lantai, lalu bin di rak.
     *
     * @return Collection<int, array{value: string, text: string, badge: string, sub: string}>
     */
    private function opsiTempat(Warehouse $gudang): Collection
    {
        $rak = Rack::query()->with('zone:id,code')->where('is_active', true)
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id)->where('is_active', true))
            ->orderBy('code')->get();
        $kembar = $rak->pluck('code')->countBy()->filter(fn ($n) => $n > 1)->all();
        $opsi = $rak->sortBy(fn (Rack $r) => [(int) $r->is_area, $r->zone->code, $r->code])->map(fn (Rack $r) => [
            'value' => 'rak:'.$r->id,
            'text' => ($r->is_area ? __('Area') : __('Rak')).' '.$r->zone->code.' · '.$r->code.($r->name ? ' — '.$r->name : ''),
            'badge' => $r->is_area ? __('area lantai') : __('seluruh rak'),
            'sub' => __('Zona :z', ['z' => $r->zone->code]),
        ])->values();

        $bins = Bin::query()->withoutGlobalScopes()->with('rackLevel.rack.zone')
            ->where('warehouse_id', $gudang->id)->where('bin_type', BinType::Storage->value)
            ->where('bin_status', '!=', BinStatus::Inactive->value)->whereNull('occupied_by_bin_id')
            ->whereNotNull('rack_level_id')
            ->whereHas('rackLevel.rack', fn ($q) => $q->where('is_area', false)->where('is_active', true))
            ->orderBy('code')->get(['id', 'code', 'rack_level_id']);

        return $opsi->merge($bins->map(fn (Bin $b) => [
            'value' => 'bin:'.$b->id,
            'text' => BinCode::pendek((string) $b->code, isset($kembar[$b->rackLevel?->rack?->code])),
            'badge' => __('bin'),
            'sub' => (string) $b->code,
        ]))->values();
    }

    /** Gudang dalam cakupan & boleh diatur (`bin.manage`, BR-ACC-05). */
    private function gudangBoleh(int $id): Warehouse
    {
        $gudang = Warehouse::query()->findOrFail($id);
        $this->authorize('manageLayout', $gudang);

        return $gudang;
    }

    private function item(): Item
    {
        return Item::query()->findOrFail($this->itemId);
    }
}
