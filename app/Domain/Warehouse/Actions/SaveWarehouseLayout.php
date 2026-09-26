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

    public function __construct(
        private readonly SaveLocation $lokasi,
        private readonly SaveBin $bin,
    ) {}

    /** @param  array<string, mixed>  $data  length_m, width_m */
    public function zone(Zone $zone, array $data, ?User $actor = null): Zone
    {
        $zone->fill([
            'length_m' => $this->ukuran($data['length_m'] ?? null, 'length_m'),
            'width_m' => $this->ukuran($data['width_m'] ?? null, 'width_m'),
        ])->save();

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
