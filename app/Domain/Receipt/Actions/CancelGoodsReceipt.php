<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\GoodsReceiptStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\GoodsReceipt;

/**
 * Permission: `receipt.cancel` — `draft` → `cancelled` (Katalog §2.5).
 *
 * GRN yang sudah `received` tidak bisa dibatalkan; koreksinya lewat ADJ atau
 * RTV (BR-GEN-04). Alasan wajib, keterangan opsional (BR-GEN-11).
 */
class CancelGoodsReceipt
{
    public function handle(GoodsReceipt $receipt, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): GoodsReceipt
    {
        if ($receipt->status !== GoodsReceiptStatus::Draft) {
            throw ReceiptRuleException::rule(
                'BR-GEN-04',
                'GRN berstatus '.$receipt->status->label().' tidak bisa dibatalkan; koreksi lewat penyesuaian stok atau retur ke vendor.',
            );
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw ReceiptRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $receipt->forceFill([
            'status' => GoodsReceiptStatus::Cancelled,
            'cancel_reason_id' => $reasonCodeId,
            'notes' => $keterangan ?? $receipt->notes,
        ])->save();

        activity('receipt')
            ->performedOn($receipt)
            ->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
            ->log('GRN dibatalkan');

        return $receipt->refresh();
    }
}
