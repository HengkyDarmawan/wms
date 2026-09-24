<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\VendorReturnStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\VendorReturn;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `vendor_return.cancel` — `submitted`/`pending_approval`/`approved`
 * → `cancelled` (Katalog §2.16). Barang tetap di Karantina. Alasan wajib.
 */
class CancelVendorReturn
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(VendorReturn $rtv, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): VendorReturn
    {
        if (! $rtv->status->isCancellable()) {
            throw ReceiptRuleException::rule(
                'BR-GEN-03',
                'RTV berstatus '.$rtv->status->label().' tidak bisa dibatalkan.',
            );
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw ReceiptRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        return DB::transaction(function () use ($rtv, $reasonCodeId, $keterangan, $actor) {
            $rtv->forceFill([
                'status' => VendorReturnStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'notes' => $keterangan ?? $rtv->notes,
            ])->save();

            // Tugas approval yang masih terbuka ikut dihentikan (20-approval §4).
            $this->approval->withdraw(ApprovalDocumentType::VendorReturn, (int) $rtv->id, 'RTV dibatalkan.', $actor);

            activity('receipt')->performedOn($rtv)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('RTV dibatalkan');

            return $rtv->refresh();
        });
    }
}
