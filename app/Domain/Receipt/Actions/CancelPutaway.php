<?php

declare(strict_types=1);

namespace App\Domain\Receipt\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;
use App\Domain\Receipt\Enums\PutawayTaskStatus;
use App\Domain\Receipt\Exceptions\ReceiptRuleException;
use App\Domain\Receipt\Models\PutawayTask;

/**
 * Permission: `putaway.cancel` — `pending` → `cancelled` (Katalog §2.6).
 *
 * Barang tetap di bin Penerimaan; PUT baru dibuat dari detail GRN
 * (`CompleteGoodsReceipt::replan`). Alasan wajib (BR-GEN-11).
 */
class CancelPutaway
{
    public function handle(PutawayTask $task, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): PutawayTask
    {
        if ($task->status !== PutawayTaskStatus::Pending) {
            throw ReceiptRuleException::rule('BR-GEN-01', 'Hanya tugas put-away berstatus Menunggu yang bisa dibatalkan.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw ReceiptRuleException::field('BR-GEN-11', 'reasonCode', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $task->forceFill([
            'status' => PutawayTaskStatus::Cancelled,
            'cancel_reason_id' => $reasonCodeId,
            'notes' => $keterangan ?? $task->notes,
        ])->save();

        activity('receipt')
            ->performedOn($task)
            ->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
            ->log('Put-away dibatalkan');

        return $task->refresh();
    }
}
