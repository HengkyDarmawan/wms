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
use Illuminate\Support\Str;
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

    /** Tambah tempat bertahap: Zona → Rak/area → Bin (opsional, banyak sekaligus). */
    public string $zonaBaru = '';

    public string $rakBaru = '';

    /** @var array<int, string> id bin terpilih di rak itu; kosong = seluruh rak */
    public array $binBaru = [];

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
        $this->reset('zonaBaru', 'rakBaru', 'binBaru', 'khususBaru');
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

    public function updatedZonaBaru(): void
    {
        $this->reset('rakBaru', 'binBaru');
    }

    public function updatedRakBaru(): void
    {
        $this->reset('binBaru');
    }

    /** Tombol "Semua L1" dsb.: tambahkan semua bin satu tingkat ke pilihan bin. */
    public function pilihTingkat(int $levelId): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);
        $bin = $this->binRak($gudang, (int) $this->rakBaru)->where('level_id', $levelId)->pluck('value')->all();
        $this->binBaru = array_values(array_unique(array_merge(array_map('strval', $this->binBaru), $bin)));
    }

    /**
     * Tambah ke daftar: bin terpilih (satu baris per bin), atau seluruh rak / area bila bin kosong.
     * Nilai `tempat` tetap `rak:<id>` / `bin:<id>` — aksi simpan tidak berubah.
     */
    public function tambah(): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);
        $semua = $this->opsiTempat($gudang)->keyBy('value');
        $rak = $semua->get('rak:'.$this->rakBaru);

        if ($rak === null) {
            $this->galat = __('Pilih rak atau area dulu.');

            return;
        }

        $binRak = $this->binRak($gudang, (int) $this->rakBaru)->pluck('value')->all();
        $pilihan = array_values(array_intersect(array_map('strval', $this->binBaru), $binRak));
        $tempat = $pilihan === [] ? ['rak:'.$this->rakBaru] : array_map(fn ($id) => 'bin:'.$id, $pilihan);

        $ada = collect($this->baris)->pluck('tempat')->all();
        $baru = array_values(array_diff($tempat, $ada));

        if ($baru === []) {
            $this->galat = __('Tempat itu sudah ada di daftar.');

            return;
        }

        foreach ($baru as $t) {
            $this->baris[] = ['tempat' => $t, 'khusus' => $this->khususBaru, 'label' => (string) $semua->get($t)['text']];
        }

        $lewati = count($tempat) - count($baru);
        $this->galat = $lewati > 0 ? __(':n tempat sudah ada di daftar, dilewati.', ['n' => $lewati]) : '';
        $this->reset('rakBaru', 'binBaru', 'khususBaru');
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
        $this->reset('gudangUbah', 'baris', 'zonaBaru', 'rakBaru', 'binBaru', 'khususBaru', 'galat');
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
        ] + $this->pilihanTambah());
    }

    /**
     * Isi kotak bertahap Tambah tempat: Zona → Rak/area (zona itu) → Bin (rak itu, per tingkat).
     *
     * @return array<string, mixed>
     */
    private function pilihanTambah(): array
    {
        if ($this->gudangUbah === 0) {
            return ['opsiZona' => [], 'opsiRak' => [], 'opsiBin' => [], 'tingkat' => [], 'rakArea' => false];
        }

        $gudang = $this->gudangBoleh($this->gudangUbah);
        $rak = $this->rakGudang($gudang);
        $diZona = $rak->filter(fn (Rack $r) => (string) $r->zone_id === $this->zonaBaru);
        $dipilih = $rak->firstWhere('id', (int) $this->rakBaru);
        $bin = $dipilih && ! $dipilih->is_area ? $this->binRak($gudang, (int) $dipilih->id) : collect();

        return [
            'opsiZona' => $rak->pluck('zone')->unique('id')->sortBy('code')
                ->mapWithKeys(fn ($z) => [$z->id => $z->code.($z->name ? ' — '.$z->name : '')])->all(),
            'opsiRak' => $diZona->sortBy(fn (Rack $r) => [(int) $r->is_area, $r->code])->map(fn (Rack $r) => [
                'value' => $r->id,
                'text' => $r->code.($r->name ? ' — '.$r->name : ''),
                'badge' => $r->is_area ? __('area lantai') : __('rak'),
            ])->values()->all(),
            'opsiBin' => $bin->pluck('text', 'value')->all(),
            'tingkat' => $bin->unique('level_id')->map(fn ($b) => ['id' => $b['level_id'], 'kode' => $b['level_code']])->values()->all(),
            'rakArea' => (bool) $dipilih?->is_area,
        ];
    }

    /** Rak & area aktif di zona aktif gudang itu. @return Collection<int, Rack> */
    private function rakGudang(Warehouse $gudang): Collection
    {
        return Rack::query()->with('zone:id,code,name')->where('is_active', true)
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id)->where('is_active', true))
            ->orderBy('code')->get();
    }

    /**
     * Bin penyimpanan yang boleh jadi tempat simpan (aktif, bukan tergabung) — satu rak atau seluruh gudang.
     *
     * @return Collection<int, Bin>
     */
    private function binGudang(Warehouse $gudang, ?int $rakId = null): Collection
    {
        return Bin::query()->withoutGlobalScopes()->with('rackLevel.rack')
            ->where('warehouse_id', $gudang->id)->where('bin_type', BinType::Storage->value)
            ->where('bin_status', '!=', BinStatus::Inactive->value)->whereNull('occupied_by_bin_id')
            ->whereNotNull('rack_level_id')
            ->whereHas('rackLevel.rack', fn ($q) => $q->where('is_area', false)->where('is_active', true)
                ->when($rakId !== null, fn ($q) => $q->whereKey($rakId)))
            ->orderBy('code')->get(['id', 'code', 'rack_level_id']);
    }

    /**
     * Bin satu rak untuk kotak tag: label pendek "L1 · B01" dan tingkatnya.
     *
     * @return Collection<int, array{value: string, text: string, level_id: int, level_code: string}>
     */
    private function binRak(Warehouse $gudang, int $rakId): Collection
    {
        return $this->binGudang($gudang, $rakId)
            ->sortBy(fn (Bin $b) => [(string) $b->rackLevel?->code, (string) $b->code])
            ->map(fn (Bin $b) => [
                'value' => (string) $b->id,
                'text' => $b->rackLevel?->code.' · '.Str::afterLast((string) $b->code, '-'),
                'level_id' => (int) $b->rack_level_id,
                'level_code' => (string) $b->rackLevel?->code,
            ])->values();
    }

    /**
     * Label semua tempat satu gudang (seluruh rak, area lantai, bin) untuk daftar berurutan.
     *
     * @return Collection<int, array{value: string, text: string}>
     */
    private function opsiTempat(Warehouse $gudang): Collection
    {
        $rak = $this->rakGudang($gudang);
        $kembar = $rak->pluck('code')->countBy()->filter(fn ($n) => $n > 1)->all();
        $opsi = $rak->map(fn (Rack $r) => [
            'value' => 'rak:'.$r->id,
            'text' => ($r->is_area ? __('Area') : __('Seluruh rak')).' '.$r->zone->code.' · '.$r->code.($r->name ? ' — '.$r->name : ''),
        ]);

        return $opsi->merge($this->binGudang($gudang)->map(fn (Bin $b) => [
            'value' => 'bin:'.$b->id,
            'text' => BinCode::pendek((string) $b->code, isset($kembar[$b->rackLevel?->rack?->code])),
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
