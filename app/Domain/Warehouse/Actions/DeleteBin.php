<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Enums\BinType;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Warehouse\Models\Bin;
use App\Domain\Warehouse\Support\BinUsage;

/**
 * Permission: `bin.manage` — **hapus bin yang belum pernah dipakai** (K-C, A-362).
 *
 * Bin salah ketik atau kelebihan saat menyusun rak boleh dihapus selama
 * belum dirujuk data mana pun ({@see BinUsage}). Bin yang pernah dipakai
 * tetap hanya bisa dinonaktifkan (P-03, BR-WH-07). Bin sistem, bin virtual,
 * bin area lantai, bin beku, dan bin dalam gabungan tidak dihapus.
 */
class DeleteBin
{
    public function __construct(private readonly BinUsage $pemakaian) {}

    public function handle(Bin $bin, ?User $actor = null): void
    {
        $alasan = $this->alasanTolak($bin);

        if ($alasan !== null) {
            throw WarehouseRuleException::rule('BR-WH-09', $alasan);
        }

        $level = $bin->rackLevel;
        $kode = (string) $bin->code;

        $bin->disableLogging()->delete();

        activity('warehouse')->performedOn($level ?? $bin->warehouse)->causedBy($actor)
            ->withProperties(['bin' => $kode, 'warehouse_id' => $bin->warehouse_id])
            ->log('Bin '.$kode.' dihapus (belum pernah dipakai)');
    }

    /** Alasan bin ini tidak boleh dihapus, atau null bila boleh. */
    public function alasanTolak(Bin $bin, ?bool $dipakai = null): ?string
    {
        if ($bin->isSystemBin() || $bin->bin_type !== BinType::Storage || $bin->rack_level_id === null) {
            return 'Bin '.$bin->code.' adalah bin sistem dan tidak bisa dihapus.';
        }

        if ($bin->rackLevel?->rack?->is_area) {
            return 'Bin area lantai ikut rak areanya; nonaktifkan areanya bila tidak dipakai.';
        }

        if ($bin->bin_status === BinStatus::Frozen) {
            return 'Bin '.$bin->code.' sedang dibekukan opname.';
        }

        if ($bin->isMerged()) {
            return 'Bin '.$bin->code.' sedang digabung. Pisah dulu.';
        }

        $dipakaiDi = $dipakai === false ? null : $this->pemakaian->dipakaiDi($bin);

        if ($dipakaiDi !== null) {
            return 'Bin '.$bin->code.' pernah dipakai ('.$dipakaiDi.'), jadi hanya bisa dinonaktifkan.';
        }

        return null;
    }
}
