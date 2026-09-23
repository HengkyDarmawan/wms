<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Stock\Support\StockGuard;
use App\Domain\Warehouse\Models\Warehouse;

/**
 * Permission: `warehouse.deactivate`.
 *
 * P-03: gudang tidak pernah dihapus. BR-WH-07: penonaktifan ditolak bila masih
 * ada bin penyimpanan aktif atau gudang anak yang aktif; BR-GEN-04 menambahkan
 * syarat saldo dan reservasi nol, diperiksa lewat StockGuard.
 */
class DeactivateWarehouse
{
    public function __construct(private readonly StockGuard $stock) {}

    public function handle(Warehouse $warehouse, string $reasonCode, ?string $notes = null, ?User $actor = null): Warehouse
    {
        if (trim($reasonCode) === '') {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        $anak = $warehouse->children()->where('is_active', true)->count();

        if ($anak > 0) {
            throw WarehouseRuleException::rule(
                'BR-WH-07',
                'Gudang ini masih punya '.$anak.' gudang anak yang aktif.',
            );
        }

        // Bin sistem dan bin virtual ikut mati bersama gudangnya, jadi yang
        // dihitung hanya bin penyimpanan yang dibuat manusia.
        $bin = $warehouse->bins()
            ->where('bin_status', BinStatus::Active->value)
            ->where('bin_type', BinType::Storage->value)
            ->count();

        if ($bin > 0) {
            throw WarehouseRuleException::rule(
                'BR-WH-07',
                'Gudang ini masih punya '.$bin.' bin penyimpanan aktif. Nonaktifkan binnya dulu.',
            );
        }

        // BR-GEN-04: saldo dan reservasi harus nol sebelum gudang dimatikan.
        $tolak = $this->stock->refuseWarehouse((int) $warehouse->id);

        if ($tolak !== null) {
            throw WarehouseRuleException::rule('BR-GEN-04', $tolak);
        }

        $warehouse->forceFill(['is_active' => false])->save();

        // Bin milik gudang nonaktif ikut berhenti menerima dokumen baru.
        $warehouse->bins()->update(['bin_status' => BinStatus::Inactive->value]);

        activity('warehouse')
            ->performedOn($warehouse)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Gudang dinonaktifkan');

        return $warehouse->refresh();
    }

    public function reactivate(Warehouse $warehouse, ?User $actor = null): Warehouse
    {
        $warehouse->forceFill(['is_active' => true])->save();

        // Bin sistem dihidupkan kembali; bin penyimpanan dibiarkan agar
        // penonaktifan yang disengaja tidak ikut terbalik.
        $warehouse->bins()
            ->where('bin_status', BinStatus::Inactive->value)
            ->whereIn('bin_type', [
                BinType::Receiving->value, BinType::Staging->value,
                BinType::Quarantine->value, BinType::Return->value,
                BinType::Waste->value, BinType::InTransit->value,
            ])
            ->update(['bin_status' => BinStatus::Active->value]);

        activity('warehouse')
            ->performedOn($warehouse)
            ->causedBy($actor)
            ->log('Gudang diaktifkan kembali');

        return $warehouse->refresh();
    }
}
