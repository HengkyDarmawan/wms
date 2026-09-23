<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Warehouse\Enums\BinStatus;
use App\Domain\Warehouse\Exceptions\WarehouseRuleException;
use App\Domain\Stock\Support\StockGuard;
use App\Domain\Warehouse\Models\Bin;

/**
 * Permission: `bin.manage` — membekukan, mencairkan, dan menonaktifkan bin
 * (12-warehouse §4). Transisi lewat POST, tidak pernah lewat GET.
 *
 * Menonaktifkan bin menuntut saldo dan reservasinya nol (BR-GEN-04), diperiksa
 * lewat {@see \App\Domain\Stock\Support\StockGuard}.
 */
class ChangeBinStatus
{
    public function __construct(private readonly StockGuard $stock) {}

    /** BR-OPN-02: bin beku menolak PCK, PUT, SJ, dan ISU baru. */
    public function freeze(Bin $bin, string $reasonCode, ?int $countId = null, ?User $actor = null): Bin
    {
        if (trim($reasonCode) === '') {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        if ($bin->bin_status === BinStatus::Inactive) {
            throw WarehouseRuleException::rule('BR-OPN-02', 'Bin nonaktif tidak perlu dibekukan.');
        }

        $bin->forceFill([
            'bin_status' => BinStatus::Frozen,
            'frozen_by_count_id' => $countId,
            'freeze_reason' => $reasonCode,
        ])->save();

        activity('warehouse')
            ->performedOn($bin)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'count_id' => $countId])
            ->log('Bin dibekukan');

        return $bin->refresh();
    }

    public function unfreeze(Bin $bin, ?User $actor = null): Bin
    {
        if ($bin->bin_status !== BinStatus::Frozen) {
            throw WarehouseRuleException::rule('BR-OPN-02', 'Bin ini tidak sedang dibeku.');
        }

        $bin->forceFill([
            'bin_status' => BinStatus::Active,
            'frozen_by_count_id' => null,
            'freeze_reason' => null,
        ])->save();

        activity('warehouse')->performedOn($bin)->causedBy($actor)->log('Bin dicairkan');

        return $bin->refresh();
    }

    /** P-03: bin tidak dihapus. BR-WH-02: bin sistem dan virtual tidak bisa dimatikan. */
    public function deactivate(Bin $bin, string $reasonCode, ?string $notes = null, ?User $actor = null): Bin
    {
        if (trim($reasonCode) === '') {
            throw WarehouseRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        if ($bin->isSystemBin()) {
            throw WarehouseRuleException::rule(
                'BR-WH-02',
                'Bin '.$bin->bin_type->label().' melekat pada gudang dan tidak bisa dinonaktifkan sendiri.',
            );
        }

        // BR-GEN-04: bin berisi tidak boleh dimatikan.
        $tolak = $this->stock->refuseBin((int) $bin->id);

        if ($tolak !== null) {
            throw WarehouseRuleException::rule('BR-GEN-04', $tolak);
        }

        $bin->forceFill(['bin_status' => BinStatus::Inactive])->save();

        activity('warehouse')
            ->performedOn($bin)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Bin dinonaktifkan');

        return $bin->refresh();
    }

    public function reactivate(Bin $bin, ?User $actor = null): Bin
    {
        $bin->forceFill(['bin_status' => BinStatus::Active])->save();

        activity('warehouse')->performedOn($bin)->causedBy($actor)->log('Bin diaktifkan kembali');

        return $bin->refresh();
    }

    /** A-67 dan BR-SJ-02: menandai bin agar didahulukan sesi opname berikutnya. */
    public function flagForCount(Bin $bin, bool $flag = true, ?User $actor = null): Bin
    {
        $bin->forceFill(['count_flag' => $flag])->save();

        activity('warehouse')
            ->performedOn($bin)
            ->causedBy($actor)
            ->log($flag ? 'Bin ditandai perlu dihitung' : 'Penanda hitung bin dilepas');

        return $bin->refresh();
    }
}
