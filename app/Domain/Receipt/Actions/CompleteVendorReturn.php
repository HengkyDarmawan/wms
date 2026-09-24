<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\VendorReturn;

/**
 * Permission: `vendor_return.complete` — `shipped` → `completed` (Katalog
 * §2.16). Penindak Lanjut PR mencatat konfirmasi vendor; barang pengganti
 * masuk lewat GRN baru yang merujuk RTV ini (BR-GRN-04) dan menautkan dirinya
 * sendiri saat diterima.
 */
class CompleteVendorReturn
{
    public function handle(VendorReturn $rtv, ?string $notes = null, ?User $actor = null): VendorReturn
    {
        if ($rtv->status !== VendorReturnStatus::Shipped) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya RTV berstatus Dikirim yang bisa diselesaikan.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $rtv->forceFill([
            'status' => VendorReturnStatus::Completed,
            'vendor_confirmed_at' => now(),
            'notes' => $keterangan ?? $rtv->notes,
        ])->save();

        activity('receipt')->performedOn($rtv)->causedBy($actor)
            ->withProperties(['keterangan' => $keterangan])
            ->log('RTV selesai: dikonfirmasi vendor');

        return $rtv->refresh();
    }
}
