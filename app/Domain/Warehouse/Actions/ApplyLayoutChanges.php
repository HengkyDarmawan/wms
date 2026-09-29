<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\FloorPlanObject;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permission: `bin.manage` — **Simpan perubahan** mode Atur denah (K-H, A-353).
 *
 * Semua geser, ubah ukuran, putar, tambah zona/rak/level/bin/area/objek,
 * ubah isian, gabung/pisah/hapus/lebar bin, kapasitas area lantai
 * (Bagian 2: K-B–K-E, A-359–A-364), dan nonaktifkan dikerjakan di browser lalu dikirim sekali
 * sebagai daftar operasi berurutan. Semuanya diterapkan dalam **satu
 * transaksi** lewat aksi yang sudah ada ({@see SaveWarehouseLayout},
 * {@see SaveFloorPlanObject}, {@see SaveLocation}, {@see GenerateBins},
 * {@see DeactivateLocation}, {@see MergeBins}, {@see DeleteBin}, {@see SaveBinShape},
 * {@see ChangeBinStatus}) sehingga aturan & jejak audit tetap sama.
 *
 * Setiap operasi dicoba di titik simpan (savepoint) sendiri supaya semua
 * galat terkumpul; bila ada satu saja yang gagal, **seluruh** perubahan
 * dibatalkan dan galat dikembalikan per objek. Benda baru memakai id
 * sementara (mis. `b3`) yang dipetakan ke id sungguhan selama penerapan;
 * level rak baru dirujuk sebagai `{idSementaraRak}#{kodeLevel}`.
 */
class ApplyLayoutChanges
{
    /** Batas operasi per simpan — menjaga satu request tetap wajar. */
    public const MAKS_OPERASI = 500;

    /** @var array<string, int> id sementara → id sungguhan, per jenis (`zona:b1`) */
    private array $peta = [];

    public function __construct(
        private readonly SaveWarehouseLayout $denah,
        private readonly SaveFloorPlanObject $objek,
        private readonly SaveLocation $lokasi,
        private readonly GenerateBins $bin,
        private readonly DeactivateLocation $nonaktif,
        private readonly MergeBins $gabung,
        private readonly DeleteBin $hapus,
        private readonly SaveBinShape $bentuk,
        private readonly ChangeBinStatus $statusBin,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $ops
     * @return array{ok: bool, galat: array<int, array{i: int, objek: string, pesan: string}>, jumlah: int}
     */
    public function handle(Warehouse $gudang, array $ops, ?User $actor = null): array
    {
        if (count($ops) > self::MAKS_OPERASI) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Terlalu banyak perubahan sekaligus (maks. '.self::MAKS_OPERASI.'). Simpan sebagian dulu.');
        }

        $this->peta = [];
        $galat = [];

        try {
            DB::transaction(function () use ($gudang, $ops, $actor, &$galat): void {
                foreach (array_values($ops) as $i => $op) {
                    $objek = $this->kunciObjek($op);

                    try {
                        DB::transaction(fn () => $this->terapkan($gudang, (array) $op, $actor));
                    } catch (WarehouseRuleException $e) {
                        $galat[] = ['i' => $i, 'objek' => $objek, 'pesan' => $e->fieldErrors !== [] ? implode(' ', $e->fieldErrors) : $e->getMessage()];
                    } catch (ModelNotFoundException) {
                        $galat[] = ['i' => $i, 'objek' => $objek, 'pesan' => __('Benda ini tidak ditemukan atau bergantung pada perubahan lain yang gagal.')];
                    }
                }

                if ($galat !== []) {
                    throw new RuntimeException('batal');
                }
            });
        } catch (RuntimeException $e) {
            if ($galat === []) {
                throw $e;
            }

            return ['ok' => false, 'galat' => $galat, 'jumlah' => count($ops)];
        }

        activity('warehouse')->performedOn($gudang)->causedBy($actor)
            ->withProperties(['operasi' => count($ops)])->log('Perubahan denah disimpan');

        return ['ok' => true, 'galat' => [], 'jumlah' => count($ops)];
    }

    /** @param  array<string, mixed>  $op */
    private function terapkan(Warehouse $gudang, array $op, ?User $actor): void
    {
        $data = (array) ($op['data'] ?? []);

        match ((string) ($op['op'] ?? '')) {
            'gedung' => $this->denah->building($gudang, $data, $actor),
            'geser' => $this->geser($gudang, $op, $actor),
            'ukuran' => match ($op['jenis'] ?? '') {
                'rak' => $this->denah->resizeRack($this->rak($gudang, $op['id']), $op['p'] ?? null, $op['l'] ?? null, $actor),
                'zona' => $this->denah->resizeZone($this->zona($gudang, $op['id']), $op['p'] ?? null, $op['l'] ?? null, $actor),
                'obj' => $this->objek->resize($this->objekDenah($gudang, $op['id']), $op['p'] ?? null, $op['l'] ?? null, $actor),
                default => $this->tidakDikenal(),
            },
            'putar' => match ($op['jenis'] ?? '') {
                'rak' => $this->denah->rotateRack($this->rak($gudang, $op['id']), $actor),
                'obj' => $this->objek->rotate($this->objekDenah($gudang, $op['id']), $actor),
                default => $this->tidakDikenal(),
            },
            'zona' => $this->denah->zone($this->zona($gudang, $op['id']), $data, $actor),
            'rak' => $this->denah->rack($this->rak($gudang, $op['id']), $data, $actor),
            'tinggi_level' => $this->denah->level($this->level($gudang, $op['id']), $data['height_m'] ?? null, $actor),
            'objek' => $this->objek->update($this->objekDenah($gudang, $op['id']), $data, $actor),
            'objek_baru' => $this->catat('obj', $op, $this->objek->create($gudang, (string) ($op['jenis'] ?? ''), $data, $actor)->id),
            'zona_baru' => $this->catat('zona', $op, $this->lokasi->saveZone($gudang, null, $data, $actor)->id),
            'rak_baru' => $this->rakBaru($gudang, $op, $data, $actor),
            'level_baru' => $this->levelBaru($gudang, $op, $data, $actor),
            'bin_baru' => $this->binBaru($gudang, $op, $actor),
            'area_baru' => $this->areaBaru($gudang, $op, $data, $actor),
            'gabung' => $this->gabung->merge($this->binGudang($gudang, $op['utama'] ?? null), array_map(fn ($id) => (int) $this->binGudang($gudang, $id)->id, (array) ($op['bins'] ?? [])), $op['arah'] ?? null, $op['sifat'] ?? null, (string) ($op['alasan'] ?? ''), $actor),
            'pisah' => $this->gabung->split($this->binGudang($gudang, $op['bin'] ?? null), (string) ($op['alasan'] ?? ''), $actor),
            'hapus_bin' => $this->hapus->handle($this->binGudang($gudang, $op['bin'] ?? null), $actor),
            'lebar_bin' => $this->bentuk->width($this->binGudang($gudang, $op['bin'] ?? null), $op['lebar'] ?? null, $actor),
            'kapasitas_area' => $this->bentuk->areaCapacity($this->rak($gudang, $op['id']), $data, $actor),
            'nonaktif' => match ($op['jenis'] ?? '') {
                'rak' => $this->nonaktif->rack($this->rak($gudang, $op['id']), (string) ($op['alasan'] ?? ''), $actor),
                'zona' => $this->nonaktif->zone($this->zona($gudang, $op['id']), (string) ($op['alasan'] ?? ''), $actor),
                'obj' => $this->objek->deactivate($this->objekDenah($gudang, $op['id']), $actor),
                'bin' => $this->statusBin->deactivate($this->binGudang($gudang, $op['id']), (string) ($op['alasan'] ?? ''), null, $actor),
                default => $this->tidakDikenal(),
            },
            default => $this->tidakDikenal(),
        };
    }

    /** @param  array<string, mixed>  $op */
    private function geser(Warehouse $gudang, array $op, ?User $actor): void
    {
        $grid = ! empty($op['halus']) ? SaveWarehouseLayout::GRID_HALUS : SaveWarehouseLayout::GRID;

        match ($op['jenis'] ?? '') {
            'rak' => $this->denah->moveRack($this->rak($gudang, $op['id']), $op['x'] ?? null, $op['y'] ?? null, $actor, $grid),
            'zona' => $this->denah->moveZone($this->zona($gudang, $op['id']), $op['x'] ?? null, $op['y'] ?? null, $actor, $grid),
            'obj' => $this->objek->move($this->objekDenah($gudang, $op['id']), $op['x'] ?? null, $op['y'] ?? null, $actor, $grid),
            default => $this->tidakDikenal(),
        };
    }

    /**
     * @param  array<string, mixed>  $op
     * @param  array<string, mixed>  $data
     */
    private function rakBaru(Warehouse $gudang, array $op, array $data, ?User $actor): void
    {
        $rak = $this->denah->newRack($this->zona($gudang, $op['zona'] ?? null), $data, $actor);
        $this->catat('rak', $op, $rak->id);

        foreach ($rak->levels()->get() as $level) {
            $this->peta['level:'.$op['tmp'].'#'.$level->code] = (int) $level->id;
        }
    }

    /**
     * @param  array<string, mixed>  $op
     * @param  array<string, mixed>  $data
     */
    private function levelBaru(Warehouse $gudang, array $op, array $data, ?User $actor): void
    {
        $level = $this->denah->newLevel($this->rak($gudang, $op['rak'] ?? null), $data, $actor);
        $this->catat('level', $op, $level->id);
    }

    /** @param  array<string, mixed>  $op */
    private function binBaru(Warehouse $gudang, array $op, ?User $actor): void
    {
        $level = $this->level($gudang, $op['level'] ?? null);
        $jumlah = trim((string) ($op['jumlah'] ?? ''));

        if (! ctype_digit($jumlah) || (int) $jumlah < 1 || (int) $jumlah > SaveWarehouseLayout::MAKS_BIN_PER_LEVEL) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Jumlah bin 1–'.SaveWarehouseLayout::MAKS_BIN_PER_LEVEL.'.');
        }

        if ($level->rack?->is_area) {
            throw WarehouseRuleException::rule('BR-WH-06', 'Area lantai hanya punya satu bin (A-255).');
        }

        $this->bin->handle($level, (int) $jumlah, ['prefix' => 'B'], $actor);
    }

    /**
     * @param  array<string, mixed>  $op
     * @param  array<string, mixed>  $data
     */
    private function areaBaru(Warehouse $gudang, array $op, array $data, ?User $actor): void
    {
        $bin = $this->denah->areaRack($this->zona($gudang, $op['zona'] ?? null), $data, $actor);
        $this->catat('rak', $op, (int) $bin->rackLevel()->value('rack_id'));
    }

    /** @param  array<string, mixed>  $op */
    private function catat(string $jenis, array $op, int $id): void
    {
        if (isset($op['tmp']) && $op['tmp'] !== '') {
            $this->peta[$jenis.':'.$op['tmp']] = $id;
        }
    }

    /** Id sungguhan dari id biasa atau id sementara benda yang dibuat lebih dulu. */
    private function id(string $jenis, mixed $id): int
    {
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            return (int) $id;
        }

        return $this->peta[$jenis.':'.(string) $id] ?? throw new ModelNotFoundException;
    }

    private function zona(Warehouse $gudang, mixed $id): Zone
    {
        return Zone::query()->where('warehouse_id', $gudang->id)->findOrFail($this->id('zona', $id));
    }

    private function rak(Warehouse $gudang, mixed $id): Rack
    {
        return Rack::query()->whereHas('zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->findOrFail($this->id('rak', $id));
    }

    private function level(Warehouse $gudang, mixed $id): RackLevel
    {
        return RackLevel::query()->whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $gudang->id))->findOrFail($this->id('level', $id));
    }

    /** Bin milik gudang ini; hanya id sungguhan (bin baru disimpan dulu sebelum digabung/dihapus). */
    private function binGudang(Warehouse $gudang, mixed $id): Bin
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new ModelNotFoundException;
        }

        return Bin::query()->withoutGlobalScopes()->where('warehouse_id', $gudang->id)->findOrFail((int) $id);
    }

    private function objekDenah(Warehouse $gudang, mixed $id): FloorPlanObject
    {
        return FloorPlanObject::query()->where('warehouse_id', $gudang->id)->findOrFail($this->id('obj', $id));
    }

    /** @param  array<string, mixed>  $op */
    private function kunciObjek(array $op): string
    {
        return match ($op['op'] ?? '') {
            'gedung' => 'gedung',
            'zona', 'zona_baru' => 'zona:'.($op['id'] ?? $op['tmp'] ?? ''),
            'rak', 'rak_baru', 'area_baru' => 'rak:'.($op['id'] ?? $op['tmp'] ?? ''),
            'level_baru' => 'rak:'.($op['rak'] ?? ''),
            'bin_baru', 'tinggi_level' => 'level:'.($op['level'] ?? $op['id'] ?? ''),
            'gabung' => 'bin:'.($op['utama'] ?? ''),
            'pisah', 'hapus_bin', 'lebar_bin' => 'bin:'.($op['bin'] ?? ''),
            'kapasitas_area' => 'rak:'.($op['id'] ?? ''),
            'objek', 'objek_baru' => 'obj:'.($op['id'] ?? $op['tmp'] ?? ''),
            default => ($op['jenis'] ?? '?').':'.($op['id'] ?? ''),
        };
    }

    private function tidakDikenal(): never
    {
        throw WarehouseRuleException::rule('BR-GEN-11', 'Perubahan denah tidak dikenal.');
    }
}
