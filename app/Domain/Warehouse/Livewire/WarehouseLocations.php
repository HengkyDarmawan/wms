<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Livewire;

use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Master\Models\StorageCategory;
use App\Domain\Warehouse\Actions\DeactivateLocation;
use App\Domain\Warehouse\Actions\DeleteBin;
use App\Domain\Warehouse\Actions\DeleteLocation;
use App\Domain\Warehouse\Actions\EditLocation;
use App\Domain\Warehouse\Actions\GenerateBins;
use App\Domain\Warehouse\Actions\MergeBins;
use App\Domain\Warehouse\Actions\ReorderLocation;
use App\Domain\Warehouse\Actions\SaveLocation;
use App\Domain\Warehouse\Actions\SaveWarehouseLayout;
use App\Domain\Warehouse\Enums\BinMergeDirection;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Livewire\Concerns\HandlesWarehouseRules;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinUsage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tab **Zona & rak** di detail gudang (A-410): daftar Zona → Rak → Tingkat → Bin
 * dengan popup Tambah zona/rak/tingkat/bin, Ubah, Hapus (hanya yang belum pernah
 * dipakai) atau Nonaktifkan, urutan zona & rak ↑↓, dan popup **Lihat bin**
 * (tampak depan rak: Gabung, Pisah, Hapus bin). Isian popup tidak hilang saat
 * popup ditutup; dikosongkan hanya setelah tersimpan atau lewat *Kosongkan*.
 * Semua aksi memakai Action yang sama dengan Denah, izin `manageLayout`
 * (`bin.manage` + cakupan gudang).
 */
class WarehouseLocations extends Component
{
    use HandlesWarehouseRules;

    private const POPUP = ['zona', 'rak', 'tingkat', 'bin', 'ubah', 'nonaktif', 'lihat'];

    #[Locked]
    public Warehouse $warehouse;

    /** Popup yang terbuka ('' = tidak ada). */
    public string $popup = '';

    #[Locked]
    public ?int $zonaId = null;

    #[Locked]
    public ?int $rakId = null;

    #[Locked]
    public ?int $generatingLevelId = null;

    #[Locked]
    public string $targetJenis = '';

    #[Locked]
    public ?int $targetId = null;

    /** @var array<string, string> */
    public array $zonaForm = ['code' => '', 'name' => ''];

    /** @var array<string, string> */
    public array $rakForm = ['code' => '', 'name' => '', 'orientation' => 'h', 'levels' => '3', 'bins_per_level' => '5', 'capacity_qty' => ''];

    /** @var array<string, string> */
    public array $tingkatForm = ['code' => '', 'bins' => '5'];

    /** @var array<string, mixed> */
    public array $generator = ['jumlah' => 5, 'prefix' => 'B', 'storage_category_id' => '', 'capacity_qty' => ''];

    /** @var array<string, string> */
    public array $ubahForm = ['code' => '', 'name' => '', 'orientation' => ''];

    public string $nonaktifAlasan = '';

    /** @var array<int, string> bin terpilih di popup Lihat bin */
    public array $binPilih = [];

    /** @var array<string, string> */
    public array $gabungForm = ['sifat' => 'temporary', 'alasan' => ''];

    public string $pisahAlasan = '';

    /** Pengingat setelah kode lokasi diubah (kode bin ikut berubah). */
    public string $pesanKode = '';

    public function mount(Warehouse $warehouse): void
    {
        $this->authorize('view', $warehouse);
        $this->warehouse = $warehouse;
    }

    /** Menutup popup tanpa mengosongkan isiannya. */
    public function tutup(): void
    {
        $this->popup = '';
        $this->ruleError = '';
        $this->resetValidation();
    }

    public function kosongkan(string $form): void
    {
        if (in_array($form, ['zonaForm', 'rakForm', 'tingkatForm', 'generator'], true)) {
            $this->reset($form);
            $this->resetValidation();
        }
    }

    // ===== Tambah =====

    public function bukaZona(): void
    {
        $this->bukaPopup('zona');
    }

    public function simpanZona(SaveLocation $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $berhasil = $this->jalankan(fn () => $aksi->saveZone($this->warehouse, null, $this->zonaForm, auth()->user()), 'zonaForm');
        $this->selesai($berhasil, 'zonaForm', __('Zona ditambahkan.'));
    }

    public function bukaRak(int $zonaId): void
    {
        $this->zonaId = (int) $this->zona($zonaId)->id;
        $this->bukaPopup('rak');
    }

    public function simpanRak(SaveWarehouseLayout $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $zona = $this->zona((int) $this->zonaId);
        $berhasil = $this->jalankan(fn () => $aksi->newRack($zona, $this->rakForm, auth()->user()), 'rakForm');
        $this->selesai($berhasil, 'rakForm', __('Rak ditambahkan lengkap dengan tingkat & bin.'));
    }

    public function bukaTingkat(int $rakId): void
    {
        $this->rakId = (int) $this->rak($rakId)->id;
        $this->bukaPopup('tingkat');
    }

    public function simpanTingkat(SaveWarehouseLayout $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $rak = $this->rak((int) $this->rakId);
        $berhasil = $this->jalankan(fn () => $aksi->newLevel($rak, $this->tingkatForm, auth()->user()), 'tingkatForm');
        $this->selesai($berhasil, 'tingkatForm', __('Tingkat ditambahkan.'));
    }

    /** Nama lama dipertahankan (uji & tautan lama): buka popup Buat bin untuk satu tingkat. */
    public function mintaBuatBin(int $levelId): void
    {
        $this->generatingLevelId = (int) $this->level($levelId)->id;
        $this->bukaPopup('bin');
    }

    public function buatBinMassal(GenerateBins $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $level = $this->level((int) $this->generatingLevelId);

        $this->validate([
            'generator.jumlah' => ['required', 'integer', 'min:1', 'max:200'],
            'generator.prefix' => ['nullable', 'string', 'max:5'],
            'generator.capacity_qty' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['generator.jumlah' => __('Jumlah bin'), 'generator.prefix' => __('Awalan kode')]);

        $dibuat = [];
        $berhasil = $this->jalankan(function () use ($aksi, $level, &$dibuat): void {
            $dibuat = $aksi->handle($level, (int) $this->generator['jumlah'], $this->generator, auth()->user());
        }, 'generator');

        $this->selesai($berhasil, 'generator', count($dibuat).' '.__('bin dibuat.'));
    }

    // ===== Ubah, urutan, hapus, nonaktif =====

    public function bukaUbah(string $jenis, int $id): void
    {
        $m = $this->lokasi($jenis, $id);
        $this->targetJenis = $jenis;
        $this->targetId = (int) $m->id;
        $this->ubahForm = [
            'code' => (string) $m->code,
            'name' => (string) ($m->name ?? ''),
            'orientation' => $m instanceof Rack ? (string) ($m->orientation ?: 'h') : '',
        ];
        $this->bukaPopup('ubah');
    }

    public function simpanUbah(EditLocation $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $m = $this->lokasi($this->targetJenis, (int) $this->targetId);
        $berubah = 0;

        $berhasil = $this->jalankan(function () use ($aksi, $m, &$berubah): void {
            $berubah = match (true) {
                $m instanceof Zone => $aksi->zone($m, $this->ubahForm, auth()->user()),
                $m instanceof Rack => $aksi->rack($m, $this->ubahForm, auth()->user()),
                default => $aksi->level($m, $this->ubahForm, auth()->user()),
            };
        }, 'ubahForm');

        if (! $berhasil) {
            return;
        }

        $this->pesanKode = $berubah > 0
            ? __(':n kode bin ikut berubah. Bila label bin sudah pernah dicetak, cetak ulang labelnya.', ['n' => $berubah])
            : '';
        $this->popup = '';
        $this->dispatch('pesan', teks: __('Perubahan disimpan.'));
    }

    public function geser(string $jenis, int $id, int $arah, ReorderLocation $aksi): void
    {
        $this->bolehAtur();
        $m = $this->lokasi($jenis, $id);

        if ($m instanceof Zone) {
            $aksi->zone($m, $arah, auth()->user());
        } elseif ($m instanceof Rack) {
            $aksi->rack($m, $arah, auth()->user());
        }
    }

    public function hapus(string $jenis, int $id, DeleteLocation $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $m = $this->lokasi($jenis, $id);
        $kode = (string) $m->code;

        $berhasil = $this->jalankan(fn () => match (true) {
            $m instanceof Zone => $aksi->zone($m, auth()->user()),
            $m instanceof Rack => $aksi->rack($m, auth()->user()),
            default => $aksi->level($m, auth()->user()),
        });

        if ($berhasil) {
            $this->dispatch('pesan', teks: __(':k dihapus.', ['k' => $kode]));
        }
    }

    public function bukaNonaktif(string $jenis, int $id): void
    {
        $m = $this->lokasi($jenis, $id);
        $this->targetJenis = $jenis;
        $this->targetId = (int) $m->id;
        $this->nonaktifAlasan = '';
        $this->bukaPopup('nonaktif');
    }

    public function simpanNonaktif(DeactivateLocation $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $m = $this->lokasi($this->targetJenis, (int) $this->targetId);

        $berhasil = $this->jalankan(fn () => match (true) {
            $m instanceof Zone => $aksi->zone($m, $this->nonaktifAlasan, auth()->user()),
            $m instanceof Rack => $aksi->rack($m, $this->nonaktifAlasan, auth()->user()),
            default => $aksi->level($m, $this->nonaktifAlasan, auth()->user()),
        }, 'nonaktif');

        if ($berhasil) {
            $this->popup = '';
            $this->dispatch('pesan', teks: __('Dinonaktifkan.'));
        }
    }

    // ===== Lihat bin: pilih, gabung, pisah, hapus =====

    public function lihatBin(int $rakId): void
    {
        $this->rakId = (int) $this->rak($rakId)->id;
        $this->binPilih = [];
        $this->bukaPopup('lihat');
    }

    public function pilihBin(int $binId): void
    {
        $id = (string) $this->binRak()->firstWhere('id', $binId)?->id;

        if ($id === '') {
            return;
        }

        $this->binPilih = in_array($id, $this->binPilih, true)
            ? array_values(array_diff($this->binPilih, [$id]))
            : [...$this->binPilih, $id];
    }

    /**
     * Gabung bin terpilih. Arah ditentukan dari pilihan: satu tingkat → ke samping
     * (bin utama = nomor terkecil); nomor petak sama di beberapa tingkat → ke atas
     * (bin utama = tingkat terbawah). Aturan lain di {@see MergeBins}.
     */
    public function gabung(MergeBins $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $bins = $this->binRak()->whereIn('id', array_map('intval', $this->binPilih))->values();

        if ($bins->count() < 2) {
            $this->ruleError = __('Pilih minimal 2 bin yang bersebelahan untuk digabung.');

            return;
        }

        if ($bins->pluck('rack_level_id')->unique()->count() === 1) {
            $arah = BinMergeDirection::Side;
            $utama = $bins->sort(fn (Bin $a, Bin $b) => strnatcmp((string) $a->code, (string) $b->code))->first();
        } elseif ($bins->map(fn (Bin $b) => Str::afterLast((string) $b->code, '-'))->unique()->count() === 1) {
            $arah = BinMergeDirection::Above;
            $utama = $bins->sort(fn (Bin $a, Bin $b) => strnatcmp((string) $a->rackLevel?->code, (string) $b->rackLevel?->code))->first();
        } else {
            $this->ruleError = __('Pilih bin bersebelahan di satu tingkat, atau bin bernomor sama di tingkat-tingkat yang bertumpuk.');

            return;
        }

        $lain = $bins->reject(fn (Bin $b) => (int) $b->id === (int) $utama->id)->pluck('id')->all();
        $berhasil = $this->jalankan(fn () => $aksi->merge($utama, $lain, $arah->value, $this->gabungForm['sifat'], $this->gabungForm['alasan'], auth()->user()), 'gabungForm');

        if ($berhasil) {
            $this->binPilih = [];
            $this->gabungForm['alasan'] = '';
            $this->dispatch('pesan', teks: __('Bin digabung.'));
        }
    }

    public function pisah(int $binId, MergeBins $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $bin = $this->binRak()->firstWhere('id', $binId) ?? throw WarehouseRuleException::rule('BR-WH-08', 'Bin tidak ditemukan di rak ini.');

        if ($this->jalankan(fn () => $aksi->split($bin, $this->pisahAlasan, auth()->user()), 'gabungForm')) {
            $this->pisahAlasan = '';
            $this->binPilih = [];
            $this->dispatch('pesan', teks: __('Gabungan dipisah.'));
        }
    }

    public function hapusBin(int $binId, DeleteBin $aksi): void
    {
        $this->bolehAtur();
        $this->resetValidation();
        $bin = $this->binRak()->firstWhere('id', $binId);

        if ($bin !== null && $this->jalankan(fn () => $aksi->handle($bin, auth()->user()))) {
            $this->binPilih = array_values(array_diff($this->binPilih, [(string) $binId]));
            $this->dispatch('pesan', teks: __('Bin :k dihapus. Pastikan label fisik bin ini dicopot dari rak.', ['k' => $bin->code]));
        }
    }

    public function render(): View
    {
        $zones = Zone::query()->where('warehouse_id', $this->warehouse->id)
            ->with(['racks' => fn ($q) => $q->orderBy('sort_order')->orderBy('code'), 'racks.levels'])
            ->orderBy('sort_order')->orderBy('code')->get();

        $levelIds = $zones->flatMap(fn (Zone $z) => $z->racks->flatMap(fn (Rack $r) => $r->levels->pluck('id')))->all();
        $bins = Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $levelIds)
            ->get(['id', 'code', 'rack_level_id', 'occupied_by_bin_id', 'merge_direction', 'merge_type', 'bin_status', 'bin_type']);
        $dipakai = app(BinUsage::class)->dipakai($bins->pluck('id')->map(fn ($id) => (int) $id)->all());
        $perLevel = $bins->sort(fn (Bin $a, Bin $b) => strnatcmp((string) $a->code, (string) $b->code))->groupBy('rack_level_id');

        // Ringkasan per lokasi: jumlah bin & apakah ada bin yang pernah dipakai (→ Nonaktifkan, bukan Hapus).
        $info = ['zona' => [], 'rak' => [], 'tingkat' => []];
        foreach ($zones as $z) {
            $info['zona'][$z->id] = ['rak' => $z->racks->count(), 'bin' => 0, 'dipakai' => false];
            foreach ($z->racks as $r) {
                $info['rak'][$r->id] = ['tingkat' => $r->levels->count(), 'bin' => 0, 'dipakai' => false];
                foreach ($r->levels as $l) {
                    $isi = $perLevel->get($l->id, collect());
                    $pakai = $isi->contains(fn (Bin $b) => isset($dipakai[(int) $b->id]));
                    $info['tingkat'][$l->id] = ['bin' => $isi->count(), 'dipakai' => $pakai];
                    $info['rak'][$r->id]['bin'] += $isi->count();
                    $info['rak'][$r->id]['dipakai'] = $info['rak'][$r->id]['dipakai'] || $pakai;
                }
                $info['zona'][$z->id]['bin'] += $info['rak'][$r->id]['bin'];
                $info['zona'][$z->id]['dipakai'] = $info['zona'][$z->id]['dipakai'] || $info['rak'][$r->id]['dipakai'];
            }
        }

        return view('livewire.warehouse.warehouse-locations', [
            'zones' => $zones,
            'perLevel' => $perLevel,
            'dipakai' => $dipakai,
            'info' => $info,
            'bolehAtur' => auth()->user()?->can('manageLayout', $this->warehouse) ?? false,
            'urutTingkat' => fn (Collection $levels) => $levels->sort(fn ($a, $b) => strnatcmp((string) $b->code, (string) $a->code))->values(),
            'alasan' => $this->popup === 'nonaktif' ? ReasonCode::query()->where('context', ReasonContext::Cancel->value)->where('is_active', true)->orderBy('label')->pluck('label', 'code')->all() : [],
            'storageCategories' => $this->popup === 'bin' ? StorageCategory::query()->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'rakLihat' => $this->popup === 'lihat' && $this->rakId ? $zones->flatMap->racks->firstWhere('id', $this->rakId) : null,
            'target' => in_array($this->popup, ['ubah', 'nonaktif'], true) && $this->targetId ? $this->lokasi($this->targetJenis, (int) $this->targetId) : null,
            'zonaTarget' => $this->zonaId ? $zones->firstWhere('id', $this->zonaId) : null,
            'rakTarget' => $this->rakId ? $zones->flatMap->racks->firstWhere('id', $this->rakId) : null,
            'levelTarget' => $this->generatingLevelId ? RackLevel::query()->with('rack')->find($this->generatingLevelId) : null,
        ]);
    }

    // ===== Pembantu =====

    private function bukaPopup(string $popup): void
    {
        $this->bolehAtur();
        $this->ruleError = '';
        $this->resetValidation();
        $this->popup = in_array($popup, self::POPUP, true) ? $popup : '';
    }

    private function selesai(bool $berhasil, string $form, string $pesan): void
    {
        if (! $berhasil) {
            return;
        }

        $this->reset($form);
        $this->popup = '';
        $this->dispatch('pesan', teks: $pesan);
    }

    private function bolehAtur(): void
    {
        $this->authorize('manageLayout', $this->warehouse);
    }

    private function lokasi(string $jenis, int $id): Zone|Rack|RackLevel
    {
        return match ($jenis) {
            'zona' => $this->zona($id),
            'rak' => $this->rak($id),
            'tingkat' => $this->level($id),
            default => abort(404),
        };
    }

    private function zona(int $id): Zone
    {
        return Zone::query()->where('warehouse_id', $this->warehouse->id)->findOrFail($id);
    }

    private function rak(int $id): Rack
    {
        return Rack::query()->whereHas('zone', fn ($q) => $q->where('warehouse_id', $this->warehouse->id))->findOrFail($id);
    }

    private function level(int $id): RackLevel
    {
        return RackLevel::query()->whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $this->warehouse->id))->findOrFail($id);
    }

    /** @return Collection<int, Bin> bin di rak yang sedang dilihat */
    private function binRak(): Collection
    {
        if (! $this->rakId) {
            return collect();
        }

        return Bin::query()->withoutGlobalScopes()->with('rackLevel')
            ->whereIn('rack_level_id', RackLevel::query()->where('rack_id', $this->rak((int) $this->rakId)->id)->pluck('id'))->get();
    }
}
