<?php

declare(strict_types=1);

namespace App\Domain\Master\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ItemStatus;
use App\Domain\Master\Exceptions\MasterRuleException;
use App\Domain\Master\Models\Item;
use App\Domain\Stock\Support\StockGuard;

/**
 * Permission: `item.deactivate`.
 *
 * P-03: item tidak dihapus, hanya dinonaktifkan sehingga berhenti muncul di
 * pilihan dokumen baru. Sejak modul `stock` ada, item yang masih punya saldo
 * atau reservasi ditolak (BR-GEN-04).
 */
class DeactivateItem
{
    public function __construct(private readonly StockGuard $stock) {}

    public function handle(Item $item, string $reasonCode, ?string $notes = null, ?User $actor = null): Item
    {
        if (trim($reasonCode) === '') {
            throw MasterRuleException::rule('BR-GEN-11', 'Alasan wajib dipilih.');
        }

        // BR-GEN-04: item dengan saldo atau reservasi tidak bisa dimatikan.
        $tolak = $this->stock->refuseItem((int) $item->id);

        if ($tolak !== null) {
            throw MasterRuleException::rule('BR-GEN-04', $tolak);
        }

        $item->forceFill(['status' => ItemStatus::Inactive])->save();

        activity('master')
            ->performedOn($item)
            ->causedBy($actor)
            ->withProperties(['reason_code' => $reasonCode, 'notes' => $notes])
            ->log('Item dinonaktifkan');

        return $item->refresh();
    }

    /** Juga dipakai Admin untuk meresmikan item sementara (BR-REQ-03). */
    public function reactivate(Item $item, ?User $actor = null): Item
    {
        $item->forceFill(['status' => ItemStatus::Active])->save();

        activity('master')
            ->performedOn($item)
            ->causedBy($actor)
            ->log('Item diaktifkan');

        return $item->refresh();
    }
}
