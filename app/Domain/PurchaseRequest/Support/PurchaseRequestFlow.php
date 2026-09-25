<?php

declare(strict_types=1);

namespace App\Domain\PurchaseRequest\Support;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\PurchaseRequest\Enums\PurchaseRequestStatus;
use App\Domain\PurchaseRequest\Models\PurchaseRequest;
use App\Domain\PurchaseRequest\Models\PurchaseRequestLine;
use App\Domain\Stock\Enums\StockEventType;
use App\Domain\Stock\Support\StockLedger;

/**
 * Langkah bersama `→ submitted → pending_approval / approved` (Katalog §2.15)
 * untuk PRQ manual, backorder REQ, dan draf titik pesan ulang yang diajukan:
 * kejadian `purchase_requested` (tanpa pergerakan, matriks §14), lalu mesin
 * approval — tanpa aturan yang cocok PRQ langsung disetujui (A-08).
 */
class PurchaseRequestFlow
{
    public function __construct(
        private readonly ApprovalEngine $approval,
        private readonly StockLedger $ledger,
    ) {}

    public function submit(PurchaseRequest $prq, ?User $actor): PurchaseRequest
    {
        $prq->loadMissing('warehouse', 'project', 'materialRequest');

        $prq->forceFill([
            'status' => PurchaseRequestStatus::Submitted,
            'submitted_by' => $actor?->id,
            'submitted_at' => now(),
        ])->save();

        $this->ledger->emitEvent(StockEventType::PurchaseRequested, array_filter([
            'purchase_request_number' => $prq->number,
            'origin' => $prq->origin->value,
            'warehouse_id' => (int) $prq->warehouse_id,
            'warehouse_code' => $prq->warehouse?->code,
            'project_code' => $prq->project?->code,
            'material_request_number' => $prq->materialRequest?->number,
            'lines' => $prq->lines()->with('item:id,code')->orderBy('id')->get()->map(fn (PurchaseRequestLine $l) => [
                'item_id' => (int) $l->item_id,
                'item_code' => $l->item?->code,
                'qty_base' => (float) $l->qty_base,
                'required_date' => $l->required_date?->toDateString(),
            ])->all(),
        ], fn ($v) => $v !== null), 'purchase_request', (int) $prq->id, $prq->number, $prq->project_id !== null ? (int) $prq->project_id : null);

        activity('purchase_request')->performedOn($prq)->causedBy($actor)
            ->withProperties(['asal' => $prq->origin->value, 'baris' => $prq->lines()->count()])
            ->log('PRQ diajukan (purchase_requested)');

        // Katalog §2.15: submitted → pending_approval / approved otomatis sesuai aturan.
        $prq->forceFill(['status' => PurchaseRequestStatus::PendingApproval])->save();
        $this->approval->submit(ApprovalDocumentType::PurchaseRequest, $prq, $actor);

        return $prq->refresh();
    }
}
