<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Purchasing\Support\PurchaseOrderIssuer;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `po.cancel` — `draft` / `pending_approval` / `approved` →
 * `cancelled` (Katalog §2.17, A-215). Alasan `*`; ditolak bila sudah ada
 * barang diterima (pakai *Tutup sisa*). PO yang sudah diterbitkan melepas
 * pesanannya di gudang (`po_cancelled`) sehingga PRQ bisa dipesan lagi.
 */
class CancelPurchaseOrder
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly PurchaseOrderIssuer $issuer,
    ) {}

    public function handle(PurchaseOrder $po, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PurchaseOrder
    {
        if (! $po->status->isCancellable()) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'PO berstatus '.$po->status->label().' tidak bisa dibatalkan.');
        }

        if ($po->hasReceipts()) {
            throw PurchasingRuleException::rule('BR-GEN-03', 'Sebagian barang PO ini sudah diterima; gunakan Tutup sisa.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw PurchasingRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($po, $reasonCodeId, $keterangan, $actor) {
            $dari = $po->status;

            if ($dari === PurchaseOrderStatus::Approved) {
                $lepas = [];

                foreach ($po->lines()->get() as $l) {
                    /** @var PurchaseOrderLine $l */
                    $lepas[(int) $l->id] = $l->outstandingQty();
                    $l->forceFill(['qty_cancelled' => round((float) $l->qty_cancelled + $l->outstandingQty(), 4)])->save();
                }

                $this->issuer->release($po, $lepas, $actor);
            }

            $po->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
            ])->save();

            if ($dari === PurchaseOrderStatus::PendingApproval) {
                $this->approval->withdraw(ApprovalDocumentType::PurchaseOrder, (int) $po->id, 'PO dibatalkan.', $actor);
            }

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan, 'dari' => $dari->value])
                ->log('PO dibatalkan');

            return $po->refresh();
        });
    }
}
