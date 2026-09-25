<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Exceptions\PurchasingRuleException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderLine;
use App\Domain\Purchasing\Support\PurchaseOrderIssuer;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `po.close` — `partially_fulfilled → closed_short` (Katalog
 * §2.17, A-215): sisa yang tidak akan datang ditutup dengan Alasan `*`, lalu
 * dilepas di gudang (`po_cancelled`) sehingga bisa dipesan ulang.
 */
class ClosePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderIssuer $issuer) {}

    public function handle(PurchaseOrder $po, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PurchaseOrder
    {
        if ($po->status !== PurchaseOrderStatus::PartiallyFulfilled) {
            throw PurchasingRuleException::rule('BR-GEN-01', 'Tutup sisa hanya untuk PO yang sebagian barangnya sudah diterima.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw PurchasingRuleException::field('BR-GEN-11', 'reason', 'Alasan penutupan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 255) : null;

        return DB::transaction(function () use ($po, $reasonCodeId, $keterangan, $actor) {
            $lepas = [];

            foreach ($po->lines()->get() as $l) {
                /** @var PurchaseOrderLine $l */
                $sisa = $l->outstandingQty();

                if ($sisa > 0) {
                    $lepas[(int) $l->id] = $sisa;
                    $l->forceFill(['qty_cancelled' => round((float) $l->qty_cancelled + $sisa, 4)])->save();
                }
            }

            $this->issuer->release($po, $lepas, $actor);

            $po->forceFill([
                'status' => PurchaseOrderStatus::ClosedShort,
                'close_reason_id' => $reasonCodeId,
                'closed_at' => now(),
            ])->save();

            activity('purchase_order')->performedOn($po)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan, 'dilepas' => $lepas])
                ->log('PO ditutup dengan sisa');

            return $po->refresh();
        });
    }
}
