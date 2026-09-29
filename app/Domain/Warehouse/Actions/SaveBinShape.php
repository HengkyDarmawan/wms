<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Models\Rack;

/**
 * Permission: `bin.manage` — bentuk bin di denah (K-D, K-E).
 *
 * - **Lebar bin** (A-363): opsional, meter; kosong = rata bagi lebar rak.
 * - **Kapasitas area lantai** (A-364): jumlah, berat, dan volume bebas diisi;
 *   kosong = tanpa batas. Kapasitas tetap bermode blokir (A-255).
 */
class SaveBinShape
{
    public function width(Bin $bin, mixed $lebar, ?User $actor = null): Bin
    {
        if ($bin->rack_level_id === null || $bin->rackLevel?->rack?->is_area) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Lebar hanya untuk bin di rak.');
        }

        $nilai = $this->angka($lebar, 'width_m', 'Lebar bin', 99);
        $lama = $bin->width_m;
        $bin->disableLogging()->forceFill(['width_m' => $nilai])->save();

        activity('warehouse')->performedOn($bin)->causedBy($actor)
            ->withProperties(['lama' => $lama, 'baru' => $nilai])
            ->log('Lebar bin '.$bin->code.' '.($nilai === null ? 'dikosongkan (rata bagi)' : 'diubah menjadi '.rtrim(rtrim(number_format($nilai, 2, ',', ''), '0'), ',').' m'));

        return $bin->refresh();
    }

    /** @param  array<string, mixed>  $data  capacity_qty, capacity_weight, capacity_volume */
    public function areaCapacity(Rack $rack, array $data, ?User $actor = null): Bin
    {
        if (! $rack->is_area) {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Kapasitas bebas hanya untuk area lantai.');
        }

        $bin = Bin::query()->withoutGlobalScopes()->whereIn('rack_level_id', $rack->levels()->select('id'))->orderBy('id')->firstOrFail();
        $nilai = [
            'capacity_qty' => $this->angka($data['capacity_qty'] ?? null, 'capacity_qty', 'Kapasitas jumlah'),
            'capacity_weight' => $this->angka($data['capacity_weight'] ?? null, 'capacity_weight', 'Kapasitas berat'),
            'capacity_volume' => $this->angka($data['capacity_volume'] ?? null, 'capacity_volume', 'Kapasitas volume'),
        ];

        $bin->disableLogging()->forceFill($nilai)->save();

        activity('warehouse')->performedOn($bin)->causedBy($actor)
            ->withProperties($nilai)->log('Kapasitas area lantai '.$rack->code.' diubah');

        return $bin->refresh();
    }

    private function angka(mixed $nilai, string $kolom, string $label, float $maks = 99999999): ?float
    {
        $teks = trim(str_replace(',', '.', (string) ($nilai ?? '')));

        if ($teks === '') {
            return null;
        }

        if (! is_numeric($teks) || (float) $teks <= 0 || (float) $teks > $maks) {
            throw WarehouseRuleException::fields([$kolom => $label.' harus angka lebih dari 0, atau kosong.'], 'BR-GEN-11');
        }

        return round((float) $teks, $kolom === 'width_m' ? 2 : 4);
    }
}
