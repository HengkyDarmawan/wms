<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Purchasing\Support\PurchaseOrderLines;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `po.submit` — `draft → pending_approval / approved` (Katalog
 * §2.17). Baris diperiksa ulang terhadap PRQ saat ini (A-210), lalu mesin
 * approval memakai nilai PO (A-212); tanpa aturan PO langsung disetujui dan
 * diterbitkan ke gudang (A-08, A-213).
 */
class SubmitPurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderLines $lines,
        private readonly ApprovalEngine $approval,
    ) {}

    public function handle(PurchaseOrder $po, ?User $actor = null): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::Draft) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'Hanya PO draf yang bisa diajukan.');
        }

        $this->lines->normalize((int) $po->warehouse_id, $po->lines()->get()->map(fn (PurchaseOrderLine $l) => [
            'purchase_request_line_id' => $l->purchase_request_line_id,
            'qty_base' => $l->qty_base,
            'unit_price' => $l->unit_price,
        ])->all(), (int) $po->id);

        return DB::transaction(function () use ($po, $actor) {
            $po->forceFill([
                'status' => PurchaseOrderStatus::PendingApproval,
                'submitted_by' => $actor?->id,
                'submitted_at' => now(),
            ])->save();

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['nilai' => (float) $po->total_amount])
                ->log('PO diajukan');

            $this->approval->submit(ApprovalDocumentType::PurchaseOrder, $po, $actor);

            return $po->refresh();
        });
    }
}
