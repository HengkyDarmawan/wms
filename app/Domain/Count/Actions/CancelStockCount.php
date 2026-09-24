<?php

declare(strict_types=1);

namespace App\Domain\Count\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Count\Enums\StockCountStatus;
use App\Domain\Count\Exceptions\CountRuleException;
use App\Domain\Count\Models\StockCount;
use App\Domain\Master\Enums\ReasonContext;
use App\Domain\Master\Models\ReasonCode;

/**
 * Permission: `count.cancel` — `planned → cancelled` (Katalog §2.13).
 *
 * Hanya sebelum dimulai: bin belum dibeku dan belum ada snapshot. Sesi yang
 * sudah berjalan diselesaikan lewat rekonsiliasi. Alasan `*` wajib
 * (BR-GEN-11).
 */
class CancelStockCount
{
    public function handle(StockCount $count, ?int $reasonCodeId, ?string $notes = null, ?User $actor = null): StockCount
    {
        if ($count->status !== StockCountStatus::Planned) {
            throw CountRuleException::rule('BR-GEN-01', 'Hanya sesi berstatus Direncanakan yang bisa dibatalkan.');
        }

        $valid = $reasonCodeId !== null && ReasonCode::query()
            ->whereKey($reasonCodeId)->where('context', ReasonContext::Cancel->value)->exists();

        if (! $valid) {
            throw CountRuleException::field('BR-GEN-11', 'reason', 'Alasan pembatalan wajib dipilih.');
        }

        $keterangan = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        $count->forceFill([
            'status' => StockCountStatus::Cancelled,
            'cancel_reason_id' => $reasonCodeId,
            'notes' => $keterangan ?? $count->notes,
        ])->save();

        activity('count')->performedOn($count)->causedBy($actor)
            ->withProperties(['reason_code_id' => $reasonCodeId, 'keterangan' => $keterangan])
            ->log('Sesi opname dibatalkan');

        return $count->refresh();
    }
}
