<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Models\GoodsReceipt;

/**
 * Izin GRN (19-receipt-putaway §2).
 *
 * Cakupan gudang ditegakkan global scope (`ScopedToUser`, BR-ACC-05): GRN
 * gudang lain tidak ditemukan sama sekali. Di sini yang diperiksa izin dan
 * kelayakan status, supaya tombol yang tidak berguna tidak muncul.
 */
class GoodsReceiptPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('receipt.view');
    }

    public function view(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('receipt.create');
    }

    public function update(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.create') && $receipt->status === GoodsReceiptStatus::Draft;
    }

    public function receive(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.receive') && $receipt->status === GoodsReceiptStatus::Draft;
    }

    public function qc(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.qc')
            && in_array($receipt->status, [GoodsReceiptStatus::Received, GoodsReceiptStatus::Completed], true);
    }

    public function complete(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.complete')
            && in_array($receipt->status, [GoodsReceiptStatus::Received, GoodsReceiptStatus::Completed], true);
    }

    public function cancel(User $actor, GoodsReceipt $receipt): bool
    {
        return $actor->hasPermission('receipt.cancel') && $receipt->status === GoodsReceiptStatus::Draft;
    }
}
