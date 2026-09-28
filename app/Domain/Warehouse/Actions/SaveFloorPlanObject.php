<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\FloorPlanObjectType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\FloorPlanObject;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Permission: `bin.manage` — objek denah gedung (A-320): pintu, dock, jalur
 * forklift, pilar, kantor, area bebas.
 *
 * Objek tidak punya stok dan tidak menyentuh kartu stok (P-01); posisi relatif
 * sudut kiri-atas gedung, dibulatkan 0,5 m (halus 0,1 m) dan dijaga di dalam
 * gedung bila ukurannya diisi. Tidak dihapus — dinonaktifkan (P-03).
 */
class SaveFloorPlanObject
{
    /** Tambah objek dengan ukuran bawaan jenisnya; posisi kosong = dekat sudut kiri-atas. */
    public function create(Warehouse $gudang, string $jenis, array $data = [], ?User $actor = null): FloorPlanObject
    {
        $tipe = FloorPlanObjectType::tryFrom($jenis)
            ?? throw WarehouseRuleException::fields(['object_type' => 'Jenis objek denah tidak dikenal.'], 'BR-GEN-11');

        [$p, $l] = $tipe->defaultSize();
        $nama = trim((string) ($data['name'] ?? '')) ?: $tipe->label();

        $objek = new FloorPlanObject([
            'warehouse_id' => $gudang->id,
            'object_type' => $tipe,
            'name' => mb_substr($nama, 0, 60),
            'length_m' => $p,
            'width_m' => $l,
            'rotation' => 0,
            'is_active' => true,
        ]);
        [$x, $y] = $this->posisi($objek, $gudang, $data['pos_x'] ?? 0.5, $data['pos_y'] ?? 0.5, SaveWarehouseLayout::GRID);
        $objek->fill(['pos_x' => $x, 'pos_y' => $y])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)
            ->withProperties(['gudang' => $gudang->code, 'jenis' => $tipe->value])->log('Objek denah ditambahkan');

        return $objek->refresh();
    }

    /** @param  array<string, mixed>  $data  object_type, name, pos_x, pos_y, length_m, width_m */
    public function update(FloorPlanObject $objek, array $data, ?User $actor = null): FloorPlanObject
    {
        $gudang = $this->gudang($objek);
        $tipe = FloorPlanObjectType::tryFrom((string) ($data['object_type'] ?? $objek->object_type->value))
            ?? throw WarehouseRuleException::fields(['object_type' => 'Jenis objek denah tidak dikenal.'], 'BR-GEN-11');
        $nama = trim((string) ($data['name'] ?? ''));

        if ($nama === '') {
            throw WarehouseRuleException::fields(['name' => 'Nama objek wajib diisi.'], 'BR-GEN-11');
        }

        $objek->fill([
            'object_type' => $tipe,
            'name' => mb_substr($nama, 0, 60),
            'length_m' => $this->ukuran($data['length_m'] ?? $objek->length_m, 'length_m'),
            'width_m' => $this->ukuran($data['width_m'] ?? $objek->width_m, 'width_m'),
        ]);
        [$x, $y] = $this->posisi($objek, $gudang, $data['pos_x'] ?? $objek->pos_x, $data['pos_y'] ?? $objek->pos_y, SaveWarehouseLayout::GRID);
        $objek->fill(['pos_x' => $x, 'pos_y' => $y])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)->log('Objek denah diubah');

        return $objek->refresh();
    }

    public function move(FloorPlanObject $objek, mixed $x, mixed $y, ?User $actor = null, float $grid = SaveWarehouseLayout::GRID): FloorPlanObject
    {
        [$px, $py] = $this->posisi($objek, $this->gudang($objek), $x, $y, $grid);
        $objek->forceFill(['pos_x' => $px, 'pos_y' => $py])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)
            ->withProperties(['x' => $px, 'y' => $py])->log('Objek denah digeser');

        return $objek->refresh();
    }

    /** Ukuran tampak atas yang ditarik di denah; objek terputar 90°/270° menyimpan panjang & lebar tertukar. */
    public function resize(FloorPlanObject $objek, mixed $tampakP, mixed $tampakL, ?User $actor = null): FloorPlanObject
    {
        $p = max(SaveWarehouseLayout::GRID_HALUS, $this->ukuran($tampakP, 'length_m'));
        $l = max(SaveWarehouseLayout::GRID_HALUS, $this->ukuran($tampakL, 'width_m'));

        if ($objek->rotation % 180 === 90) {
            [$p, $l] = [$l, $p];
        }

        $objek->forceFill(['length_m' => $p, 'width_m' => $l])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)->log('Ukuran objek denah diubah');

        return $objek->refresh();
    }

    /** A-322: putar 90° searah jarum jam; posisi dijepit ulang di dalam gedung. */
    public function rotate(FloorPlanObject $objek, ?User $actor = null): FloorPlanObject
    {
        $objek->forceFill(['rotation' => ($objek->rotation + 90) % 360])->save();
        [$x, $y] = $this->posisi($objek, $this->gudang($objek), $objek->pos_x, $objek->pos_y, SaveWarehouseLayout::GRID_HALUS);
        $objek->forceFill(['pos_x' => $x, 'pos_y' => $y])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)
            ->withProperties(['rotasi' => $objek->rotation])->log('Objek denah diputar');

        return $objek->refresh();
    }

    /** P-03: objek tidak dihapus; nonaktif = tidak digambar. */
    public function deactivate(FloorPlanObject $objek, ?User $actor = null): FloorPlanObject
    {
        $objek->forceFill(['is_active' => false])->save();

        activity('warehouse')->performedOn($objek)->causedBy($actor)->log('Objek denah dinonaktifkan');

        return $objek->refresh();
    }

    private function gudang(FloorPlanObject $objek): Warehouse
    {
        return Warehouse::query()->withoutGlobalScopes()->findOrFail($objek->warehouse_id);
    }

    /** @return array{0: float, 1: float} */
    private function posisi(FloorPlanObject $objek, Warehouse $gudang, mixed $x, mixed $y, float $grid): array
    {
        $px = round(round($this->ukuran($x, 'pos_x', true) / $grid) * $grid, 2);
        $py = round(round($this->ukuran($y, 'pos_y', true) / $grid) * $grid, 2);
        [$p, $l] = $objek->footprint();

        if ($gudang->length_m !== null) {
            $px = min($px, max(0, (float) $gudang->length_m - $p));
        }

        if ($gudang->width_m !== null) {
            $py = min($py, max(0, (float) $gudang->width_m - $l));
        }

        return [round(max(0, $px), 2), round(max(0, $py), 2)];
    }

    private function ukuran(mixed $nilai, string $kolom, bool $bolehNol = false): float
    {
        $teks = trim(str_replace(',', '.', (string) ($nilai ?? '')));

        if ($teks === '' || ! is_numeric($teks) || (float) $teks < 0 || (! $bolehNol && (float) $teks == 0.0) || (float) $teks > 9999) {
            throw WarehouseRuleException::fields([$kolom => 'Ukuran dalam meter harus angka '.($bolehNol ? '≥ 0' : '> 0').'.'], 'BR-GEN-11');
        }

        return round((float) $teks, 2);
    }
}
