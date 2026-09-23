<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Support\MasterCode;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\WarehouseType;

/**
 * Permission: `warehouse_type.manage`.
 *
 * Tipe bawaan (`main`, `branch`, `site`) boleh diubah namanya tetapi kodenya
 * dikunci, karena BR-WH-04 memakai kode `site` untuk menentukan gudang proyek.
 */
class SaveWarehouseType
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(?WarehouseType $type, array $attributes, ?User $actor = null): WarehouseType
    {
        $baru = $type === null || ! $type->exists;

        $nama = trim((string) ($attributes['name'] ?? ''));

        if ($nama === '') {
            throw WarehouseRuleException::fields(['name' => 'Nama tipe gudang wajib diisi.'], 'BR-GEN-11');
        }

        $kode = MasterCode::resolve($type, (string) ($attributes['code'] ?? ''), 'tipe gudang');

        $bentrok = WarehouseType::query()->where('code', $kode)
            ->when(! $baru, fn ($q) => $q->whereKeyNot($type->getKey()))
            ->exists();

        if ($bentrok) {
            throw WarehouseRuleException::fields(
                ['code' => 'Kode tipe gudang "'.$kode.'" sudah dipakai.'],
                'BR-MST-01',
            );
        }

        if ($baru) {
            $type = WarehouseType::create([
                'code' => $kode,
                'name' => $nama,
                'is_builtin' => false,
                'is_active' => true,
            ]);
        } else {
            $type->fill(['name' => $nama])->save();
        }

        activity('warehouse')
            ->performedOn($type)
            ->causedBy($actor)
            ->log($baru ? 'Tipe gudang dibuat' : 'Tipe gudang diubah');

        return $type->refresh();
    }

    /** P-03: tipe bawaan tidak bisa dinonaktifkan; tipe lain ditolak bila masih dipakai. */
    public function deactivate(WarehouseType $type, ?User $actor = null): WarehouseType
    {
        if ($type->is_builtin) {
            throw WarehouseRuleException::rule('BR-WH-04', 'Tipe gudang bawaan tidak bisa dinonaktifkan.');
        }

        $dipakai = $type->warehouses()->where('is_active', true)->count();

        if ($dipakai > 0) {
            throw WarehouseRuleException::rule(
                'BR-WH-07',
                'Tipe ini masih dipakai '.$dipakai.' gudang aktif.',
            );
        }

        $type->forceFill(['is_active' => false])->save();

        activity('warehouse')->performedOn($type)->causedBy($actor)->log('Tipe gudang dinonaktifkan');

        return $type->refresh();
    }

    public function reactivate(WarehouseType $type, ?User $actor = null): WarehouseType
    {
        $type->forceFill(['is_active' => true])->save();

        activity('warehouse')->performedOn($type)->causedBy($actor)->log('Tipe gudang diaktifkan kembali');

        return $type->refresh();
    }
}
