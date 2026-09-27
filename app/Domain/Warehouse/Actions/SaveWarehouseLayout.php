<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\CapacityMode;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `bin.manage` — perencanaan denah gudang (A-254, A-255).
 *
 * Semua ukuran & posisi **opsional** dan dalam meter: kosong = denah menata
 * otomatis. Posisi rak dibulatkan ke kelipatan 0,5 m (geser di grid) dan
 * dijaga tetap di dalam zona bila ukuran zona diisi. Kode zona/rak/level/bin
 * tidak berubah (BR-WH-01) — yang diatur hanya ukuran, posisi, dan nama.
 */
class SaveWarehouseLayout
{
    public const GRID = 0.5;

    /** Batas susun dari denah: level per rak dan bin per level (A-271). */
    public const MAKS_LEVEL = 20;

    public const MAKS_BIN_PER_LEVEL = 50;

    public function __construct(
        private readonly SaveLocation $lokasi,
        private readonly SaveBin $bin,
        private readonly GenerateBins $binMassal,
    ) {}

    /** @param  array<string, mixed>  $data  length_m, width_m, name (opsional) */
    public function zone(Zone $zone, array $data, ?User $actor = null): Zone
    {
        $zone->fill([
            'length_m' => $this->ukuran($data['length_m'] ?? null, 'length_m'),
            'width_m' => $this->ukuran($data['width_m'] ?? null, 'width_m'),
        ]);

        // A-271: nama zona boleh diubah dari denah; kodenya tetap (BR-WH-01).
        if (array_key_exists('name', $data)) {
            $nama = trim((string) $data['name']);

            if ($nama === '') {
                throw WarehouseRuleException::fields(['name' => 'Nama zona wajib diisi.'], 'BR-GEN-11');
            }

            $zone->fill(['name' => mb_substr($nama, 0, 60)]);
        }

        $zone->save();

        activity('warehouse')->performedOn($zone)->causedBy($actor)->log('Ukuran zona diubah');

        return $zone->refresh();
    }

    /** @param  array<string, mixed>  $data  name, length_m, width_m, height_m, orientation, pos_x, pos_y */
    public function rack(Rack $rack, array $data, ?User $actor = null): Rack
    {
        $orientasi = in_array($data['orientation'] ?? null, ['h', 'v'], true) ? $data['orientation'] : null;
        $nama = trim((string) ($data['name'] ?? ''));

        $rack->fill([
            'name' => $nama === '' ? null : mb_substr($nama, 0, 60),
            'length_m' => $this->ukuran($data['length_m'] ?? null, 'length_m'),
            'width_m' => $this->ukuran($data['width_m'] ?? null, 'width_m'),
            'height_m' => $this->ukuran($data['height_m'] ?? null, 'height_m'),
            'orientation' => $orientasi,
        ]);

        if (array_key_exists('pos_x', $data) || array_key_exists('pos_y', $data)) {
            [$x, $y] = $this->posisi($rack, $data['pos_x'] ?? null, $data['pos_y'] ?? null);
            $rack->fill(['pos_x' => $x, 'pos_y' => $y]);
        }

        $rack->save();

        activity('warehouse')->performedOn($rack)->causedBy($actor)->log('Denah rak diubah');

        return $rack->refresh();
    }

    /** Geser rak di grid denah (snap 0,5 m). */
    public function moveRack(Rack $rack, mixed $x, mixed $y, ?User $actor = null): Rack
    {
        [$px, $py] = $this->posisi($rack, $x, $y);
        $rack->forceFill(['pos_x' => $px, 'pos_y' => $py])->save();

        activity('warehouse')->performedOn($rack)->causedBy($actor)
            ->withProperties(['x' => $px, 'y' => $py])->log('Rak digeser di denah');

        return $rack->refresh();
    }

    public function level(RackLevel $level, mixed $tinggi, ?User $actor = null): RackLevel
    {
        $level->forceFill(['height_m' => $this->ukuran($tinggi, 'height_m')])->save();

        activity('warehouse')->performedOn($level)->causedBy($actor)->log('Tinggi level diubah');

        return $level->refresh();
    }

    /**
     * Rak baru langsung dari denah (A-271): rak + level `L1`…`Ln` + bin
     * opsional per level dalam satu transaksi. Kode rak/level lewat
     * {@see SaveLocation} dan bin lewat {@see GenerateBins}, jadi BR-WH-01 dan
     * jejak audit sama dengan tab *Zona & rak*. Posisi kosong = ditata otomatis.
     *
     * @param  array<string, mixed>  $data  code, name, levels, bins_per_level, prefix, capacity_qty
     */
    public function newRack(Zone $zone, array $data, ?User $actor = null): Rack
    {
        $this->zonaAktif($zone);
        $jumlahLevel = $this->bilangan($data['levels'] ?? 1, 'levels', 1, self::MAKS_LEVEL, 'Jumlah level');
        $binPerLevel = $this->bilangan($data['bins_per_level'] ?? 0, 'bins_per_level', 0, self::MAKS_BIN_PER_LEVEL, 'Bin per level');

        return DB::transaction(function () use ($zone, $data, $jumlahLevel, $binPerLevel, $actor): Rack {
            $rak = $this->lokasi->saveRack($zone, null, ['code' => $data['code'] ?? ''], $actor);
            $nama = trim((string) ($data['name'] ?? ''));

            if ($nama !== '') {
                $rak->forceFill(['name' => mb_substr($nama, 0, 60)])->save();
            }

            for ($i = 1; $i <= $jumlahLevel; $i++) {
                $level = $this->lokasi->saveLevel($rak, null, ['code' => 'L'.$i], $actor);
                $this->isiBin($level, $binPerLevel, $data, $actor);
            }

            activity('warehouse')->performedOn($rak)->causedBy($actor)
                ->withProperties(['level' => $jumlahLevel, 'bin_per_level' => $binPerLevel])
                ->log('Rak dibuat dari denah');

            return $rak->refresh();
        });
    }

    /**
     * Level baru di rak yang sudah ada (A-271). Kode kosong = `L` + nomor
     * berikutnya yang belum dipakai.
     *
     * @param  array<string, mixed>  $data  code, bins, prefix, capacity_qty
     */
    public function newLevel(Rack $rack, array $data, ?User $actor = null): RackLevel
    {
        if (! $rack->is_active) {
            throw WarehouseRuleException::rule('BR-WH-07', 'Rak '.$rack->code.' nonaktif.');
        }

        if ($rack->is_area) {
            throw WarehouseRuleException::rule('BR-WH-06', 'Rak area hanya punya satu level dan satu bin (A-255).');
        }

        $jumlahBin = $this->bilangan($data['bins'] ?? 0, 'bins', 0, self::MAKS_BIN_PER_LEVEL, 'Jumlah bin');
        $kode = trim((string) ($data['code'] ?? ''));

        if ($kode === '') {
            $n = $rack->levels()->count() + 1;

            while ($rack->levels()->where('code', 'L'.$n)->exists()) {
                $n++;
            }

            $kode = 'L'.$n;
        }

        return DB::transaction(function () use ($rack, $kode, $jumlahBin, $data, $actor): RackLevel {
            $level = $this->lokasi->saveLevel($rack, null, ['code' => $kode], $actor);
            $this->isiBin($level, $jumlahBin, $data, $actor);

            return $level->refresh();
        });
    }

    /**
     * Rak area untuk barang besar (A-255): satu rak berisi satu level dan
     * **satu bin** yang mewakili seluruh rak — atau seluruh zona bila
     * `seluruh_zona` (ukuran = ukuran zona, posisi 0,0). Bin berkapasitas
     * `capacity_qty` (bawaan 1 unit) bermode **blokir**: isi kedua ditolak.
     *
     * @param  array<string, mixed>  $data  code, name, capacity_qty, seluruh_zona, length_m, width_m
     */
    public function areaRack(Zone $zone, array $data, ?User $actor = null): Bin
    {
        $gudang = Warehouse::query()->withoutGlobalScopes()->findOrFail($zone->warehouse_id);
        $seluruhZona = filter_var($data['seluruh_zona'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $kapasitas = is_numeric($data['capacity_qty'] ?? null) && (float) $data['capacity_qty'] > 0 ? (float) $data['capacity_qty'] : 1.0;

        return DB::transaction(function () use ($zone, $gudang, $data, $seluruhZona, $kapasitas, $actor) {
            $rak = $this->lokasi->saveRack($zone, null, ['code' => $data['code'] ?? ''], $actor);
            $rak->forceFill([
                'name' => trim((string) ($data['name'] ?? '')) ?: ($seluruhZona ? __('Area zona :z', ['z' => $zone->code]) : __('Area')),
                'is_area' => true,
                'length_m' => $seluruhZona ? $zone->length_m : $this->ukuran($data['length_m'] ?? null, 'length_m'),
                'width_m' => $seluruhZona ? $zone->width_m : $this->ukuran($data['width_m'] ?? null, 'width_m'),
                'pos_x' => $seluruhZona ? 0 : null,
                'pos_y' => $seluruhZona ? 0 : null,
            ])->save();

            $level = $this->lokasi->saveLevel($rak, null, ['code' => 'L1'], $actor);

            $bin = $this->bin->handle($gudang, null, [
                'rack_level_id' => $level->id,
                'code' => 'AREA',
                'capacity_qty' => $kapasitas,
            ], $actor);

            $bin->forceFill(['capacity_mode' => CapacityMode::Block->value])->save();

            activity('warehouse')->performedOn($rak)->causedBy($actor)
                ->withProperties(['bin' => $bin->code, 'kapasitas' => $kapasitas, 'seluruh_zona' => $seluruhZona])
                ->log('Rak area dibuat untuk barang besar');

            return $bin->refresh();
        });
    }

    /** @param  array<string, mixed>  $data */
    private function isiBin(RackLevel $level, int $jumlah, array $data, ?User $actor): void
    {
        if ($jumlah < 1) {
            return;
        }

        $this->binMassal->handle($level, $jumlah, [
            'prefix' => $data['prefix'] ?? 'B',
            'capacity_qty' => $data['capacity_qty'] ?? null,
        ], $actor);
    }

    private function zonaAktif(Zone $zone): void
    {
        if (! $zone->is_active) {
            throw WarehouseRuleException::rule('BR-WH-07', 'Zona '.$zone->code.' nonaktif.');
        }
    }

    private function bilangan(mixed $nilai, string $kolom, int $min, int $maks, string $label): int
    {
        $teks = trim((string) $nilai);

        if ($teks === '' && $min === 0) {
            return 0;
        }

        if (! ctype_digit($teks) || (int) $teks < $min || (int) $teks > $maks) {
            throw WarehouseRuleException::fields([$kolom => $label.' harus bilangan bulat '.$min.'–'.$maks.'.'], 'BR-GEN-11');
        }

        return (int) $teks;
    }

    /** @return array{0: ?float, 1: ?float} */
    private function posisi(Rack $rack, mixed $x, mixed $y): array
    {
        $px = $this->ukuran($x, 'pos_x', true);
        $py = $this->ukuran($y, 'pos_y', true);

        if ($px === null || $py === null) {
            return [null, null];
        }

        $px = round($px / self::GRID) * self::GRID;
        $py = round($py / self::GRID) * self::GRID;

        $zona = $rack->zone;

        // Tetap di dalam zona bila ukurannya diketahui.
        if ($zona?->length_m !== null) {
            $px = min($px, max(0, (float) $zona->length_m - (float) ($rack->length_m ?? 0)));
        }

        if ($zona?->width_m !== null) {
            $py = min($py, max(0, (float) $zona->width_m - (float) ($rack->width_m ?? 0)));
        }

        return [round($px, 2), round($py, 2)];
    }

    private function ukuran(mixed $nilai, string $kolom, bool $bolehNol = false): ?float
    {
        $teks = trim(str_replace(',', '.', (string) ($nilai ?? '')));

        if ($teks === '') {
            return null;
        }

        if (! is_numeric($teks) || (float) $teks < 0 || (! $bolehNol && (float) $teks == 0.0) || (float) $teks > 9999) {
            throw WarehouseRuleException::fields([$kolom => 'Ukuran dalam meter harus angka '.($bolehNol ? '≥ 0' : '> 0').'.'], 'BR-GEN-11');
        }

        return round((float) $teks, 2);
    }
}
