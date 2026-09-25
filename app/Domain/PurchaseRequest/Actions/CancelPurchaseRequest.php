<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Exceptions\PurchaseRequestRuleException;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Support\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `pr.cancel` — `draft` / `submitted` / `pending_approval` /
 * `approved` / `forwarded` → `cancelled` (Katalog §2.15). Alasan `*`
 * (BR-GEN-11); ditolak bila sudah ada barang diterima lewat GRN. PRQ yang
 * pernah diajukan menerbitkan `purchase_request_cancelled` (matriks §14);
 * approval yang menunggu ditarik.
 */
class CancelPurchaseRequest
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly StockLedger $ledger,
    ) {}

    public function handle(PurchaseRequest $prq, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PurchaseRequest
    {
        if (! $prq->status->isCancellable()) {
            throw PurchaseRequestRuleException::rule('BR-GEN-01', 'PRQ berstatus '.$prq->status->label().' tidak bisa dibatalkan.');
        }

        if ($prq->hasReceipts()) {
            throw PurchaseRequestRuleException::rule('BR-GEN-03', 'Sebagian barang PRQ ini sudah diterima lewat GRN; PRQ tidak bisa dibatalkan.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw PurchaseRequestRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($prq, $reasonCodeId, $keterangan, $actor) {
            $dari = $prq->status;

            $prq->forceFill([
                'status' => PurchaseRequestStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'cancelled_at' => now(),
                'notes' => $keterangan ?? $prq->notes,
            ])->save();

            if ($dari === PurchaseRequestStatus::PendingApproval) {
                $this->approval->withdraw(ApprovalDocumentType::PurchaseRequest, (int) $prq->id, 'PRQ dibatalkan.', $actor);
            }

            if ($dari !== PurchaseRequestStatus::Draft) {
                $this->ledger->emitEvent(StockEventType::PurchaseRequestCancelled, array_filter([
                    'purchase_request_number' => $prq->number,
                    'origin' => $prq->origin->value,
                    'warehouse_id' => (int) $prq->warehouse_id,
                    'from_status' => $dari->value,
                    'reason_code_id' => $reasonCodeId,
                ], fn ($v) => $v !== null), 'purchase_request', (int) $prq->id, $prq->number, $prq->project_id !== null ? (int) $prq->project_id : null);
            }

            activity('purchase_request')->performedOn($prq)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan, 'dari' => $dari->value])
                ->log('PRQ dibatalkan');

            return $prq->refresh();
        });
    }
}
