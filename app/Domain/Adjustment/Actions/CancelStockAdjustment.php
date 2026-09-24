<?php

declare(strict_types=1);

namespace App\Domain\Adjustment\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Adjustment\Enums\StockAdjustmentStatus;
use App\Domain\Adjustment\Exceptions\AdjustmentRuleException;
use App\Domain\Adjustment\Models\StockAdjustment;
use App\Domain\Approval\Enums\ApprovalDocumentType;
use App\Domain\Approval\Support\ApprovalEngine;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use Illuminate\Support\Facades\DB;

/**
 * Permission: `adjustment.cancel` — `submitted`/`pending_approval` →
 * `cancelled` (Katalog §2.12). Belum ada stok yang bergerak, jadi tidak ada
 * pembalik. Alasan `*` wajib, Keterangan opsional (BR-GEN-11).
 *
 * ADJ hasil opname dikelola sesinya (BR-OPN-06), tidak dibatalkan dari sini.
 */
class CancelStockAdjustment
{
    public function __construct(private readonly ApprovalEngine $approval) {}

    public function handle(StockAdjustment $adj, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): StockAdjustment
    {
        if (! $adj->status->isCancellable()) {
            throw AdjustmentRuleException::rule('BR-GEN-03', 'ADJ berstatus '.$adj->status->label().' tidak bisa dibatalkan.');
        }

        if (! $adj->isManual()) {
            throw AdjustmentRuleException::rule('BR-OPN-06', 'ADJ hasil opname mengikuti sesinya dan tidak dibatalkan sendiri.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw AdjustmentRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        return DB::transaction(function () use ($adj, $reasonCodeId, $keterangan, $actor) {
            $adj->forceFill([
                'status' => StockAdjustmentStatus::Cancelled,
                'cancel_reason_id' => $reasonCodeId,
                'notes' => $keterangan ?? $adj->notes,
            ])->save();

            $this->approval->withdraw(ApprovalDocumentType::StockAdjustment, (int) $adj->id, 'ADJ dibatalkan.', $actor);

            activity('adjustment')->performedOn($adj)->causedBy($actor)
                ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
                ->log('ADJ dibatalkan');

            return $adj->refresh();
        });
    }
}
