<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Rack;
use App\Domain\Warehouse\Models\RackLevel;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\Zone;
use App\Domain\Warehouse\Support\BinCodeBuilder;

/**
 * Permission: `bin.manage`.
 *
 * Tiga tingkat hierarki lokasi — zona, rak, level — dalam satu aksi karena
 * aturannya sama persis: kode dibakukan huruf besar, unik di dalam induknya,
 * dan terkunci setelah dibuat karena ikut membentuk kode bin (BR-WH-01).
 */
class SaveLocation
{
    /** @param  array<string, mixed>  $attributes */
    public function saveZone(Warehouse $warehouse, ?Zone $zone, array $attributes, ?User $actor = null): Zone
    {
        $baru = $zone === null || ! $zone->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw WarehouseRuleException::fields(['name' => 'Nama zona wajib diisi.'], 'BR-GEN-11');
        }

        $kode = $this->resolveCode($zone, $attributes, 'zona');

        $bentrok = Zone::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($zone->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode zona "'.$kode.'" sudah ada di gudang ini.'],
                'BR-WH-01',
            );
        }

        if ($baru) {
            $zone = Zone::create([
                'warehouse_id' => $warehouse->id,
                'code' => $kode,
                'name' => $nama,
                'is_active' => true,
            ]);
        } else {
            $zone->fill(['name' => $nama])->save();
        }

        $this->catat($zone, $baru, 'Zona', $actor);

        return $zone->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveRack(Zone $zone, ?Rack $rack, array $attributes, ?User $actor = null): Rack
    {
        $baru = $rack === null || ! $rack->exists;

        $kode = $this->resolveCode($rack, $attributes, 'rak');

        $bentrok = Rack::query()
            ->where('zone_id', $zone->id)
            ->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($rack->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode rak "'.$kode.'" sudah ada di zona ini.'],
                'BR-WH-01',
            );
        }

        if ($baru) {
            $rack = Rack::create(['zone_id' => $zone->id, 'code' => $kode, 'is_active' => true]);
        }

        $this->catat($rack, $baru, 'Rak', $actor);

        return $rack->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function saveLevel(Rack $rack, ?RackLevel $level, array $attributes, ?User $actor = null): RackLevel
    {
        $baru = $level === null || ! $level->exists;

        $kode = $this->resolveCode($level, $attributes, 'level');

        $bentrok = RackLevel::query()
            ->where('rack_id', $rack->id)
            ->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($level->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode level "'.$kode.'" sudah ada di rak ini.'],
                'BR-WH-01',
            );
        }

        if ($baru) {
            $level = RackLevel::create(['rack_id' => $rack->id, 'code' => $kode, 'is_active' => true]);
        }

        $this->catat($level, $baru, 'Level rak', $actor);

        return $level->refresh();
    }

    /**
     * BR-WH-01: kode segmen terkunci setelah dibuat, karena kode bin yang sudah
     * tercetak di label diturunkan darinya.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCode(?object $model, array $attributes, string $label): string
    {
        $kode = BinCodeBuilder::segment((string) ($attributes['code'] ?? ''));

        if ($model !== null && $model->exists) {
            $lama = (string) $model->code;

            if ($kode !== '' && $kode !== $lama) {
                throw WarehouseRuleException::fields(
                    ['code' => 'Kode '.$label.' tidak bisa diubah karena kode bin diturunkan darinya.'],
                    'BR-WH-01',
                );
            }

            return $lama;
        }

        if ($kode === '') {
            throw WarehouseRuleException::fields(['code' => 'Kode '.$label.' wajib diisi.'], 'BR-GEN-11');
        }

        return $kode;
    }

    private function catat(object $model, bool $baru, string $label, ?User $actor): void
    {
        activity('warehouse')
            ->performedOn($model)
            ->causedBy($actor)
            ->log($label.($baru ? ' dibuat' : ' diubah'));
    }
}
