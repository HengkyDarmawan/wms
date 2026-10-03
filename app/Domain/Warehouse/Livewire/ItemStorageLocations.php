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
use App\Domain\Warehouse\Support\WarehouseLayoutData;
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

    /** A-400: popup *Pilih tempat di denah* (tampak atas → tampak depan rak) terbuka. */
    public bool $pilihDenah = false;

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

    /** A-400: buka popup pilih tempat bergambar untuk gudang yang sedang diubah. */
    public function bukaDenah(): void
    {
        $this->gudangBoleh($this->gudangUbah);
        $this->pilihDenah = true;
        $this->galat = '';
    }

    public function tutupDenah(): void
    {
        $this->pilihDenah = false;
    }

    /** A-400: klik rak/area di tampak atas → rak terpilih (zona ikut), pilihan bin dikosongkan. */
    public function pilihRakDenah(int $rakId): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);
        $rak = $this->rakGudang($gudang)->firstWhere('id', $rakId);

        if ($rak === null) {
            $this->galat = __('Rak tidak ditemukan di gudang ini.');

            return;
        }

        $tempat = app(WarehouseLayoutData::class)->tempatSimpan((int) $gudang->id, (int) $rak->id);

        if ($this->khususLain($tempat['rak'][(int) $rak->id] ?? []) !== null) {
            $this->galat = __('Rak :rak khusus untuk barang :barang — tidak bisa dipilih.', ['rak' => $rak->code, 'barang' => $this->khususLain($tempat['rak'][(int) $rak->id] ?? [])]);

            return;
        }

        $this->zonaBaru = (string) $rak->zone_id;
        $this->rakBaru = (string) $rak->id;
        $this->binBaru = [];
        $this->galat = '';
    }

    /** A-400: ketuk petak di tampak depan = pilih/lepas bin itu ("kursi bioskop"). */
    public function toggleBin(int $binId): void
    {
        $gudang = $this->gudangBoleh($this->gudangUbah);

        if ($this->rakBaru === '') {
            return;
        }

        $petak = collect($this->petakRak($gudang, (int) $this->rakBaru)['tingkat'])->flatMap(fn ($t) => $t['bins'])->firstWhere('id', $binId);

        if ($petak === null || ! $petak['boleh']) {
            $this->galat = $petak !== null && $petak['khusus_lain'] !== null
                ? __('Bin :bin khusus untuk barang :barang — tidak bisa dipilih.', ['bin' => $petak['pendek'], 'barang' => $petak['khusus_lain']])
                : __('Bin itu tidak bisa dijadikan tempat simpan.');

            return;
        }

        $k = (string) $binId;
        $this->binBaru = in_array($k, array_map('strval', $this->binBaru), true)
            ? array_values(array_filter(array_map('strval', $this->binBaru), fn ($v) => $v !== $k))
            : array_values(array_merge(array_map('strval', $this->binBaru), [$k]));
        $this->galat = '';
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
        $this->reset('gudangUbah', 'baris', 'zonaBaru', 'rakBaru', 'binBaru', 'khususBaru', 'galat', 'pilihDenah');
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
        $tempat = ItemStorageLocation::query()->with('bin:id,code', 'rack.zone', 'warehouse')
            ->where('item_id', $item->id)->orderBy('warehouse_id')->orderBy('sequence')->get()->groupBy('warehouse_id');
        $bolehUbah = auth()->user()?->hasPermission('bin.manage') ?? false;
        $gudangUbahModel = $this->gudangUbah > 0 ? Warehouse::query()->find($this->gudangUbah) : null;

        return view('livewire.warehouse.item-storage-locations', [
            'item' => $item,
            'tempat' => $tempat,
            'bolehUbah' => $bolehUbah,
            'gudangLain' => $bolehUbah ? Warehouse::query()->active()->whereNotIn('id', $tempat->keys())->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'gudangUbahModel' => $gudangUbahModel,
            // A-401: denah mini hanya-lihat per gudang (hanya saat tidak sedang mengubah).
            'denahTersimpan' => $this->gudangUbah === 0 ? $tempat->map(fn ($daftar, $gudangId) => $this->denahMini($daftar->first()->warehouse, $daftar))->all() : [],
            // A-400: isi popup Pilih tempat di denah.
            'denahPilih' => $this->pilihDenah && $gudangUbahModel ? $this->denahMini($gudangUbahModel, null) : null,
            'petak' => $this->pilihDenah && $gudangUbahModel && $this->rakBaru !== '' ? $this->petakRak($gudangUbahModel, (int) $this->rakBaru) : null,
        ] + $this->pilihanTambah());
    }

    /**
     * A-400/A-401: data denah mini — muatan denah yang sama dengan layar Denah
     * ({@see WarehouseLayoutData::payload}) + kunci tempat yang disorot
     * (`rak:<id>` / `bin:<id>`) dan rak yang ditolak karena khusus barang lain.
     *
     * @param  Collection<int, ItemStorageLocation>|null  $tersimpan  null = pakai daftar yang sedang diubah
     * @return array{d: array<string, mixed>, sorot: array<int, string>, tolak: array<int, string>, rakAktif: ?int}
     */
    private function denahMini(Warehouse $gudang, ?Collection $tersimpan): array
    {
        $data = app(WarehouseLayoutData::class);
        $sorot = $tersimpan !== null
            ? $tersimpan->map(fn (ItemStorageLocation $t) => $t->bin_id !== null ? 'bin:'.$t->bin_id : 'rak:'.$t->rack_id)->values()->all()
            : collect($this->baris)->pluck('tempat')->all();
        $tempat = $data->tempatSimpan((int) $gudang->id);
        $tolak = [];

        foreach ($tempat['rak'] as $rakId => $barang) {
            if (($kode = $this->khususLain($barang)) !== null) {
                $tolak[(string) $rakId] = $kode;
            }
        }

        return [
            'd' => $data->payload($gudang),
            'sorot' => $sorot,
            'tolak' => $tolak,
            'rakAktif' => $tersimpan === null && $this->rakBaru !== '' ? (int) $this->rakBaru : null,
        ];
    }

    /**
     * A-400: tampak depan satu rak untuk popup — tingkat (teratas di atas) × petak,
     * per petak: boleh dipilih, terpilih, sudah di daftar, barang lain, khusus lain, tergabung.
     *
     * @return array{rak: array<string, mixed>, tingkat: array<int, array{id: int, kode: string, bins: array<int, array<string, mixed>>}>}
     */
    private function petakRak(Warehouse $gudang, int $rakId): array
    {
        $rak = Rack::query()->with(['zone:id,code,name', 'levels' => fn ($q) => $q->where('is_active', true)->orderByDesc('code')])->findOrFail($rakId);
        $tempat = app(WarehouseLayoutData::class)->tempatSimpan((int) $gudang->id, (int) $rak->id);
        $bins = Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $rak->levels->pluck('id'))
            ->where('warehouse_id', $gudang->id)->orderBy('code')->get()->groupBy('rack_level_id');
        $kodeUtama = $bins->flatten()->pluck('code', 'id');
        $sudah = collect($this->baris)->pluck('tempat')->all();
        $terpilih = array_map('strval', $this->binBaru);
        $kembar = Rack::query()->where('code', $rak->code)->whereKeyNot($rak->id)
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->exists();
        $awalan = $kembar ? (string) $rak->zone->code : null;

        $tingkat = $rak->levels->map(fn ($l) => [
            'id' => (int) $l->id,
            'kode' => (string) $l->code,
            'bins' => ($bins->get($l->id) ?? collect())->map(function (Bin $b) use ($tempat, $sudah, $terpilih, $rak, $l, $awalan, $kodeUtama) {
                $barang = $tempat['bin'][(int) $b->id] ?? [];
                $khususLain = $this->khususLain($barang);
                $tergabung = $b->occupied_by_bin_id !== null;
                $nonaktif = $b->bin_status === BinStatus::Inactive;

                return [
                    'id' => (int) $b->id,
                    'short' => Str::afterLast((string) $b->code, '-'),
                    'pendek' => BinCode::dari($awalan, (string) $rak->code, (string) $l->code, Str::afterLast((string) $b->code, '-'), (bool) $rak->is_area),
                    'boleh' => $b->bin_type === BinType::Storage && ! $nonaktif && ! $tergabung && $khususLain === null,
                    'terpilih' => in_array((string) $b->id, $terpilih, true),
                    'sudah' => in_array('bin:'.$b->id, $sudah, true),
                    'milik_ini' => collect($barang)->contains(fn ($x) => (int) $x['id'] === $this->itemId),
                    'barang_lain' => collect($barang)->filter(fn ($x) => (int) $x['id'] !== $this->itemId && ! $x['khusus'])->pluck('code')->values()->all(),
                    'khusus_lain' => $khususLain,
                    'nonaktif' => $nonaktif,
                    'tergabung' => $tergabung,
                    'utama' => $tergabung ? Str::afterLast((string) ($kodeUtama[$b->occupied_by_bin_id] ?? ''), '-') : null,
                ];
            })->values()->all(),
        ])->values()->all();

        return [
            'rak' => [
                'id' => (int) $rak->id,
                'code' => (string) $rak->code,
                'name' => $rak->name,
                'zona' => (string) $rak->zone->code,
                'is_area' => (bool) $rak->is_area,
                'arah' => $rak->orientation === 'v' ? __('Memanjang ke bawah') : __('Memanjang ke samping'),
                'ukuran' => $rak->length_m !== null && $rak->width_m !== null ? str_replace('.', ',', rtrim(rtrim(number_format((float) $rak->length_m, 2, '.', ''), '0'), '.')).' × '.str_replace('.', ',', rtrim(rtrim(number_format((float) $rak->width_m, 2, '.', ''), '0'), '.')).' m' : null,
                'sudah' => in_array('rak:'.$rak->id, $sudah, true),
                'barang' => collect($tempat['rak'][(int) $rak->id] ?? [])->filter(fn ($x) => (int) $x['id'] !== $this->itemId)->pluck('code')->values()->all(),
                'jumlah_tingkat' => count($tingkat),
                'jumlah_bin' => (int) collect($tingkat)->max(fn ($t) => count($t['bins'])),
            ],
            'tingkat' => $tingkat,
        ];
    }

    /**
     * Kode barang lain yang menguasai tempat ini (Khusus Barang Ini, BR-WH-10), atau null.
     *
     * @param  array<int, array{id: int, code: string, khusus: bool}>  $barang
     */
    private function khususLain(array $barang): ?string
    {
        foreach ($barang as $x) {
            if ($x['khusus'] && (int) $x['id'] !== $this->itemId) {
                return (string) $x['code'];
            }
        }

        return null;
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
